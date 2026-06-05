<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 模板视图引擎（改进版）
 * 变更点：
 * 1. render() 明确支持可选的第三个参数 $layout，兼容旧代码。
 * 2. 增加 View Composer 机制，避免每个 Controller 重复注入共享数据。
 * 3. 编译缓存增加文件mtime检查，模板修改后自动失效。
 * 4. 修复 @include 支持。
 */
final class View
{
    private static array $shared = [];
    private static array $composers = [];
    private static ?string $cacheDir = null;

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * 注册视图 Composer：当渲染指定模板时自动合并返回的数据。
     *
     * @param string|array $template 模板名(支持通配符 *;也接受数组批量注册)
     * @param callable $callback 接收当前 data，返回要合并的数组
     */
    public static function composer(string|array $template, callable $callback): void
    {
        foreach ((array) $template as $tpl) {
            self::$composers[$tpl][] = $callback;
        }
    }

    /**
     * 渲染模板。
     *
     * @param string $template 模板路径（点分隔）
     * @param array $data 视图数据
     * @param string|null $layout 显式指定布局（覆盖模板内的 @extends）
     * @return string
     */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $data = array_merge(self::$shared, $data);

        // 执行匹配的 View Composers
        foreach (self::$composers as $pattern => $callbacks) {
            if (self::matchComposer($pattern, $template)) {
                foreach ($callbacks as $cb) {
                    $data = array_merge($data, $cb($data));
                }
            }
        }

        return self::renderFile($template, $data, $layout);
    }

    public static function display(string $template, array $data = [], ?string $layout = null): void
    {
        echo self::render($template, $data, $layout);
    }

    private static function renderFile(string $template, array $data, ?string $layout = null): string
    {
        $path = self::resolvePath($template);
        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$template} ({$path})");
        }
        $source = (string) file_get_contents($path);

        // 处理 @extends
        $extendsMatch = [];
        $hasExtends = preg_match('/@extends\(\s*[\'"]?(.+?)[\'"]?\s*\)/', $source, $extendsMatch);
        $parentTemplate = $layout ?? ($extendsMatch[1] ?? null);

        if ($hasExtends || $layout) {
            $source = preg_replace('/@extends\(\s*[\'"]?.+?[\'"]?\s*\)\s*\n?/', '', $source, 1);

            $sections = [];
            if (preg_match_all('/@section\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@endsection/s', $source, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $sections[$match[1]] = $match[2];
                }
                $source = preg_replace('/@section\(\s*[\'"].+?[\'"]\s*\).*?@endsection/s', '', $source);
            }

            if ($parentTemplate) {
                $parentPath = self::resolvePath($parentTemplate);
                $parentSource = is_file($parentPath) ? (string) file_get_contents($parentPath) : '';
                $parentSource = preg_replace_callback(
                    '/@yield\(\s*[\'"](.+?)[\'"]\s*\)/',
                    function ($m) use ($sections) {
                        return $sections[$m[1]] ?? '';
                    },
                    $parentSource
                );
                $source = $parentSource;
            }
        }

        // 处理 @include —— 编译成「运行时 include」,使被包含模板能访问当前作用域
        // (包括 @foreach 内的循环变量),并支持递归嵌套。
        $source = self::resolveIncludes($source);

        $phpSource = self::compile($source);
        $compiledFile = self::getCacheFile($template, $phpSource, $path);

        extract($data, EXTR_SKIP);
        ob_start();
        include $compiledFile;
        return (string) ob_get_clean();
    }

    /**
     * 把 @include('x') 替换成运行时 `include '<编译缓存文件>'`。
     * - 被包含模板在调用处的作用域内执行(能用 @foreach 循环变量、$currentAdmin 等)。
     * - 递归解析嵌套 @include。
     */
    private static function resolveIncludes(string $source): string
    {
        return preg_replace_callback(
            '/@include\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($m) {
                $name = $m[1];
                $incPath = self::resolvePath($name);
                if (!is_file($incPath)) {
                    return '';
                }
                $incSource = (string) file_get_contents($incPath);
                $incSource = self::resolveIncludes($incSource); // 递归处理嵌套 @include
                $incPhp = self::compile($incSource);
                $incFile = self::getCacheFile($name, $incPhp, $incPath);
                return '<?php include ' . var_export($incFile, true) . '; ?>';
            },
            $source
        );
    }

    private static function compile(string $source): string
    {
        $source = self::convertBlockDirective($source, 'if',      'if ',      ':');
        $source = self::convertBlockDirective($source, 'elseif',  'elseif ',  ':');
        $source = self::convertBlockDirective($source, 'foreach', 'foreach ', ':');
        $source = self::convertBlockDirective($source, 'for',     'for ',     ':');

        $source = str_replace('@else',      '<?php else: ?>',      $source);
        $source = str_replace('@endif',     '<?php endif; ?>',     $source);
        $source = str_replace('@endforeach','<?php endforeach; ?>',$source);
        $source = str_replace('@endfor',    '<?php endfor; ?>',    $source);

        $source = str_replace('@php',    '<?php', $source);
        $source = str_replace('@endphp', '?>',    $source);

        // {{-- comment --}} 必须在 {{ }} 之前处理，否则 {{-- 会被当作 {{ 匹配
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source);

        $source = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?= $1 ?>', $source);

        $source = preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}/s',
            function ($m) {
                return '<?= htmlspecialchars((string)(' . $m[1] . ' ?? \'\'), ENT_QUOTES, \'UTF-8\') ?>';
            },
            $source
        );

        $source = preg_replace("/@yield\(.+?\)/", '', $source);

        return $source;
    }

    private static function convertBlockDirective(string $source, string $name, string $prefix, string $suffix): string
    {
        $needle = '@' . $name . '(';
        $result = '';
        $offset = 0;
        $len = strlen($source);
        while (($pos = strpos($source, $needle, $offset)) !== false) {
            $result .= substr($source, $offset, $pos - $offset);
            $i = $pos + strlen($needle) - 1;
            $depth = 0;
            $end = -1;
            for ($j = $i; $j < $len; $j++) {
                $c = $source[$j];
                if ($c === '(') {
                    $depth++;
                } elseif ($c === ')') {
                    $depth--;
                    if ($depth === 0) { $end = $j; break; }
                } elseif ($c === '"' || $c === "'") {
                    $q = $c;
                    $j++;
                    while ($j < $len && $source[$j] !== $q) {
                        if ($source[$j] === '\\') { $j++; }
                        if ($j >= $len) break;
                        $j++;
                    }
                }
            }
            if ($end === -1) {
                $result .= substr($source, $pos, strlen($needle));
                $offset = $pos + strlen($needle);
                continue;
            }
            $inner = substr($source, $i, $end - $i + 1);
            $result .= '<?php ' . $prefix . $inner . $suffix . ' ?>';
            $offset = $end + 1;
        }
        $result .= substr($source, $offset);
        return $result;
    }

    private static function getCacheDir(): string
    {
        if (self::$cacheDir === null) {
            self::$cacheDir = self::basePath() . '/runtime/storage/cache/views';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0775, true);
            }
        }
        return self::$cacheDir;
    }

    private static function getCacheFile(string $template, string $phpSource, string $sourcePath): string
    {
        $mtime = filemtime($sourcePath) ?: time();
        $key = md5($template . '|' . $mtime . '|' . $phpSource);
        $file = self::getCacheDir() . '/' . $key . '.php';
        if (!is_file($file)) {
            file_put_contents($file, $phpSource);
        }
        return $file;
    }

    private static function resolvePath(string $template): string
    {
        if (str_starts_with($template, 'layouts.front') || TemplateMap::isFrontendTemplate($template)) {
            try {
                \App\Services\ThemeManager::loadFunctions();
                $themePath = \App\Services\ThemeManager::viewPath($template);
                if ($themePath !== null) {
                    return $themePath;
                }
            } catch (\Throwable) {
                // Theme fallback must never break rendering the built-in views.
            }
        }

        $template = TemplateMap::map($template);
        $relative = str_replace('.', '/', $template);
        $basePath = self::basePath();

        if (str_starts_with($template, 'admin.')) {
            return $basePath . '/admin/pages/' . self::flatTemplateName(substr($relative, strlen('admin/'))) . '.php';
        }

        if (str_starts_with($template, 'layouts.')) {
            $layout = substr($relative, strlen('layouts/'));
            $layout = $layout === 'admin_auth' ? 'auth-layout' : $layout . '-layout';
            return $basePath . '/admin/parts/' . $layout . '.php';
        }

        if (str_starts_with($template, 'partials.')) {
            return $basePath . '/admin/parts/' . substr($relative, strlen('partials/')) . '.php';
        }

        if (str_starts_with($template, 'system.')) {
            return $basePath . '/core/system/' . substr($relative, strlen('system/')) . '.php';
        }

        return $basePath . '/core/system/' . $relative . '.php';
    }

    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
    }

    private static function flatTemplateName(string $relative): string
    {
        $parts = array_values(array_filter(explode('/', $relative), static fn(string $part): bool => $part !== ''));
        if (($parts[0] ?? '') === 'auth') {
            array_shift($parts);
        }
        if (end($parts) === 'index') {
            array_pop($parts);
        }
        return implode('-', $parts);
    }

    private static function matchComposer(string $pattern, string $template): bool
    {
        if (str_contains($pattern, '*')) {
            $regex = '#^' . str_replace('*', '.*', $pattern) . '$#';
            return (bool) preg_match($regex, $template);
        }
        return $pattern === $template;
    }
}
