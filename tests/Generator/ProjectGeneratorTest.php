<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Generator;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Catalog\DefaultProfileCatalog;
use SymPress\Cli\Catalog\DefaultTemplateCatalog;
use SymPress\Cli\Generator\CommandRunner;
use SymPress\Cli\Generator\ProjectGenerator;
use SymPress\Cli\Model\PackageReference;
use SymPress\Cli\Model\ProjectConfiguration;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class ProjectGeneratorTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporaryDirectories);
    }

    public function testRemovesCopiedLockWhenRequirementsChangeWithoutSetup(): void
    {
        $projectDir = $this->temporaryProjectPath();
        $runner = new FixtureProjectCommandRunner($this->starterComposer());
        $configuration = $this->configuration(
            $projectDir,
            packages: [new PackageReference('sympress/assets')],
        );

        self::assertSame(0, (new ProjectGenerator($runner))->generate($configuration, $this->style()));
        self::assertFileDoesNotExist($projectDir . '/composer.lock');
    }

    public function testRemovesCopiedLockBeforeSetupRuns(): void
    {
        $projectDir = $this->temporaryProjectPath();
        $runner = new FixtureProjectCommandRunner($this->starterComposer());
        $configuration = $this->configuration(
            $projectDir,
            packages: [new PackageReference('sympress/assets')],
            runSetup: true,
        );

        self::assertSame(0, (new ProjectGenerator($runner))->generate($configuration, $this->style()));
        self::assertFalse($runner->lockPresentDuringSetup);
    }

    public function testPreservesCopiedLockWhenComposerJsonIsUnchanged(): void
    {
        $projectDir = $this->temporaryProjectPath();
        $runner = new FixtureProjectCommandRunner($this->starterComposer());
        $configuration = $this->configuration($projectDir);

        self::assertSame(0, (new ProjectGenerator($runner))->generate($configuration, $this->style()));
        self::assertFileExists($projectDir . '/composer.lock');
    }

    public function testSetupRequiresExplicitTrustBeforeAnyCommandOrProjectMutation(): void
    {
        $directory = $this->temporaryProjectPath();
        $runner = new FixtureProjectCommandRunner($this->starterComposer());
        try {
            (new ProjectGenerator($runner))->generate(
                $this->configuration($directory, runSetup: true, allowExecution: false),
                $this->style(),
            );
            self::fail('Untrusted setup accepted.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('--allow-template-execution', $exception->getMessage());
        }
        self::assertSame(0, $runner->calls);
        self::assertDirectoryDoesNotExist($directory);
    }

    public function testTemplateSymlinksCannotRedirectWritesOutsideTheProject(): void
    {
        $projectDir = $this->temporaryProjectPath();
        $root = dirname($projectDir);
        $repository = $root . '/template';
        $victim = $root . '/victim';
        mkdir($repository);
        $git = static function (array $arguments) use ($repository): string {
            $process = new Process(['git', '-C', $repository, ...$arguments]);
            $process->setEnv(['GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_INDEX_FILE' => false]);
            $process->mustRun();

            return trim($process->getOutput());
        };
        $git(['init', '-b', 'main']);
        $git(['config', 'user.name', 'Review fixture']);
        $git(['config', 'user.email', 'fixture@example.test']);
        file_put_contents($repository . '/composer.json', json_encode($this->starterComposer(), JSON_THROW_ON_ERROR));
        file_put_contents($repository . '/.env.example', "INJECTED=payload\n");
        self::assertTrue(symlink('../victim', $repository . '/.env'));
        $git(['add', '.']);
        $git(['commit', '-m', 'Malicious template']);

        $configuration = $this->configuration(
            $projectDir,
            templateRepository: $repository,
            templateRevision: $git(['rev-parse', 'HEAD']),
        );

        self::assertSame(0, (new ProjectGenerator())->generate($configuration, $this->style()));
        self::assertFileDoesNotExist($victim);
        self::assertFalse(is_link($projectDir . '/.env'));
    }

    public function testFailedCreationRemovesTheHalfCreatedProject(): void
    {
        $runner = new class implements CommandRunner {
            public function run(array $command, ?string $cwd, OutputInterface $output): int
            {
                if ($cwd === null) {
                    $projectDir = $command[count($command) - 1];
                    is_dir($projectDir) || mkdir($projectDir, 0777, true);
                    file_put_contents($projectDir . '/composer.json', '{broken');
                    file_put_contents($projectDir . '/.env', 'WP_ADMIN_PASSWORD=secret');
                }

                return 0;
            }
        };
        $created = $this->temporaryProjectPath();
        $existing = $this->temporaryProjectPath();
        mkdir($existing);

        foreach ([$created, $existing] as $directory) {
            try {
                (new ProjectGenerator($runner))->generate($this->configuration($directory), $this->style());
                self::fail('Broken composer.json accepted.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Unable to parse', $exception->getMessage());
            }
        }

        self::assertFileDoesNotExist($created);
        self::assertSame(['.', '..'], scandir($existing));
    }

    /** @return array<string, mixed> */
    private function starterComposer(): array
    {
        return [
            'name'        => 'acme/customer-portal',
            'description' => 'Customer Portal project generated by SymPress CLI.',
            'require'     => ['php' => '^8.5'],
        ];
    }

    /**
     * @param list<PackageReference> $packages
     */
    private function configuration(
        string $projectDir,
        array $packages = [],
        bool $runSetup = false,
        bool $allowExecution = true,
        ?string $templateRepository = null,
        ?string $templateRevision = null,
    ): ProjectConfiguration {
        return new ProjectConfiguration(
            template: (new DefaultTemplateCatalog())->first(),
            profile: (new DefaultProfileCatalog())->default(),
            directory: $projectDir,
            projectName: 'Customer Portal',
            projectSlug: 'customer-portal',
            composerPackageName: 'acme/customer-portal',
            ddevTld: 'ddev.site',
            wpAdminUsername: 'admin',
            wpAdminPassword: 'secret-secret',
            packages: $packages,
            runSetup: $runSetup,
            allowTemplateExecution: $allowExecution,
            templateRepository: $templateRepository,
            templateRevision: $templateRevision ?? str_repeat('a', 40),
        );
    }

    private function temporaryProjectPath(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory, 0777, true));
        $this->temporaryDirectories[] = $directory;

        return $directory . '/project';
    }

    private function style(): SymfonyStyle
    {
        return new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
    }
}
