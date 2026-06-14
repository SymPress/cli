<?php

declare(strict_types=1);

namespace SymPress\Cli\Repository;

use SymPress\Cli\Model\PackageSuggestion;
use SymPress\Cli\Model\ProjectProfile;
use SymPress\Cli\Model\TemplateDefinition;

final readonly class RepositoryManifest
{
    /**
     * @param list<TemplateDefinition> $templates
     * @param list<ProjectProfile> $profiles
     * @param list<PackageSuggestion> $packageSuggestions
     */
    public function __construct(
        public array $templates = [],
        public array $profiles = [],
        public array $packageSuggestions = [],
        public ?string $source = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->templates === []
            && $this->profiles === []
            && $this->packageSuggestions === [];
    }
}
