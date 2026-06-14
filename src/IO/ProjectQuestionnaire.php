<?php

declare(strict_types=1);

namespace SymPress\Cli\IO;

use InvalidArgumentException;
use SymPress\Cli\Catalog\DefaultPackageCatalog;
use SymPress\Cli\Catalog\DefaultProfileCatalog;
use SymPress\Cli\Catalog\DefaultTemplateCatalog;
use SymPress\Cli\Model\PackageReference;
use SymPress\Cli\Model\PackageSuggestion;
use SymPress\Cli\Model\ProjectConfiguration;
use SymPress\Cli\Model\ProjectProfile;
use SymPress\Cli\Model\TemplateDefinition;
use SymPress\Cli\Repository\RepositoryManifest;
use SymPress\Cli\Repository\RepositoryManifestLoader;
use SymPress\Cli\Util\ProjectNameNormalizer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ProjectQuestionnaire
{
    public function __construct(
        private DefaultTemplateCatalog $templates,
        private DefaultProfileCatalog $profiles,
        private DefaultPackageCatalog $packages,
        private ProjectNameNormalizer $normalizer,
        private RepositoryManifestLoader $manifestLoader = new RepositoryManifestLoader(),
    ) {
    }

    public function ask(InputInterface $input, OutputInterface $output, string $cwd): ProjectConfiguration
    {
        $io = new SymfonyStyle($input, $output);
        $interactive = $input->isInteractive();

        if ($interactive) {
            $io->title('SymPress CLI');
            $io->text(
                'Create a configured project from a starter template.'
                . ' You can accept the examples and adjust later.',
            );
        }

        $templates = $this->templates;
        $profiles = $this->profiles;
        $packages = $this->packages;
        $explicitManifest = $this->loadExplicitManifest($input);

        if ($explicitManifest !== null) {
            [$templates, $profiles, $packages] = $this->applyManifest(
                $explicitManifest,
                $templates,
                $profiles,
                $packages,
            );
        }

        $template = $this->selectTemplate($input, $io, $interactive, $templates);
        $templateRepository = $this->emptyToNull($input->getOption('repository')) ?: $template->repositoryUrl;
        $remoteManifest = $this->loadRemoteManifest($input, $templateRepository);

        if ($remoteManifest !== null) {
            [$templates, $profiles, $packages] = $this->applyManifest(
                $remoteManifest,
                $templates,
                $profiles,
                $packages,
            );
            $template = $templates->get($template->id);

            if ($this->emptyToNull($input->getOption('repository')) === null) {
                $templateRepository = $template->repositoryUrl;
            }
        }

        $profile = $this->selectProfile($input, $io, $interactive, $profiles);
        $projectName = $this->projectName($input, $io, $interactive, $profile);
        $projectSlug = $this->projectSlug($input, $io, $interactive, $projectName);
        $directory = $this->directory($input, $io, $interactive, $cwd, $projectSlug);
        $composerName = $this->composerPackageName($input, $io, $interactive, $projectSlug);
        $ddevTld = $this->ddevTld($input, $io, $interactive);
        $adminUser = $this->adminUser($input, $io, $interactive);
        $adminPassword = $this->adminPassword($input, $io, $interactive);
        [$runtimePackages, $devPackages] = $this->selectedPackages(
            $input,
            $io,
            $interactive,
            $profile,
            $packages,
        );

        return new ProjectConfiguration(
            template: $template,
            profile: $profile,
            directory: $directory,
            projectName: $projectName,
            projectSlug: $projectSlug,
            composerPackageName: $composerName,
            ddevTld: $ddevTld,
            wpAdminUsername: $adminUser,
            wpAdminPassword: $adminPassword,
            packages: $runtimePackages,
            devPackages: $devPackages,
            runSetup: $this->runSetup($input, $io, $interactive),
            dryRun: (bool) $input->getOption('dry-run'),
            composerBinary: (string) $input->getOption('composer-bin'),
            templateVersion: $this->emptyToNull($input->getOption('template-version')),
            templateRepository: $templateRepository,
        );
    }

    private function loadExplicitManifest(InputInterface $input): ?RepositoryManifest
    {
        $source = $this->emptyToNull($input->getOption('manifest'));

        if ($source === null) {
            return null;
        }

        return $this->manifestLoader->loadRequired($source, $this->manifestRef($input));
    }

    private function loadRemoteManifest(InputInterface $input, string $repositoryUrl): ?RepositoryManifest
    {
        if ((bool) $input->getOption('no-remote-manifest')) {
            return null;
        }

        return $this->manifestLoader->loadFromRepository($repositoryUrl, $this->manifestRef($input));
    }

    /**
     * @return array{0: DefaultTemplateCatalog, 1: DefaultProfileCatalog, 2: DefaultPackageCatalog}
     */
    private function applyManifest(
        RepositoryManifest $manifest,
        DefaultTemplateCatalog $templates,
        DefaultProfileCatalog $profiles,
        DefaultPackageCatalog $packages,
    ): array {
        if ($manifest->isEmpty()) {
            return [$templates, $profiles, $packages];
        }

        return [
            $templates->withTemplates($manifest->templates),
            $profiles->withProfiles($manifest->profiles),
            $packages->withSuggestions($manifest->packageSuggestions),
        ];
    }

    private function manifestRef(InputInterface $input): string
    {
        return $this->emptyToNull($input->getOption('manifest-ref')) ?: 'main';
    }

    private function selectTemplate(
        InputInterface $input,
        SymfonyStyle $io,
        bool $interactive,
        DefaultTemplateCatalog $templates,
    ): TemplateDefinition {
        $templateId = $this->emptyToNull($input->getOption('template'));

        if ($templateId !== null) {
            return $templates->get($templateId);
        }

        if (!$interactive) {
            return $templates->first();
        }

        $templateList = $templates->all();
        $io->table(
            ['Template', 'Description'],
            array_map(
                static fn (TemplateDefinition $template): array => [$template->id, $template->description],
                $templateList,
            ),
        );

        $selected = (string) $io->choice(
            'Starter template',
            array_map(static fn (TemplateDefinition $template): string => $template->id, $templateList),
            $templateList[0]->id,
        );

        return $templates->get($selected);
    }

    private function selectProfile(
        InputInterface $input,
        SymfonyStyle $io,
        bool $interactive,
        DefaultProfileCatalog $profiles,
    ): ProjectProfile {
        $profileId = $this->emptyToNull($input->getOption('type'));

        if ($profileId !== null) {
            return $profiles->get($profileId);
        }

        if (!$interactive) {
            return $profiles->default();
        }

        $io->section('Project type');
        $io->table(
            ['Type', 'Good for', 'Example'],
            array_map(
                static fn (ProjectProfile $profile): array => [
                    $profile->id,
                    $profile->description,
                    $profile->exampleName,
                ],
                $profiles->all(),
            ),
        );

        $selected = (string) $io->choice(
            'What are you building?',
            array_map(static fn (ProjectProfile $profile): string => $profile->id, $profiles->all()),
            $profiles->default()->id,
        );

        return $profiles->get($selected);
    }

    private function projectName(
        InputInterface $input,
        SymfonyStyle $io,
        bool $interactive,
        ProjectProfile $profile,
    ): string {
        $name = $this->emptyToNull($input->getOption('name'));

        if ($name !== null) {
            return $name;
        }

        $directory = $this->emptyToNull($input->getArgument('directory'));

        if (!$interactive) {
            if ($directory !== null) {
                return $this->normalizer->displayNameFromSlug($this->normalizer->slug(basename($directory)));
            }

            return $this->normalizer->displayNameFromSlug($profile->exampleName);
        }

        $io->section('Project identity');
        $io->text(sprintf('Example values: %s, newsroom-platform, customer-api', $profile->exampleName));

        return trim((string) $io->ask(
            'Project display name',
            $this->normalizer->displayNameFromSlug($profile->exampleName),
        ));
    }

    private function projectSlug(
        InputInterface $input,
        SymfonyStyle $io,
        bool $interactive,
        string $projectName,
    ): string {
        $slug = $this->emptyToNull($input->getOption('project-slug'));

        if ($slug !== null) {
            return $this->normalizer->slug($slug);
        }

        $default = $this->normalizer->slug($projectName);

        if (!$interactive) {
            return $default;
        }

        return $this->normalizer->slug((string) $io->ask('Project slug / DDEV name', $default));
    }

    private function directory(
        InputInterface $input,
        SymfonyStyle $io,
        bool $interactive,
        string $cwd,
        string $projectSlug,
    ): string {
        $directory = $this->emptyToNull($input->getArgument('directory'));

        if ($directory === null && $interactive) {
            $directory = (string) $io->ask('Target directory', './' . $projectSlug);
        }

        $directory ??= './' . $projectSlug;

        if ($this->isAbsolutePath($directory)) {
            return rtrim($directory, '/');
        }

        return rtrim($cwd, '/') . '/' . trim($directory, '/');
    }

    private function composerPackageName(
        InputInterface $input,
        SymfonyStyle $io,
        bool $interactive,
        string $projectSlug,
    ): string {
        $packageName = $this->emptyToNull($input->getOption('package-name'));

        if ($packageName !== null) {
            return $this->normalizer->composerName($projectSlug, $packageName);
        }

        $default = $this->normalizer->composerName($projectSlug);

        if (!$interactive) {
            return $default;
        }

        $io->text('Composer package example: acme/customer-portal');

        return $this->normalizer->composerName(
            $projectSlug,
            (string) $io->ask('Composer package name', $default),
        );
    }

    private function ddevTld(InputInterface $input, SymfonyStyle $io, bool $interactive): string
    {
        $tld = $this->emptyToNull($input->getOption('ddev-tld')) ?: 'ddev.site';

        if (!$interactive || $input->getOption('ddev-tld') !== null) {
            return $tld;
        }

        $io->section('Local environment');
        $io->text('Example URL with the default: https://my-project.ddev.site');

        return trim((string) $io->ask('DDEV project TLD', $tld));
    }

    private function adminUser(InputInterface $input, SymfonyStyle $io, bool $interactive): string
    {
        $adminUser = $this->emptyToNull($input->getOption('admin-user')) ?: 'admin';

        if (!$interactive || $input->getOption('admin-user') !== null) {
            return $adminUser;
        }

        return trim((string) $io->ask('WordPress admin username', $adminUser));
    }

    private function adminPassword(InputInterface $input, SymfonyStyle $io, bool $interactive): string
    {
        $password = $this->emptyToNull($input->getOption('admin-password'));

        if ($password !== null) {
            return $password;
        }

        if (!$interactive || $io->confirm('Generate a secure WordPress admin password?', true)) {
            return bin2hex(random_bytes(16));
        }

        return (string) $io->askHidden(
            'WordPress admin password',
            static function (string $value): string {
                if (strlen($value) < 8) {
                    throw new InvalidArgumentException('Use at least 8 characters.');
                }

                return $value;
            },
        );
    }

    private function runSetup(InputInterface $input, SymfonyStyle $io, bool $interactive): bool
    {
        if ((bool) $input->getOption('no-setup')) {
            return false;
        }

        if (!$interactive) {
            return true;
        }

        $io->section('Setup');
        $io->text('The starter setup configures DDEV, writes WordPress URLs and runs Composer install.');

        return $io->confirm('Run starter setup after creating the project?', true);
    }

    /**
     * @return array{0: list<PackageReference>, 1: list<PackageReference>}
     */
    private function selectedPackages(
        InputInterface $input,
        SymfonyStyle $io,
        bool $interactive,
        ProjectProfile $profile,
        DefaultPackageCatalog $packages,
    ): array {
        if ($interactive && !(bool) $input->getOption('no-suggested-packages')) {
            return [
                $this->mergePackageReferences(
                    $this->interactivePackageSelection($io, $profile, false, 'Runtime package suggestions', $packages),
                    $this->referencesFromOption($input, 'package', false, $packages),
                ),
                $this->mergePackageReferences(
                    $this->interactivePackageSelection(
                        $io,
                        $profile,
                        true,
                        'Development package suggestions',
                        $packages,
                    ),
                    $this->referencesFromOption($input, 'dev-package', true, $packages),
                ),
            ];
        }

        if ((bool) $input->getOption('no-suggested-packages')) {
            $runtime = [];
            $dev = [];
        } else {
            $runtime = array_map(
                static fn (PackageSuggestion $suggestion): PackageReference => $suggestion->toReference(),
                $packages->recommendedForProfile($profile->id, false),
            );
            $dev = array_map(
                static fn (PackageSuggestion $suggestion): PackageReference => $suggestion->toReference(),
                $packages->recommendedForProfile($profile->id, true),
            );
        }

        return [
            $this->mergePackageReferences($runtime, $this->referencesFromOption($input, 'package', false, $packages)),
            $this->mergePackageReferences($dev, $this->referencesFromOption($input, 'dev-package', true, $packages)),
        ];
    }

    /**
     * @return list<PackageReference>
     */
    private function interactivePackageSelection(
        SymfonyStyle $io,
        ProjectProfile $profile,
        bool $dev,
        string $title,
        DefaultPackageCatalog $packages,
    ): array {
        $suggestions = $packages->forProfile($profile->id, $dev);

        if ($suggestions === []) {
            return [];
        }

        $io->section($title);
        $io->table(
            ['Package', 'Recommended', 'Why'],
            array_map(
                static fn (PackageSuggestion $suggestion): array => [
                    $suggestion->name,
                    $suggestion->isRecommendedFor($profile->id) ? 'yes' : 'optional',
                    $suggestion->description,
                ],
                $suggestions,
            ),
        );

        $choices = [
            'none',
            ...array_map(
                static fn (PackageSuggestion $suggestion): string => $suggestion->name,
                $suggestions,
            ),
        ];
        $defaults = array_values(array_map(
            static fn (PackageSuggestion $suggestion): string => $suggestion->name,
            array_filter(
                $suggestions,
                static fn (PackageSuggestion $suggestion): bool => $suggestion->isRecommendedFor($profile->id),
            ),
        ));

        $selected = $io->choice(
            'Select packages to add to composer.json',
            $choices,
            $defaults === [] ? 'none' : implode(',', $defaults),
            true,
        );

        $manual = trim((string) $io->ask(
            'Additional Composer packages, comma separated',
            '',
        ));

        return $this->mergePackageReferences(
            $this->referencesForNames($this->withoutNone((array) $selected), $dev, $packages),
            $this->referencesForNames($this->csv($manual), $dev, $packages),
        );
    }

    /**
     * @return list<PackageReference>
     */
    private function referencesFromOption(
        InputInterface $input,
        string $option,
        bool $dev,
        DefaultPackageCatalog $packages,
    ): array {
        $value = $input->getOption($option);

        if (!is_array($value)) {
            return [];
        }

        return $this->referencesForNames($value, $dev, $packages);
    }

    /**
     * @param list<string> $packageNames
     * @return list<PackageReference>
     */
    private function referencesForNames(array $packageNames, bool $dev, DefaultPackageCatalog $packages): array
    {
        return array_values(array_map(
            fn (string $package): PackageReference => $packages->referenceFor($package, $dev),
            array_filter($packageNames, static fn (string $package): bool => trim($package) !== ''),
        ));
    }

    /**
     * @param list<PackageReference> $base
     * @param list<PackageReference> $additional
     * @return list<PackageReference>
     */
    private function mergePackageReferences(array $base, array $additional): array
    {
        $merged = [];

        foreach ([...$base, ...$additional] as $reference) {
            $merged[$reference->name] = $reference;
        }

        return array_values($merged);
    }

    /**
     * @return list<string>
     */
    private function csv(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function withoutNone(array $values): array
    {
        return array_values(array_filter($values, static fn (string $value): bool => $value !== 'none'));
    }

    private function emptyToNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }
}
