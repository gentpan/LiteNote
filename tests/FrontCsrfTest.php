<?php
declare(strict_types=1);

namespace Tests;

use App\Core\FrontCsrf;
use App\Core\Request;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class FrontCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        Session::start();
    }

    public function testTokenMatchesSession(): void
    {
        $token = FrontCsrf::token();
        $this->assertNotSame('', $token);
        $this->assertTrue(Session::verifyCsrf($token));
    }

    public function testRequestReadsCsrfHeader(): void
    {
        $token = Session::csrfToken();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        $request = new Request();
        $this->assertSame($token, $request->header('X-CSRF-Token'));
        $this->assertTrue(Session::verifyCsrf($request->header('X-CSRF-Token')));
    }
}
