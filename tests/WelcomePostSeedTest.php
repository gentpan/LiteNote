<?php
declare(strict_types=1);

namespace Tests;

use App\Services\Installer;
use App\Services\PostContentStorage;
use PHPUnit\Framework\TestCase;

final class WelcomePostSeedTest extends TestCase
{
    public function testDefaultWelcomeMarkdownIsNotEmpty(): void
    {
        $markdown = Installer::defaultWelcomeMarkdown();
        $this->assertNotSame('', trim($markdown));
        $this->assertStringContainsString('LiteNote', $markdown);
        $this->assertStringContainsString('欢迎使用', $markdown);
    }

    public function testEnsureWelcomePostContentWritesWhenMissing(): void
    {
        $slug = 'welcome-test-' . bin2hex(random_bytes(4));
        $path = PostContentStorage::path($slug);
        if (is_file($path)) {
            @unlink($path);
        }

        $this->assertSame('', PostContentStorage::read($slug));

        $seed = Installer::defaultWelcomeMarkdown();
        PostContentStorage::write($slug, $seed);
        $this->assertSame(trim($seed), trim(PostContentStorage::read($slug)));

        @unlink($path);
    }
}
