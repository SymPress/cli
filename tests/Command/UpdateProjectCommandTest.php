<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Command;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Command\UpdateProjectCommand;
use SymPress\Cli\Project\ProjectMetadata;
use SymPress\Cli\Project\ProjectMetadataStore;
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

    private function projectDirectory(): string
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
                templateId: 'sympress-starter',
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
