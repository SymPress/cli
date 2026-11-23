<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Command;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Catalog\DefaultPackageCatalog;
use SymPress\Cli\Catalog\DefaultProfileCatalog;
use SymPress\Cli\Catalog\DefaultTemplateCatalog;
use SymPress\Cli\Command\CreateProjectCommand;
use SymPress\Cli\IO\ProjectQuestionnaire;
use SymPress\Cli\Model\TemplateDefinition;
use SymPress\Cli\Util\ProjectNameNormalizer;
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
            $git(['tag', 'v1.2.5']);
            $git(['tag', 'v1.1.6']);
            foreach ([null, '1.1.6'] as $version) {
                $options = ['directory' => $root . '/project', '--repository' => $repository,
                    '--dry-run' => true, '--no-setup' => true, '--name' => 'Fixture'];
                if ($version !== null) {
                    $options['--template-version'] = $version;
                }
                $tester = new CommandTester(new CreateProjectCommand());
                self::assertSame(0, $tester->execute($options, ['interactive' => false]), $tester->getDisplay());
                self::assertStringContainsString('sympress/starter:' . ($version ?? '1.2.5'), $tester->getDisplay());
                self::assertStringContainsString($sha, $tester->getDisplay());
                self::assertDirectoryDoesNotExist($root . '/project');
            }
        } finally {
            new Filesystem()->remove($root);
        }
    }
    public function testDefaultStarterIsPinnedToReleaseCommit(): void
    {
        $starter = new DefaultTemplateCatalog()->first();

        self::assertSame('1.2.5', $starter->defaultVersion);
        self::assertSame('b403cc00866a5fcb189dadd2b211f4cb198a5e78', $starter->defaultRevision);
    }

    public function testDefaultVersionMustResolveToPinnedRevision(): void
    {
        $root = $this->temporaryDirectory();
        $repository = $root . '/repository';
        mkdir($repository);
        $git = static function (array $arguments) use ($repository): string {
            $process = new Process(['git', '-C', $repository, ...$arguments]);
            $process->setEnv(['GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_INDEX_FILE' => false]);
            $process->mustRun();
            return trim($process->getOutput());
        };
        try {
            $git(['init', '-b', 'main']);
            $git(['-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test',
                'commit', '--allow-empty', '-m', 'Release']);
            $git(['tag', 'v1.2.5']);
            $sha = $git(['rev-parse', 'HEAD']);
            $options = ['directory' => $root . '/project', '--dry-run' => true, '--no-setup' => true,
                '--name' => 'Fixture'];

            $tester = $this->pinnedCommand($repository, str_repeat('a', 40));
            self::assertSame(2, $tester->execute($options, ['interactive' => false]));
            self::assertStringContainsString('pinned commit', $tester->getDisplay());

            $tester = $this->pinnedCommand($repository, $sha);
            self::assertSame(0, $tester->execute($options, ['interactive' => false]), $tester->getDisplay());
            self::assertStringContainsString($sha, $tester->getDisplay());

            // Without a remote lookup the pinned commit is used instead of an unchecked tag.
            $tester = $this->pinnedCommand($repository, str_repeat('b', 40));
            $options['--no-remote-manifest'] = true;
            self::assertSame(0, $tester->execute($options, ['interactive' => false]), $tester->getDisplay());
            self::assertStringContainsString(str_repeat('b', 40), $tester->getDisplay());
            self::assertDirectoryDoesNotExist($root . '/project');
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

    public function testSetupWithUnpinnedRemoteJsonManifestRequiresSha256(): void
    {
        $directory = $this->temporaryDirectory();
        $tester = new CommandTester(new CreateProjectCommand());

        $exitCode = $tester->execute([
            'directory' => $directory . '/project',
            '--manifest' => 'https://example.test/catalog.json',
            '--allow-template-execution' => true,
            '--name' => 'Pinned',
        ], ['interactive' => false]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('--manifest-sha256', $tester->getDisplay());
        self::assertDirectoryDoesNotExist($directory . '/project');
        new Filesystem()->remove($directory);
    }

    private function pinnedCommand(string $repository, string $revision): CommandTester
    {
        $template = new TemplateDefinition(
            id: 'sympress-starter',
            label: 'Starter',
            packageName: 'sympress/starter',
            repositoryUrl: $repository,
            description: 'Fixture',
            defaultRevision: $revision,
        );

        return new CommandTester(new CreateProjectCommand(new ProjectQuestionnaire(
            new DefaultTemplateCatalog([$template]),
            new DefaultProfileCatalog(),
            new DefaultPackageCatalog(),
            new ProjectNameNormalizer(),
        )));
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $directory;
    }
}
