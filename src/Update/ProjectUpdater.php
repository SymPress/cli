<?php

declare(strict_types=1);

namespace SymPress\Cli\Update;

use RuntimeException;
use SymPress\Cli\Catalog\DefaultPackageCatalog;
use SymPress\Cli\Catalog\DefaultProfileCatalog;
use SymPress\Cli\Catalog\DefaultTemplateCatalog;
use SymPress\Cli\Generator\CommandRunner;
use SymPress\Cli\Generator\ComposerJsonEditor;
use SymPress\Cli\Generator\SymfonyProcessCommandRunner;
use SymPress\Cli\Model\PackageReference;
use SymPress\Cli\Model\PackageSuggestion;
use SymPress\Cli\Project\ProjectMetadata;
use SymPress\Cli\Project\ProjectMetadataStore;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ProjectUpdater
{
    private const LEVELS = ['patch', 'minor', 'major'];

    public function __construct(
        private ComposerJsonEditor $composerJsonEditor = new ComposerJsonEditor(),
        private ProjectMetadataStore $metadataStore = new ProjectMetadataStore(),
        private CommandRunner $commandRunner = new SymfonyProcessCommandRunner(),
    ) {
    }

    public function plan(
        string $projectDir,
        string $level,
        ProjectMetadata $metadata,
        DefaultTemplateCatalog $templates,
        DefaultProfileCatalog $profiles,
        DefaultPackageCatalog $packages,
        ?string $targetType = null,
        ?string $targetTemplate = null,
        ?string $templateRepository = null,
        ?string $templateVersion = null,
    ): ProjectUpdatePlan {
        $level = $this->normalizeLevel($level);
        $targetType ??= $metadata->profileId;
        $targetTemplate ??= $metadata->templateId;

        if ($level === 'patch' && $targetType !== $metadata->profileId) {
            throw new RuntimeException('Changing project type requires --level=minor or --level=major.');
        }

        if ($level !== 'major' && $targetTemplate !== $metadata->templateId) {
            throw new RuntimeException('Changing starter template requires --level=major.');
        }

        if ($level !== 'major' && $templateRepository !== null && $templateRepository !== '') {
            throw new RuntimeException('Changing starter repository requires --level=major.');
        }

        $profile = $profiles->get($targetType);
        $template = $templates->get($targetTemplate);
        $nextTemplateRepository = $templateRepository ?: (
            $targetTemplate !== $metadata->templateId ? $template->repositoryUrl : $metadata->templateRepositoryUrl
        );
        $nextTemplateVersion = $templateVersion ?: (
            $targetTemplate !== $metadata->templateId ? $template->defaultVersion : $metadata->templateVersion
        );
        $nextMetadata = $metadata->withUpdate(
            $profile,
            $template,
            $nextTemplateRepository,
            $nextTemplateVersion,
        );
        $runtimePackages = $this->references($packages->recommendedForProfile($profile->id, false));
        $devPackages = $this->references($packages->recommendedForProfile($profile->id, true));

        return new ProjectUpdatePlan(
            level: $level,
            projectDir: $projectDir,
            currentMetadata: $metadata,
            nextMetadata: $nextMetadata,
            template: $template,
            profile: $profile,
            packages: $runtimePackages,
            devPackages: $devPackages,
        );
    }

    public function apply(
        ProjectUpdatePlan $plan,
        SymfonyStyle $io,
        bool $dryRun,
        bool $runComposer,
        string $composerBinary,
    ): int {
        if ($dryRun) {
            $this->renderPlan(
                $plan,
                $io,
                $this->composerJsonEditor->missingPackageReferences(
                    $plan->projectDir,
                    $plan->packages,
                    $plan->devPackages,
                ),
                $runComposer,
                $composerBinary,
            );

            return 0;
        }

        $changedPackages = $this->composerJsonEditor->mergePackageReferences(
            $plan->projectDir,
            $plan->packages,
            $plan->devPackages,
        );
        $this->metadataStore->write($plan->projectDir, $plan->nextMetadata);
        $this->renderPlan($plan, $io, $changedPackages, $runComposer, $composerBinary);

        if (!$runComposer || $changedPackages === []) {
            return 0;
        }

        return $this->commandRunner->run(
            [
                $composerBinary,
                'update',
                ...$changedPackages,
                '--with-all-dependencies',
            ],
            $plan->projectDir,
            $io,
        );
    }

    /**
     * @param list<string> $changedPackages
     */
    private function renderPlan(
        ProjectUpdatePlan $plan,
        SymfonyStyle $io,
        array $changedPackages,
        bool $runComposer,
        string $composerBinary,
    ): void {
        $io->title('SymPress project update');
        $io->definitionList(
            ['Level' => $plan->level],
            ['Project' => $plan->currentMetadata->projectName],
            ['Directory' => $plan->projectDir],
            ['Type' => $plan->currentMetadata->profileId . ' -> ' . $plan->nextMetadata->profileId],
            ['Template' => $plan->currentMetadata->templateId . ' -> ' . $plan->nextMetadata->templateId],
        );

        if ($plan->packages !== []) {
            $io->section('Runtime recommendations');
            $io->listing(array_map(
                static fn (PackageReference $package): string => $package->name . ':' . $package->constraint,
                $plan->packages,
            ));
        }

        if ($plan->devPackages !== []) {
            $io->section('Development recommendations');
            $io->listing(array_map(
                static fn (PackageReference $package): string => $package->name . ':' . $package->constraint,
                $plan->devPackages,
            ));
        }

        if ($changedPackages === []) {
            $io->note('No new Composer packages need to be added.');

            return;
        }

        $io->section('Composer changes');
        $io->listing($changedPackages);

        if ($runComposer) {
            $io->text($composerBinary . ' update ' . implode(' ', $changedPackages) . ' --with-all-dependencies');
        }
    }

    private function normalizeLevel(string $level): string
    {
        $level = strtolower(trim($level));

        if (!in_array($level, self::LEVELS, true)) {
            throw new RuntimeException('Update level must be one of: patch, minor, major.');
        }

        return $level;
    }

    /**
     * @param list<PackageSuggestion> $suggestions
     *
     * @return list<PackageReference>
     */
    private function references(array $suggestions): array
    {
        return array_map(
            static fn (PackageSuggestion $suggestion): PackageReference => $suggestion->toReference(),
            $suggestions,
        );
    }
}
