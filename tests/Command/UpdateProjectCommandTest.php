<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Command;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Command\UpdateProjectCommand;
use SymPress\Cli\Generator\CommandRunner;
use SymPress\Cli\Project\ProjectMetadata;
use SymPress\Cli\Project\ProjectMetadataStore;
use SymPress\Cli\Repository\RepositoryManifestLoader;
use SymPress\Cli\Update\ProjectUpdater;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class UpdateProjectCommandTest extends TestCase
{
    public function testMinorDryRunCanChangeProjectType(): void
    {
        $projectDir = $this->projectDirectory();
        $tester = new CommandTester(new UpdateProjectCommand());

        $tester->execute([
            'directory' => $projectDir,
            '--level' => 'minor',
            '--type' => 'app',
            '--dry-run' => true,
            '--no-install' => true,
            '--no-remote-manifest' => true,
        ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('microservice -> app', $output);
        self::assertStringContainsString('sympress/assets', $output);
        self::assertStringContainsString('sympress/orm', $output);
    }

    public function testPatchDryRunRejectsProjectTypeChange(): void
    {
        $projectDir = $this->projectDirectory();
        $tester = new CommandTester(new UpdateProjectCommand());

        $exitCode = $tester->execute([
            'directory' => $projectDir,
            '--level' => 'patch',
            '--type' => 'website',
            '--dry-run' => true,
            '--no-install' => true,
            '--no-remote-manifest' => true,
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('requires --level=minor or --level=major', $tester->getDisplay());
    }

    public function testDryRunKeepsTemplateFromManifestThatIsNotBuiltIn(): void
    {
        $projectDir = $this->projectDirectory('acme-starter');
        $tester = new CommandTester(new UpdateProjectCommand());

        $exitCode = $tester->execute([
            'directory' => $projectDir,
            '--dry-run' => true,
            '--no-remote-manifest' => true,
        ], ['interactive' => false]);

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertStringContainsString('acme-starter -> acme-starter', $tester->getDisplay());
    }

    public function testTrustedUpdateWithUnpinnedRemoteJsonManifestRequiresSha256(): void
    {
        $tester = new CommandTester(new UpdateProjectCommand());

        $exitCode = $tester->execute([
            'directory' => $this->projectDirectory(),
            '--manifest' => 'https://example.test/catalog.json',
            '--allow-template-execution' => true,
            '--no-remote-manifest' => true,
        ], ['interactive' => false]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('--manifest-sha256', $tester->getDisplay());
    }

    public function testManifestSha256MustMatchTheManifest(): void
    {
        $projectDir = $this->projectDirectory();
        file_put_contents($projectDir . '/catalog.json', '{"schemaVersion":1}');
        $tester = new CommandTester(new UpdateProjectCommand());
        $options = ['directory' => $projectDir, '--manifest' => $projectDir . '/catalog.json', '--dry-run' => true,
            '--no-remote-manifest' => true];

        self::assertSame(2, $tester->execute([...$options, '--manifest-sha256' => str_repeat('0', 64)]));
        self::assertStringContainsString('SHA-256', $tester->getDisplay());
        $sha256 = hash('sha256', '{"schemaVersion":1}');
        self::assertSame(0, $tester->execute([...$options, '--manifest-sha256' => $sha256]), $tester->getDisplay());
    }

    public function testComposerUpdateWithNewCatalogRepositoryRequiresTrust(): void
    {
        $projectDir = $this->projectDirectory();
        $manifest = $projectDir . '/catalog.json';
        file_put_contents($manifest, json_encode([
            'schemaVersion' => 1,
            'packageSuggestions' => [[
                'name' => 'acme/remote',
                'label' => 'Remote',
                'description' => 'Package from a catalog VCS repository.',
                'recommendedProfiles' => ['microservice'],
                'repositoryUrl' => 'https://example.test/acme/remote.git',
            ]],
        ], JSON_THROW_ON_ERROR));
        $runner = new class implements CommandRunner {
            /** @var list<list<string>> */
            public array $commands = [];

            public function run(array $command, ?string $cwd, OutputInterface $output): int
            {
                $this->commands[] = $command;

                return 0;
            }
        };
        $tester = new CommandTester(new UpdateProjectCommand(
            new ProjectMetadataStore(),
            new RepositoryManifestLoader(),
            new ProjectUpdater(commandRunner: $runner),
        ));
        $options = ['directory' => $projectDir, '--level' => 'patch', '--manifest' => $manifest,
            '--no-remote-manifest' => true];
        $composerJson = (string) file_get_contents($projectDir . '/composer.json');

        self::assertSame(0, $tester->execute([...$options, '--dry-run' => true]));
        self::assertStringContainsString('https://example.test/acme/remote.git', $tester->getDisplay());

        foreach ([$options, [...$options, '--no-install' => true]] as $untrusted) {
            self::assertSame(2, $tester->execute($untrusted));
            self::assertStringContainsString('--allow-template-execution', $tester->getDisplay());
            self::assertSame([], $runner->commands);
            self::assertSame($composerJson, file_get_contents($projectDir . '/composer.json'));
        }

        self::assertSame(0, $tester->execute([...$options, '--allow-template-execution' => true]));
        self::assertSame('update', $runner->commands[0][1] ?? null);
        $repositories = json_decode((string) file_get_contents($projectDir . '/composer.json'), true)['repositories'];
        self::assertContains(
            ['type' => 'vcs', 'url' => 'https://example.test/acme/remote.git', 'only' => ['acme/remote']],
            $repositories,
        );

        // Built-in catalog repositories (here sympress/nginx-cache) keep the documented flow.
        $runner->commands = [];
        self::assertSame(0, $tester->execute(['directory' => $projectDir, '--level' => 'minor', '--type' => 'website',
            '--no-remote-manifest' => true]), $tester->getDisplay());
        self::assertStringContainsString('https://github.com/SymPress/nginx-cache', $tester->getDisplay());
        self::assertSame('update', $runner->commands[0][1] ?? null);
    }

    private function projectDirectory(string $templateId = 'sympress-starter'): string
    {
        $projectDir = $this->temporaryDirectory();
        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'acme/example-project',
            'require' => [
                'php' => '^8.5',
            ],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        (new ProjectMetadataStore())->write(
            $projectDir,
            new ProjectMetadata(
                projectName: 'Example Project',
                projectSlug: 'example-project',
                composerPackageName: 'acme/example-project',
                ddevTld: 'ddev.site',
                profileId: 'microservice',
                templateId: $templateId,
                templatePackageName: 'sympress/starter',
                templateRepositoryUrl: 'https://github.com/SymPress/starter',
                templateVersion: '1.0.x-dev',
                updatedAt: '2026-06-14T00:00:00+00:00',
            ),
        );

        return $projectDir;
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $directory;
    }
}
