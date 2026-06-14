<?php

declare(strict_types=1);

namespace SymPress\Cli\Model;

final readonly class TemplateDefinition
{
    /**
     * @param list<string> $setupCommand
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $packageName,
        public string $repositoryUrl,
        public string $description,
        public string $defaultVersion = '1.0.x-dev',
        public array $setupCommand = ['bin/console', 'setup', '{project_slug}'],
    ) {
    }

    public function packageSpec(?string $version = null): string
    {
        $selectedVersion = $version ?: $this->defaultVersion;

        if ($selectedVersion === '') {
            return $this->packageName;
        }

        return $this->packageName . ':' . $selectedVersion;
    }

    /**
     * @return list<string>
     */
    public function setupCommandFor(ProjectConfiguration $configuration): array
    {
        return array_map(
            static fn (string $part): string => str_replace(
                [
                    '{project_slug}',
                    '{project_name}',
                ],
                [
                    $configuration->projectSlug,
                    $configuration->projectName,
                ],
                $part,
            ),
            $this->setupCommand,
        );
    }
}
