<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Generator;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Catalog\DefaultProfileCatalog;
use SymPress\Cli\Catalog\DefaultTemplateCatalog;
use SymPress\Cli\Generator\ComposerJsonEditor;
use SymPress\Cli\Model\PackageReference;
use SymPress\Cli\Model\ProjectConfiguration;

final class ComposerJsonEditorTest extends TestCase
{
    public function testApplyWritesProjectMetadataAndPackages(): void
    {
        $projectDir = $this->temporaryDirectory();
        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'sympress/starter',
            'require' => [
                'php' => '^8.5',
            ],
        ], JSON_PRETTY_PRINT));

        $configuration = new ProjectConfiguration(
            template: (new DefaultTemplateCatalog())->first(),
            profile: (new DefaultProfileCatalog())->default(),
            directory: $projectDir,
            projectName: 'Customer Portal',
            projectSlug: 'customer-portal',
            composerPackageName: 'acme/customer-portal',
            ddevTld: 'ddev.site',
            wpAdminUsername: 'admin',
            wpAdminPassword: 'secret-secret',
            packages: [new PackageReference('sympress/assets')],
            devPackages: [new PackageReference('sympress/profiler')],
        );

        (new ComposerJsonEditor())->apply($projectDir, $configuration);

        $data = json_decode((string) file_get_contents($projectDir . '/composer.json'), true);

        self::assertSame('acme/customer-portal', $data['name']);
        self::assertSame('*', $data['require']['sympress/assets']);
        self::assertSame('*', $data['require-dev']['sympress/profiler']);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $directory;
    }
}
