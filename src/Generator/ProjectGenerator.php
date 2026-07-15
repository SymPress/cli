<?php

declare(strict_types=1);

namespace SymPress\Cli\Generator;

use RuntimeException;
use SymPress\Cli\Model\PackageReference;
use SymPress\Cli\Model\ProjectConfiguration;
use SymPress\Cli\Project\ProjectMetadata;
use SymPress\Cli\Project\ProjectMetadataStore;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

final readonly class ProjectGenerator
{
    public function __construct(
        private CommandRunner $commandRunner = new SymfonyProcessCommandRunner(),
        private ComposerJsonEditor $composerJsonEditor = new ComposerJsonEditor(),
        private EnvFileEditor $envFileEditor = new EnvFileEditor(),
        private ProjectMetadataStore $metadataStore = new ProjectMetadataStore(),
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function generate(ProjectConfiguration $configuration, SymfonyStyle $io): int
    {
        if ($configuration->dryRun) {
            $this->renderPlan($configuration, $io);

            return 0;
        }

        $this->assertTargetDirectoryIsAvailable($configuration->directory);
        $this->filesystem->mkdir(dirname($configuration->directory));

        $io->section('Create starter project');
        $exitCode = $this->commandRunner->run($this->createProjectCommand($configuration), null, $io);

        if ($exitCode !== 0) {
            return $exitCode;
        }

        $io->section('Apply initial configuration');
        $composerChanged = $this->composerJsonEditor->apply($configuration->directory, $configuration);
        if ($composerChanged) {
            $this->filesystem->remove($configuration->directory . '/composer.lock');
        }

        $this->envFileEditor->apply($configuration->directory, $configuration);
        $this->metadataStore->write(
            $configuration->directory,
            ProjectMetadata::fromConfiguration($configuration),
        );

        if ($configuration->runSetup) {
            $io->section('Run starter setup');
            $exitCode = $this->commandRunner->run(
                $configuration->template->setupCommandFor($configuration),
                $configuration->directory,
                $io,
            );

            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        $io->success(sprintf('Project "%s" is ready in %s', $configuration->projectName, $configuration->directory));
        $io->definitionList(
            ['Project URL' => $configuration->wpHome()],
            ['Admin user' => $configuration->wpAdminUsername],
            ['Admin password' => $configuration->wpAdminPassword],
        );

        if (!$configuration->runSetup) {
            $io->note([
                'Setup was skipped. Run these commands when you are ready:',
                sprintf('cd %s', $configuration->directory),
                sprintf('bin/console setup %s', $configuration->projectSlug),
            ]);
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private function createProjectCommand(ProjectConfiguration $configuration): array
    {
        $command = [
            $configuration->composerBinary,
            'create-project',
            $configuration->template->packageSpec($configuration->templateVersion),
            $configuration->directory,
            '--no-install',
        ];

        if ($configuration->templateRepository !== null && $configuration->templateRepository !== '') {
            $command[] = '--repository=' . json_encode(
                [
                    'type' => 'vcs',
                    'url' => $configuration->templateRepository,
                ],
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        }

        return $command;
    }

    private function renderPlan(ProjectConfiguration $configuration, SymfonyStyle $io): void
    {
        $io->title('SymPress CLI dry run');
        $io->definitionList(
            ['Template' => $configuration->template->label],
            ['Project type' => $configuration->profile->label],
            ['Directory' => $configuration->directory],
            ['Composer name' => $configuration->composerPackageName],
            ['URL' => $configuration->wpHome()],
            ['Run setup' => $configuration->runSetup ? 'yes' : 'no'],
        );
        $io->listing([
            $this->formatCommand($this->createProjectCommand($configuration)),
            'patch composer.json',
            'write .env',
            'write ' . ProjectMetadataStore::RELATIVE_PATH,
            $configuration->runSetup
                ? $this->formatCommand($configuration->template->setupCommandFor($configuration))
                : 'skip starter setup',
        ]);

        $this->renderPackages('Runtime packages', $configuration->packages, $io);
        $this->renderPackages('Development packages', $configuration->devPackages, $io);
    }

    /**
     * @param list<PackageReference> $packages
     */
    private function renderPackages(string $title, array $packages, SymfonyStyle $io): void
    {
        if ($packages === []) {
            return;
        }

        $io->section($title);
        $io->listing(array_map(
            static fn (PackageReference $package): string => $package->name . ':' . $package->constraint,
            $packages,
        ));
    }

    /**
     * @param list<string> $command
     */
    private function formatCommand(array $command): string
    {
        return implode(' ', array_map(
            static fn (string $part): string => str_contains($part, ' ') ? escapeshellarg($part) : $part,
            $command,
        ));
    }

    private function assertTargetDirectoryIsAvailable(string $directory): void
    {
        if (!file_exists($directory)) {
            return;
        }

        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('Target path "%s" exists and is not a directory.', $directory));
        }

        $files = scandir($directory);

        if ($files === false) {
            throw new RuntimeException(sprintf('Unable to inspect target directory "%s".', $directory));
        }

        if (array_values(array_diff($files, ['.', '..'])) !== []) {
            throw new RuntimeException(sprintf('Target directory "%s" is not empty.', $directory));
        }
    }
}
