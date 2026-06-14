<?php

declare(strict_types=1);

namespace SymPress\Cli\Model;

final readonly class ProjectConfiguration
{
    /**
     * @param list<PackageReference> $packages
     * @param list<PackageReference> $devPackages
     */
    public function __construct(
        public TemplateDefinition $template,
        public ProjectProfile $profile,
        public string $directory,
        public string $projectName,
        public string $projectSlug,
        public string $composerPackageName,
        public string $ddevTld,
        public string $wpAdminUsername,
        public string $wpAdminPassword,
        public array $packages = [],
        public array $devPackages = [],
        public bool $runSetup = false,
        public bool $dryRun = false,
        public string $composerBinary = 'composer',
        public ?string $templateVersion = null,
        public ?string $templateRepository = null,
    ) {
    }

    public function wpHome(): string
    {
        return sprintf('https://%s.%s', $this->projectSlug, $this->ddevTld);
    }
}
