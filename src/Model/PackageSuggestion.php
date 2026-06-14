<?php

declare(strict_types=1);

namespace SymPress\Cli\Model;

final readonly class PackageSuggestion
{
    /**
     * @param list<string> $recommendedProfiles
     * @param list<string> $optionalProfiles
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $description,
        public string $constraint = '*',
        public bool $dev = false,
        public array $recommendedProfiles = [],
        public array $optionalProfiles = [],
        public ?string $repositoryUrl = null,
    ) {
    }

    public function isRelevantFor(string $profileId): bool
    {
        return $this->isRecommendedFor($profileId) || in_array($profileId, $this->optionalProfiles, true);
    }

    public function isRecommendedFor(string $profileId): bool
    {
        return in_array($profileId, $this->recommendedProfiles, true);
    }

    public function toReference(): PackageReference
    {
        return new PackageReference($this->name, $this->constraint, $this->repositoryUrl);
    }
}
