<?php

declare(strict_types=1);

namespace SymPress\Cli\Catalog;

use InvalidArgumentException;
use SymPress\Cli\Model\ProjectProfile;

final class DefaultProfileCatalog
{
    /**
     * @var array<string, ProjectProfile>
     */
    private array $profiles;

    /**
     * @param list<ProjectProfile> $profiles
     */
    public function __construct(array $profiles = [])
    {
        $defaultProfiles = [
            new ProjectProfile(
                id: 'website',
                label: 'Website',
                description: 'Public WordPress site with editorial content, SEO, forms, caching and consent basics.',
                exampleName: 'acme-website',
            ),
            new ProjectProfile(
                id: 'app',
                label: 'App / Portal',
                description: 'Authenticated workflows, custom data, background jobs and admin-heavy screens.',
                exampleName: 'customer-portal',
            ),
            new ProjectProfile(
                id: 'microservice',
                label: 'Microservice',
                description: 'Lean SymPress service boundary with events, migrations, mail and operational tooling.',
                exampleName: 'content-sync-service',
            ),
            new ProjectProfile(
                id: 'commerce',
                label: 'Commerce',
                description: 'WooCommerce-oriented project with forms, mail, cache and operational visibility.',
                exampleName: 'shop-platform',
            ),
        ];

        $this->profiles = array_combine(
            array_map(static fn (ProjectProfile $profile): string => $profile->id, $defaultProfiles),
            $defaultProfiles,
        );

        foreach ($profiles as $profile) {
            $this->profiles[$profile->id] = $profile;
        }
    }

    /**
     * @param list<ProjectProfile> $profiles
     */
    public function withProfiles(array $profiles): self
    {
        return new self([...$this->all(), ...$profiles]);
    }

    /**
     * @return list<ProjectProfile>
     */
    public function all(): array
    {
        return array_values($this->profiles);
    }

    public function default(): ProjectProfile
    {
        return $this->profiles['website'];
    }

    public function get(string $id): ProjectProfile
    {
        if (!isset($this->profiles[$id])) {
            throw new InvalidArgumentException(sprintf('Unknown project type "%s".', $id));
        }

        return $this->profiles[$id];
    }
}
