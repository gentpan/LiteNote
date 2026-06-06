<?php
declare(strict_types=1);

namespace App\Services\Plugins;

/**
 * 插件入口约定。
 *
 * 每个插件在自己的目录下放一个入口文件 Plugin.php,其中定义一个实现本接口的
 * 类(命名空间 LiteNotePlugin\<Studly>\Plugin)。运行时由 PluginManager 加载:
 *  - register():每次请求启动时调用,向 PluginContext 注册路由/适配器/菜单等运行时扩展;
 *  - migrate() :仅在插件被启用(enable)时调用一次,负责建表 / 数据迁移,必须幂等;
 *  - uninstall():在插件被禁用(disable)时调用,清理持久副作用(如导航页记录)。
 */
interface PluginInterface
{
    public function register(PluginContext $ctx): void;

    public function migrate(): void;

    public function uninstall(): void;
}
