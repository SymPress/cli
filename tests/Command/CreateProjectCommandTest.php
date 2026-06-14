<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Command;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Command\CreateProjectCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateProjectCommandTest extends TestCase
{
    public function testDryRunUsesManifestProvidedCatalogEntries(): void
    {
        $directory = $this->temporaryDirectory();
        $manifest = $directory . '/sympress-cli.json';
        file_put_contents($manifest, json_encode([
            'templates' => [
                [
                    'id' => 'sympress-starter',
                    'label' => 'Repository Starter',
                    'packageName' => 'acme/starter',
                    'repositoryUrl' => 'https://github.com/acme/starter',
                    'description' => 'Starter from manifest.',
                    'defaultVersion' => 'dev-main',
                ],
            ],
            'profiles' => [
                [
                    'id' => 'custom',
                    'label' => 'Custom',
                    'description' => 'Custom project type.',
                    'exampleName' => 'custom-project',
                ],
            ],
            'packageSuggestions' => [
                [
                    'name' => 'acme/runtime',
                    'label' => 'Runtime',
                    'description' => 'Runtime package.',
                    'recommendedProfiles' => ['custom'],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new CreateProjectCommand());
        $tester->execute([
            'directory' => $directory . '/project',
            '--manifest' => $manifest,
            '--no-remote-manifest' => true,
            '--dry-run' => true,
            '--no-setup' => true,
            '--type' => 'custom',
            '--name' => 'Manifest App',
            '--package-name' => 'acme/manifest-app',
        ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('Repository Starter', $output);
        self::assertStringContainsString('acme/starter:dev-main', $output);
        self::assertStringContainsString('acme/runtime:*', $output);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $directory;
    }
}
