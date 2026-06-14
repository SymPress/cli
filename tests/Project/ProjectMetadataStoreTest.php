<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Project;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Project\ProjectMetadata;
use SymPress\Cli\Project\ProjectMetadataStore;

final class ProjectMetadataStoreTest extends TestCase
{
    public function testWriteReadAndFindProjectDirectory(): void
    {
        $projectDir = $this->temporaryDirectory();
        $nestedDir = $projectDir . '/packages/example';
        mkdir($nestedDir, 0777, true);

        $metadata = new ProjectMetadata(
            projectName: 'Example Project',
            projectSlug: 'example-project',
            composerPackageName: 'acme/example-project',
            ddevTld: 'ddev.site',
            profileId: 'microservice',
            templateId: 'sympress-starter',
            templatePackageName: 'sympress/starter',
            templateRepositoryUrl: 'https://github.com/SymPress/starter',
            templateVersion: '1.0.x-dev',
            updatedAt: '2026-06-14T00:00:00+00:00',
        );
        $store = new ProjectMetadataStore();

        $store->write($projectDir, $metadata);

        self::assertSame('microservice', $store->read($projectDir)->profileId);
        self::assertSame($projectDir, $store->findProjectDir($nestedDir));
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $directory;
    }
}
