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
    private const int SCHEMA_VERSION = 1;

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

        if (!is_array($data) || !str_starts_with(ltrim($json), '{')) {
            throw new RuntimeException('Template manifest must contain a JSON object.');
        }

        $this->assertAllowedKeys(
            $data,
            ['$schema', 'schemaVersion', 'templates', 'profiles', 'packageSuggestions'],
            '',
        );

        if (array_key_exists('$schema', $data) && !is_string($data['$schema'])) {
            throw new RuntimeException('Manifest $schema must be a string.');
        }

        if (($data['schemaVersion'] ?? null) !== self::SCHEMA_VERSION) {
            throw new RuntimeException('Template manifest must declare schemaVersion 1.');
        }

        return new RepositoryManifest(
            schemaVersion: self::SCHEMA_VERSION,
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
        if (!array_key_exists('templates', $data)) {
            return [];
        }

        $templates = $data['templates'];

        if (!is_array($templates) || !array_is_list($templates)) {
            throw new RuntimeException('Manifest templates must be a JSON array.');
        }

        return array_map(
            fn (mixed $template, int $index): TemplateDefinition => $this->template($template, "templates[{$index}]"),
            $templates,
            array_keys($templates),
        );
    }

    private function template(mixed $template, string $path): TemplateDefinition
    {
        if (!is_array($template)) {
            throw new RuntimeException("Manifest {$path} must be a JSON object.");
        }

        $this->assertAllowedKeys(
            $template,
            ['id', 'label', 'packageName', 'repositoryUrl', 'description', 'defaultVersion', 'setupCommand'],
            $path,
        );
        $id = $this->string($template, 'id', $path);
        $packageName = $this->string($template, 'packageName', $path);
        $repositoryUrl = $this->string($template, 'repositoryUrl', $path);

        if ($id === null || $packageName === null || $repositoryUrl === null) {
            throw new RuntimeException(
                "Manifest {$path} requires non-empty id, packageName and repositoryUrl strings.",
            );
        }

        $setupCommand = array_key_exists('setupCommand', $template)
            ? $this->stringList($template['setupCommand'], "{$path}.setupCommand")
            : ['bin/console', 'setup', '{project_slug}'];

        if ($setupCommand === []) {
            throw new RuntimeException("Manifest {$path}.setupCommand must not be empty.");
        }

        return new TemplateDefinition(
            id: $id,
            label: $this->string($template, 'label', $path) ?? $id,
            packageName: $packageName,
            repositoryUrl: $repositoryUrl,
            description: $this->string($template, 'description', $path)
                ?? 'Repository-provided starter template.',
            defaultVersion: $this->string($template, 'defaultVersion', $path) ?? '1.0.x-dev',
            setupCommand: $setupCommand,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<ProjectProfile>
     */
    private function profiles(array $data): array
    {
        if (!array_key_exists('profiles', $data)) {
            return [];
        }

        if (!is_array($data['profiles']) || !array_is_list($data['profiles'])) {
            throw new RuntimeException('Manifest profiles must be a JSON array.');
        }

        return array_map(
            fn (mixed $profile, int $index): ProjectProfile => $this->profile($profile, "profiles[{$index}]"),
            $data['profiles'],
            array_keys($data['profiles']),
        );
    }

    private function profile(mixed $profile, string $path): ProjectProfile
    {
        if (!is_array($profile)) {
            throw new RuntimeException("Manifest {$path} must be a JSON object.");
        }

        $this->assertAllowedKeys($profile, ['id', 'label', 'description', 'exampleName'], $path);
        $id = $this->string($profile, 'id', $path);

        if ($id === null) {
            throw new RuntimeException("Manifest {$path} requires a non-empty id string.");
        }

        return new ProjectProfile(
            id: $id,
            label: $this->string($profile, 'label', $path) ?? $id,
            description: $this->string($profile, 'description', $path) ?? 'Repository-provided project type.',
            exampleName: $this->string($profile, 'exampleName', $path) ?? $id,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<PackageSuggestion>
     */
    private function packageSuggestions(array $data): array
    {
        if (!array_key_exists('packageSuggestions', $data)) {
            return [];
        }

        $suggestions = $data['packageSuggestions'];

        if (!is_array($suggestions) || !array_is_list($suggestions)) {
            throw new RuntimeException('Manifest packageSuggestions must be a JSON array.');
        }

        return array_map(
            fn (mixed $suggestion, int $index): PackageSuggestion => $this->packageSuggestion(
                $suggestion,
                "packageSuggestions[{$index}]",
            ),
            $suggestions,
            array_keys($suggestions),
        );
    }

    private function packageSuggestion(mixed $suggestion, string $path): PackageSuggestion
    {
        if (!is_array($suggestion)) {
            throw new RuntimeException("Manifest {$path} must be a JSON object.");
        }

        $this->assertAllowedKeys(
            $suggestion,
            [
                'name',
                'label',
                'description',
                'constraint',
                'dev',
                'recommendedProfiles',
                'optionalProfiles',
                'repositoryUrl',
            ],
            $path,
        );
        $name = $this->string($suggestion, 'name', $path);

        if ($name === null) {
            throw new RuntimeException("Manifest {$path} requires a non-empty name string.");
        }

        if (array_key_exists('dev', $suggestion) && !is_bool($suggestion['dev'])) {
            throw new RuntimeException("Manifest {$path}.dev must be a boolean.");
        }

        return new PackageSuggestion(
            name: $name,
            label: $this->string($suggestion, 'label', $path) ?? $name,
            description: $this->string($suggestion, 'description', $path)
                ?? 'Repository-provided package suggestion.',
            constraint: $this->string($suggestion, 'constraint', $path) ?? '*',
            dev: $suggestion['dev'] ?? false,
            recommendedProfiles: $this->optionalStringList(
                $suggestion,
                'recommendedProfiles',
                "{$path}.recommendedProfiles",
            ),
            optionalProfiles: $this->optionalStringList(
                $suggestion,
                'optionalProfiles',
                "{$path}.optionalProfiles",
            ),
            repositoryUrl: $this->string($suggestion, 'repositoryUrl', $path),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function string(array $data, string $key, string $path): ?string
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];

        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException("Manifest {$path}.{$key} must be a non-empty string.");
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private function optionalStringList(array $data, string $key, string $path): array
    {
        return array_key_exists($key, $data) ? $this->stringList($data[$key], $path) : [];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, string $path): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuntimeException("Manifest {$path} must be a JSON array of non-empty strings.");
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new RuntimeException("Manifest {$path} must contain only non-empty strings.");
            }

            $strings[] = trim($item);
        }

        if (count(array_unique($strings)) !== count($strings)) {
            throw new RuntimeException("Manifest {$path} must not contain duplicate strings.");
        }

        return $strings;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $allowed
     */
    private function assertAllowedKeys(array $data, array $allowed, string $path): void
    {
        $unknown = array_diff(array_keys($data), $allowed);

        if ($unknown === []) {
            return;
        }

        $prefix = $path === '' ? 'Manifest' : "Manifest {$path}";
        throw new RuntimeException(sprintf('%s contains unknown property %s.', $prefix, (string) reset($unknown)));
    }
}
