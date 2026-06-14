<?php

declare(strict_types=1);

namespace SymPress\Cli\Repository;

use JsonException;
use RuntimeException;
use SymPress\Cli\Model\PackageSuggestion;
use SymPress\Cli\Model\ProjectProfile;
use SymPress\Cli\Model\TemplateDefinition;

final class RepositoryManifestParser
{
    public function parse(string $json, ?string $source = null): RepositoryManifest
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf(
                    'Unable to parse manifest%s: %s',
                    $source === null ? '' : ' "' . $source . '"',
                    $exception->getMessage(),
                ),
                0,
                $exception,
            );
        }

        if (!is_array($data)) {
            throw new RuntimeException('Template manifest must contain a JSON object.');
        }

        return new RepositoryManifest(
            templates: $this->templates($data),
            profiles: $this->profiles($data),
            packageSuggestions: $this->packageSuggestions($data),
            source: $source,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<TemplateDefinition>
     */
    private function templates(array $data): array
    {
        $templates = $data['templates'] ?? (isset($data['template']) ? [$data['template']] : []);

        if (!is_array($templates)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $template): ?TemplateDefinition => $this->template($template),
            $templates,
        )));
    }

    private function template(mixed $template): ?TemplateDefinition
    {
        if (!is_array($template)) {
            return null;
        }

        $id = $this->string($template, 'id');
        $packageName = $this->string($template, 'packageName') ?: $this->string($template, 'package');
        $repositoryUrl = $this->string($template, 'repositoryUrl') ?: $this->string($template, 'repository');

        if ($id === null || $packageName === null || $repositoryUrl === null) {
            return null;
        }

        return new TemplateDefinition(
            id: $id,
            label: $this->string($template, 'label') ?: $id,
            packageName: $packageName,
            repositoryUrl: $repositoryUrl,
            description: $this->string($template, 'description') ?: 'Repository-provided starter template.',
            defaultVersion: $this->string($template, 'defaultVersion') ?: '1.0.x-dev',
            setupCommand: $this->stringList($template['setupCommand'] ?? null)
                ?: ['bin/console', 'setup', '{project_slug}'],
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<ProjectProfile>
     */
    private function profiles(array $data): array
    {
        if (!isset($data['profiles']) || !is_array($data['profiles'])) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $profile): ?ProjectProfile => $this->profile($profile),
            $data['profiles'],
        )));
    }

    private function profile(mixed $profile): ?ProjectProfile
    {
        if (!is_array($profile)) {
            return null;
        }

        $id = $this->string($profile, 'id');

        if ($id === null) {
            return null;
        }

        return new ProjectProfile(
            id: $id,
            label: $this->string($profile, 'label') ?: $id,
            description: $this->string($profile, 'description') ?: 'Repository-provided project type.',
            exampleName: $this->string($profile, 'exampleName') ?: $id,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<PackageSuggestion>
     */
    private function packageSuggestions(array $data): array
    {
        $suggestions = $data['packageSuggestions'] ?? $data['packages'] ?? [];

        if (!is_array($suggestions)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $suggestion): ?PackageSuggestion => $this->packageSuggestion($suggestion),
            $suggestions,
        )));
    }

    private function packageSuggestion(mixed $suggestion): ?PackageSuggestion
    {
        if (!is_array($suggestion)) {
            return null;
        }

        $name = $this->string($suggestion, 'name') ?: $this->string($suggestion, 'package');

        if ($name === null) {
            return null;
        }

        return new PackageSuggestion(
            name: $name,
            label: $this->string($suggestion, 'label') ?: $name,
            description: $this->string($suggestion, 'description') ?: 'Repository-provided package suggestion.',
            constraint: $this->string($suggestion, 'constraint') ?: '*',
            dev: (bool) ($suggestion['dev'] ?? false),
            recommendedProfiles: $this->stringList($suggestion['recommendedProfiles'] ?? null),
            optionalProfiles: $this->stringList($suggestion['optionalProfiles'] ?? null),
            repositoryUrl: $this->string($suggestion, 'repositoryUrl') ?: $this->string($suggestion, 'repository'),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $value),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
