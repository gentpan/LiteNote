<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class PublicAuthSurfaceTest extends TestCase
{
    public function testCommentIdentityAndAdminLoginUseSeparateDialogs(): void
    {
        $header = (string) file_get_contents(dirname(__DIR__) . '/themes/ember/header.php');

        self::assertStringContainsString('data-identity-overlay', $header);
        self::assertStringContainsString('data-admin-login-overlay', $header);
        self::assertStringContainsString('仅用于评论，不创建用户账号', $header);
        self::assertStringNotContainsString('data-account-overlay', $header);
        self::assertStringNotContainsString('data-register-form', $header);
    }

    public function testPublicRegistrationRoutesAndScriptsAreRemoved(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__) . '/core/routes/web.php');
        $script = (string) file_get_contents(dirname(__DIR__) . '/themes/ember/assets/main.js');

        foreach (['/auth/register', '/auth/verify', '/auth/resend-verify', '/auth/passkey/register'] as $path) {
            self::assertStringNotContainsString($path, $routes);
            self::assertStringNotContainsString($path, $script);
        }
    }

    public function testAdminLoginRejectsNonAdminAccounts(): void
    {
        $passwordController = (string) file_get_contents(dirname(__DIR__) . '/core/app/Controllers/Admin/AuthController.php');
        $passkeyController = (string) file_get_contents(dirname(__DIR__) . '/core/app/Controllers/Admin/PasskeyController.php');

        self::assertStringContainsString('if (!$user->isAdmin())', $passwordController);
        self::assertStringContainsString('if (!$user->isAdmin())', $passkeyController);
        self::assertStringContainsString("'redirect' => '/admin'", $passkeyController);
    }

    public function testCommentAvatarMotionMatchesTransitionRecipe(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/themes/ember/assets/home.css');

        self::assertStringContainsString('--avatar-lift: -2px;', $css);
        self::assertStringContainsString('--avatar-dur: 320ms;', $css);
        self::assertStringContainsString('--avatar-scale: 1.025;', $css);
        self::assertStringContainsString('--avatar-falloff: 0.45;', $css);
        self::assertStringContainsString('translateY(var(--shift, 0px))', $css);
        self::assertStringContainsString('transition: transform var(--avatar-dur) var(--avatar-ease-in);', $css);
    }
}
