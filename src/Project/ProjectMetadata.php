<?php

declare(strict_types=1);

namespace SymPress\Cli\Project;

use SymPress\Cli\Model\ProjectConfiguration;
use SymPress\Cli\Model\ProjectProfile;
use SymPress\Cli\Model\TemplateDefinition;

final readonly class ProjectMetadata
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public string $projectName,
        public string $projectSlug,
        public string $composerPackageName,
        public string $ddevTld,
        public string $profileId,
        public string $templateId,
        public string $templatePackageName,
        public string $templateRepositoryUrl,
        public string $templateVersion,
        public string $updatedAt,
        public int $schemaVersion = self::SCHEMA_VERSION,
        public ?string $templateRevision = null,
    ) {
    }

    public static function fromConfiguration(ProjectConfiguration $configuration, ?string $revision = null): self
    {
        return new self(
            projectName: $configuration->projectName,
            projectSlug: $configuration->projectSlug,
            composerPackageName: $configuration->composerPackageName,
            ddevTld: $configuration->ddevTld,
            profileId: $configuration->profile->id,
            templateId: $configuration->template->id,
            templatePackageName: $configuration->template->packageName,
            templateRepositoryUrl: $configuration->templateRepository ?: $configuration->template->repositoryUrl,
            templateVersion: $configuration->templateVersion ?: $configuration->template->defaultVersion,
            updatedAt: gmdate('c'),
            templateRevision: $revision ?? $configuration->templateRevision,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            projectName: self::string($data, 'projectName'),
            projectSlug: self::string($data, 'projectSlug'),
            composerPackageName: self::string($data, 'composerPackageName'),
            ddevTld: self::string($data, 'ddevTld', 'ddev.site'),
            profileId: self::string($data, 'profileId'),
            templateId: self::string($data, 'templateId'),
            templatePackageName: self::string($data, 'templatePackageName'),
            templateRepositoryUrl: self::string($data, 'templateRepositoryUrl'),
            templateVersion: self::string($data, 'templateVersion', '1.0.x-dev'),
            updatedAt: self::string($data, 'updatedAt'),
            schemaVersion: (int) ($data['schemaVersion'] ?? self::SCHEMA_VERSION),
            templateRevision: self::string($data, 'templateRevision') ?: null,
        );
    }

    public function withUpdate(
        ProjectProfile $profile,
        TemplateDefinition $template,
        ?string $templateRepository = null,
        ?string $templateVersion = null,
        ?string $templateRevision = null,
    ): self {
        $repository = $templateRepository ?: $template->repositoryUrl;
        $version = $templateVersion ?: $template->defaultVersion;
        $preservedRevision = $repository === $this->templateRepositoryUrl && $version === $this->templateVersion
            && $template->id === $this->templateId ? $this->templateRevision : null;
        return new self(
            projectName: $this->projectName,
            projectSlug: $this->projectSlug,
            composerPackageName: $this->composerPackageName,
            ddevTld: $this->ddevTld,
            profileId: $profile->id,
            templateId: $template->id,
            templatePackageName: $template->packageName,
            templateRepositoryUrl: $templateRepository ?: $template->repositoryUrl,
            templateVersion: $templateVersion ?: $template->defaultVersion,
            updatedAt: gmdate('c'),
            schemaVersion: $this->schemaVersion,
            templateRevision: $templateRevision ?? $preservedRevision,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => $this->schemaVersion,
            'projectName' => $this->projectName,
            'projectSlug' => $this->projectSlug,
            'composerPackageName' => $this->composerPackageName,
            'ddevTld' => $this->ddevTld,
            'profileId' => $this->profileId,
            'templateId' => $this->templateId,
            'templatePackageName' => $this->templatePackageName,
            'templateRepositoryUrl' => $this->templateRepositoryUrl,
            'templateVersion' => $this->templateVersion,
            'templateRevision' => $this->templateRevision,
            'updatedAt' => $this->updatedAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }
}
