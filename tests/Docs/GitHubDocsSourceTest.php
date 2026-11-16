<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Docs;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Docs\GitHubDocsSource;

final class GitHubDocsSourceTest extends TestCase
{
    public function testTokenIsSentOnlyToTheApiAndNotAcrossRedirects(): void
    {
        $source = new GitHubDocsSource('secret-token');
        $method = new \ReflectionMethod($source, 'httpOptions');

        $api = $method->invoke($source, 'https://api.github.com/repos/SymPress/kernel/git/trees/abc?recursive=1');
        self::assertIsArray($api);
        self::assertContains('Authorization: Bearer secret-token', $api['header']);
        self::assertSame(0, $api['follow_location']);

        $raw = $method->invoke($source, 'https://raw.githubusercontent.com/SymPress/kernel/abc/docs/index.md');
        self::assertIsArray($raw);
        self::assertStringNotContainsString('secret-token', implode("\n", $raw['header']));
        self::assertArrayNotHasKey('follow_location', $raw);
    }
}
