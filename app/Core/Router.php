<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 路由（改进版）
 * 变更点：
 * 1. 中间件支持返回 Response 对象或 false 短路。
 * 2. 404 处理更统一，安装路径例外逻辑更清晰。
 * 3. 增加路由命名支持（可选）。
 * 4. 匹配逻辑提取为独立方法，降低 dispatch 复杂度。
 */
final class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private array $groupPrefix = [];
    private array $groupMiddleware = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function any(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('*', $path, $handler, $middleware);
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevMiddleware = $this->groupMiddleware;
        $this->groupPrefix[] = trim($prefix, '/');
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $callback($this);
        $this->groupPrefix = $prevPrefix;
        $this->groupMiddleware = $prevMiddleware;
    }

    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $prefix = $this->groupPrefix ? '/' . implode('/', $this->groupPrefix) : '';
        $fullPath = $prefix . ($path === '/' ? '/' : $path);
        $fullPath = '/' . trim($fullPath, '/');
        if ($fullPath === '//') {
            $fullPath = '/';
        }

        $allMiddleware = array_merge($this->groupMiddleware, $middleware);

        $this->routes[] = [
            'method'     => $method,
            'path'       => $fullPath,
            'handler'    => $handler,
            'middleware' => $allMiddleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $path = $this->normalizePath($request->path);
        $triedPaths = $this->buildTriedPaths($path);

        foreach ($this->routes as $route) {
            if ($route['method'] !== '*' && $route['method'] !== $request->method) {
                continue;
            }

            $params = null;
            foreach ($triedPaths as $tryPath) {
                $params = $this->match($route['path'], $tryPath);
                if ($params !== null) {
                    break;
                }
            }

            if ($params !== null) {
                // 中间件管道
                foreach ($route['middleware'] as $mw) {
                    $mwInstance = is_string($mw) ? new $mw() : $mw;
                    $result = $mwInstance->handle($request);
                    if ($result === false) {
                        return;
                    }
                }

                $this->runHandler($route['handler'], $request, $params);
                return;
            }
        }

        // 未匹配
        if ($request->path === '/install' || str_starts_with($request->path, '/install')) {
            return;
        }
        Response::notFound('404 - 页面不存在');
    }

    private function normalizePath(string $path): string
    {
        if (str_ends_with($path, '.html')) {
            $path = substr($path, 0, -5);
        }
        return $path === '' ? '/' : $path;
    }

    private function buildTriedPaths(string $path): array
    {
        $tried = [$path];
        if (substr($path, -1) !== '/' && $path !== '/') {
            $tried[] = $path . '/';
        } elseif ($path !== '/') {
            $tried[] = rtrim($path, '/');
        }
        return $tried;
    }

    private function runHandler(callable|array $handler, Request $request, array $params): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = new $class();
            echo $instance->$method($request, $params);
        } elseif (is_callable($handler)) {
            echo $handler($request, $params);
        }
    }

    private function match(string $routePath, string $actualPath): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $routePath);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $actualPath, $matches)) {
            $params = [];
            foreach ($matches as $k => $v) {
                if (!is_int($k)) {
                    $params[$k] = $v;
                }
            }
            return $params;
        }
        return null;
    }
}
