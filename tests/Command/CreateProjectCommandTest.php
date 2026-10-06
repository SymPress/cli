<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Command;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Command\CreateProjectCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class CreateProjectCommandTest extends TestCase
{
    public function testDefaultAndExplicitReleaseKeepTheirResolvedRevisionDespiteManifestDefault(): void
    {
        $root = $this->temporaryDirectory();
        $repository = $root . '/repository';
        mkdir($repository . '/.sympress', 0700, true);
        $git = static function (array $arguments) use ($repository): string {
            $process = new Process(['git', '-C', $repository, ...$arguments]);
            $process->setEnv(['GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_INDEX_FILE' => false]);
            $process->mustRun();
            return trim($process->getOutput());
        };
        try {
            $git(['init', '-b', 'main']);
            $git(['config', 'user.name', 'Fixture']);
            $git(['config', 'user.email', 'fixture@example.test']);
            file_put_contents($repository . '/composer.json', '{"name":"sympress/starter"}');
            file_put_contents($repository . '/.sympress/cli.json', json_encode([
                'schemaVersion' => 1, 'templates' => [[
                    'id' => 'sympress-starter', 'label' => 'Starter', 'packageName' => 'sympress/starter',
                    'repositoryUrl' => 'https://github.com/SymPress/starter', 'description' => 'Fixture',
                    'defaultVersion' => '1.0.0',
                ]],
            ], JSON_THROW_ON_ERROR));
            $git(['add', '.']);
            $git(['commit', '-m', 'Release with older manifest default']);
            $sha = $git(['rev-parse', 'HEAD']);
            $git(['tag', 'v1.1.8']);
            $git(['tag', 'v1.1.6']);
            foreach ([null, '1.1.6'] as $version) {
                $options = ['directory' => $root . '/project', '--repository' => $repository,
                    '--dry-run' => true, '--no-setup' => true, '--name' => 'Fixture'];
                if ($version !== null) {
                    $options['--template-version'] = $version;
                }
                $tester = new CommandTester(new CreateProjectCommand());
                self::assertSame(0, $tester->execute($options, ['interactive' => false]), $tester->getDisplay());
                self::assertStringContainsString('sympress/starter:' . ($version ?? '1.1.8'), $tester->getDisplay());
                self::assertStringContainsString($sha, $tester->getDisplay());
                self::assertDirectoryDoesNotExist($root . '/project');
            }
        } finally {
            new Filesystem()->remove($root);
        }
    }
    public function testDryRunUsesManifestProvidedCatalogEntries(): void
    {
        $directory = $this->temporaryDirectory();
        $manifest = $directory . '/sympress-cli.json';
        file_put_contents($manifest, json_encode([
            'schemaVersion' => 1,
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
