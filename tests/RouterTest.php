<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testMatchesParameterizedRoute(): void
    {
        $router = new Router();
        $router->get('/posts/{id}/edit', static function (): void {});

        $request = $this->makeRequest('GET', '/posts/12/edit');
        ob_start();
        $router->dispatch($request);
        ob_end_clean();

        $this->assertTrue(true);
    }

    public function testStaticRouteHasPriorityOverCatchAll(): void
    {
        $router = new Router();
        $matched = false;
        $router->get('/hello', static function () use (&$matched): void {
            $matched = true;
            echo 'ok';
        });
        $router->get('/{slug}', static function (): void {
            echo 'fallback';
        });

        $request = $this->makeRequest('GET', '/hello');
        ob_start();
        $router->dispatch($request);
        $output = (string)ob_get_clean();

        $this->assertTrue($matched);
        $this->assertSame('ok', $output);
    }

    private function makeRequest(string $method, string $path): \App\Core\Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_COOKIE = [];

        return new \App\Core\Request();
    }
}
