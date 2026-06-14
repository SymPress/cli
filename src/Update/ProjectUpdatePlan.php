<?php

declare(strict_types=1);

namespace SymPress\Cli\Update;

use SymPress\Cli\Model\PackageReference;
use SymPress\Cli\Model\ProjectProfile;
use SymPress\Cli\Model\TemplateDefinition;
use SymPress\Cli\Project\ProjectMetadata;

final readonly class ProjectUpdatePlan
{
    /**
     * @param list<PackageReference> $packages
     * @param list<PackageReference> $devPackages
     * @param list<string> $composerUpdatePackages
     */
    public function __construct(
        public string $level,
        public string $projectDir,
        public ProjectMetadata $currentMetadata,
        public ProjectMetadata $nextMetadata,
        public TemplateDefinition $template,
        public ProjectProfile $profile,
        public array $packages,
        public array $devPackages,
        public array $composerUpdatePackages = [],
    ) {
    }

    public function changesType(): bool
    {
        return $this->currentMetadata->profileId !== $this->nextMetadata->profileId;
    }

    public function changesTemplate(): bool
    {
        return $this->currentMetadata->templateId !== $this->nextMetadata->templateId
            || $this->currentMetadata->templatePackageName !== $this->nextMetadata->templatePackageName
            || $this->currentMetadata->templateRepositoryUrl !== $this->nextMetadata->templateRepositoryUrl;
    }
}
