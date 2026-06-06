<?php
declare(strict_types=1);

namespace App\Services\Plugins;

use App\Core\View;
use App\Services\ActivityAdapters\ActivityAdapter;

/**
 * 传给插件 register() 的注册门面。
 *
 * 方法均为链式(返回 $this),内部把扩展写入 Registry(或直接转调 View::composer)。
 * 持有当前插件的 key 与目录,方便插件用 $ctx->baseDir() 拼相对路径。
 */
final class PluginContext
{
    public function __construct(
        private readonly string $key,
        private readonly string $baseDir,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    /** 注册前台路由。回调签名 function(\App\Core\Router $r): void */
    public function webRoutes(callable $cb): self
    {
        Registry::addWebRoutes($cb);
        return $this;
    }

    /** 注册后台路由(apply 时自动套 /admin 前缀与 AdminAuth)。回调签名 function(\App\Core\Router $r): void */
    public function adminRoutes(callable $cb): self
    {
        Registry::addAdminRoutes($cb);
        return $this;
    }

    public function activityAdapter(ActivityAdapter $adapter): self
    {
        Registry::addAdapter($adapter);
        return $this;
    }

    public function activityProvider(string $provider, array $definition): self
    {
        Registry::addProvider($provider, $definition);
        return $this;
    }

    /** $item:['label'=>..,'href'=>..,'icon'=>..,'group'=>'资源','sort'=>int] */
    public function adminMenu(array $item): self
    {
        Registry::addAdminMenu($item);
        return $this;
    }

    /** 注册前台导航页,取代 Page::systemDefinitions 里的硬编码项。 */
    public function navPage(string $slug, array $definition): self
    {
        Registry::addNavPage($slug, $definition);
        return $this;
    }

    /** 注册插件视图目录(绝对路径),作为"主题→插件→core/system"中的中间回落层。 */
    public function viewDir(string $dir): self
    {
        Registry::addViewDir($dir);
        return $this;
    }

    /** 透传到核心 View Composer。 */
    public function viewComposer(string|array $template, callable $cb): self
    {
        View::composer($template, $cb);
        return $this;
    }

    /** 注入一段前台 <head> HTML(通常是插件自带 CSS 的 <link>)。 */
    public function frontHead(string $html): self
    {
        Registry::addFrontHead($html);
        return $this;
    }

    /** 注册首页时间线贡献者。回调签名 function(): array<int,array{type:string,partial:string,time:int,item:mixed,pinned?:bool}> */
    public function homeFeedContributor(callable $cb): self
    {
        Registry::addHomeFeedContributor($cb);
        return $this;
    }
}
