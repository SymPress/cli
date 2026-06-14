<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Repository;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Repository\RepositoryManifestLoader;

final class RepositoryManifestLoaderTest extends TestCase
{
    public function testLoadReadsManifestFromDirectory(): void
    {
        $directory = $this->temporaryDirectory();
        mkdir($directory . '/.sympress', 0777, true);
        file_put_contents($directory . '/.sympress/cli.json', json_encode([
            'profiles' => [
                [
                    'id' => 'website',
                    'label' => 'Website Override',
                    'description' => 'From repo.',
                    'exampleName' => 'repo-site',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $manifest = (new RepositoryManifestLoader())->load($directory);

        self::assertNotNull($manifest);
        self::assertSame('Website Override', $manifest->profiles[0]->label);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $directory;
    }
}
