<?php
declare(strict_types=1);

namespace App\Traits;

use App\Core\Response;
use App\Core\Session;

/**
 * 为后台 Controller 提供统一的 Flash + Redirect 快捷方法。
 */
trait HasFlashRedirect
{
    protected function flashSuccess(string $message): void
    {
        Session::flash('success', $message);
    }

    protected function flashError(string $message): void
    {
        Session::flash('error', $message);
    }

    protected function redirect(string $url): never
    {
        Response::redirect($url);
    }

    /**
     * 校验失败时 flash 错误并跳转。
     */
    protected function backWithError(string $message, string $fallbackUrl = '/admin'): never
    {
        Session::flash('error', $message);
        $this->redirect($_SERVER['HTTP_REFERER'] ?? $fallbackUrl);
    }
}
