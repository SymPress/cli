<?php

declare(strict_types=1);

namespace SymPress\Cli\Catalog;

use SymPress\Cli\Model\PackageReference;
use SymPress\Cli\Model\PackageSuggestion;

final class DefaultPackageCatalog
{
    /**
     * @var array<string, PackageSuggestion>
     */
    private array $suggestions;

    /**
     * @param list<PackageSuggestion> $suggestions
     */
    public function __construct(array $suggestions = [])
    {
        $defaultSuggestions = [
            new PackageSuggestion(
                name: 'sympress/assets',
                label: 'Assets',
                description: 'Asset registration and output filtering for themes, plugins and app screens.',
                recommendedProfiles: ['website', 'app', 'commerce'],
                optionalProfiles: ['microservice'],
            ),
            new PackageSuggestion(
                name: 'sympress/asset-compiler',
                label: 'Asset Compiler',
                description: 'Composer-driven frontend builds for package workspaces.',
                recommendedProfiles: ['website', 'app', 'commerce'],
            ),
            new PackageSuggestion(
                name: 'sympress/event-dispatcher',
                label: 'Event Dispatcher',
                description: 'PSR/Symfony event boundaries for app and service workflows.',
                recommendedProfiles: ['app', 'microservice', 'commerce'],
                optionalProfiles: ['website'],
            ),
            new PackageSuggestion(
                name: 'sympress/mailer',
                label: 'Mailer',
                description: 'Mail transport and templating integration for transactional messages.',
                constraint: 'dev-main',
                recommendedProfiles: ['app', 'microservice', 'commerce'],
                optionalProfiles: ['website'],
                repositoryUrl: 'https://github.com/SymPress/mailer',
            ),
            new PackageSuggestion(
                name: 'sympress/migration',
                label: 'Migrations',
                description: 'Repeatable database/schema changes for custom application data.',
                recommendedProfiles: ['app', 'microservice', 'commerce'],
            ),
            new PackageSuggestion(
                name: 'sympress/nginx-cache',
                label: 'Nginx Cache',
                description: 'Cache-aware WordPress integration for nginx-backed environments.',
                constraint: 'dev-main',
                recommendedProfiles: ['website', 'commerce'],
                repositoryUrl: 'https://github.com/SymPress/nginx-cache',
            ),
            new PackageSuggestion(
                name: 'sympress/orm',
                label: 'ORM',
                description: 'Entity mapping and repositories for custom WordPress-backed data models.',
                recommendedProfiles: ['app', 'microservice', 'commerce'],
                optionalProfiles: ['website'],
            ),
            new PackageSuggestion(
                name: 'sympress/profiler',
                label: 'Profiler',
                description: 'Development profiler for inspecting runtime, services and requests.',
                dev: true,
                recommendedProfiles: ['website', 'app', 'microservice', 'commerce'],
            ),
            new PackageSuggestion(
                name: 'symfony/var-dumper',
                label: 'VarDumper',
                description: 'Debug dumps with Symfony-friendly formatting.',
                dev: true,
                recommendedProfiles: ['website', 'app', 'microservice', 'commerce'],
            ),
            new PackageSuggestion(
                name: 'wpackagist-plugin/contact-form-7',
                label: 'Contact Form 7',
                description: 'Editorial form handling for classic website contact flows.',
                optionalProfiles: ['website', 'commerce'],
            ),
            new PackageSuggestion(
                name: 'wpackagist-plugin/performance-lab',
                label: 'Performance Lab',
                description: 'WordPress performance feature plugin for measurement and experiments.',
                optionalProfiles: ['website', 'commerce'],
            ),
            new PackageSuggestion(
                name: 'wpackagist-plugin/query-monitor',
                label: 'Query Monitor',
                description: 'Developer diagnostics for database queries, hooks, requests and template state.',
                dev: true,
                optionalProfiles: ['website', 'app', 'commerce'],
            ),
            new PackageSuggestion(
                name: 'wpackagist-plugin/safe-svg',
                label: 'Safe SVG',
                description: 'Safer SVG uploads for content and brand-heavy sites.',
                optionalProfiles: ['website', 'app', 'commerce'],
            ),
            new PackageSuggestion(
                name: 'wpackagist-plugin/seo-by-rank-math',
                label: 'Rank Math SEO',
                description: 'SEO metadata, redirects and editorial search controls.',
                optionalProfiles: ['website', 'commerce'],
            ),
            new PackageSuggestion(
                name: 'wpackagist-plugin/woocommerce',
                label: 'WooCommerce',
                description: 'Commerce foundation for catalogs, carts and orders.',
                recommendedProfiles: ['commerce'],
            ),
        ];

        $this->suggestions = array_combine(
            array_map(static fn (PackageSuggestion $suggestion): string => $suggestion->name, $defaultSuggestions),
            $defaultSuggestions,
        );

        foreach ($suggestions as $suggestion) {
            $this->suggestions[$suggestion->name] = $suggestion;
        }
    }

    /**
     * @param list<PackageSuggestion> $suggestions
     */
    public function withSuggestions(array $suggestions): self
    {
        return new self([...array_values($this->suggestions), ...$suggestions]);
    }

    /**
     * @return list<PackageSuggestion>
     */
    public function forProfile(string $profileId, ?bool $dev = null): array
    {
        return array_values(array_filter(
            $this->suggestions,
            static fn (PackageSuggestion $suggestion): bool => $suggestion->isRelevantFor($profileId)
                && ($dev === null || $suggestion->dev === $dev),
        ));
    }

    /**
     * @return list<PackageSuggestion>
     */
    public function recommendedForProfile(string $profileId, ?bool $dev = null): array
    {
        return array_values(array_filter(
            $this->suggestions,
            static fn (PackageSuggestion $suggestion): bool => $suggestion->isRecommendedFor($profileId)
                && ($dev === null || $suggestion->dev === $dev),
        ));
    }

    public function referenceFor(string $package, bool $dev = false): PackageReference
    {
        $reference = PackageReference::fromComposerString($package);

        if ($reference->constraint !== '*' || !isset($this->suggestions[$reference->name])) {
            return $reference;
        }

        $suggestion = $this->suggestions[$reference->name];

        if ($suggestion->dev !== $dev) {
            return $reference;
        }

        return $suggestion->toReference();
    }
}
