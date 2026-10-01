<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Project;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Project\ProjectMetadata;
use SymPress\Cli\Catalog\DefaultProfileCatalog;
use SymPress\Cli\Catalog\DefaultTemplateCatalog;
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

    public function testChangedSourceRefInvalidatesPreviousImmutableIdentity(): void
    {
        $template = (new DefaultTemplateCatalog())->first();
        $profile = (new DefaultProfileCatalog())->default();
        $sha = str_repeat('a', 40);
        $metadata = ProjectMetadata::fromArray([
            'templateId' => $template->id, 'templatePackageName' => $template->packageName,
            'templateRepositoryUrl' => $template->repositoryUrl, 'templateVersion' => $template->defaultVersion,
            'templateRevision' => $sha,
        ]);
        self::assertSame($sha, $metadata->withUpdate($profile, $template)->templateRevision);
        self::assertNull($metadata->withUpdate($profile, $template, templateVersion: '2.0.0')->templateRevision);
        self::assertSame(str_repeat('b', 40), $metadata->withUpdate(
            $profile,
            $template,
            templateVersion: '2.0.0',
            templateRevision: str_repeat('b', 40),
        )->templateRevision);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $directory;
    }
}
