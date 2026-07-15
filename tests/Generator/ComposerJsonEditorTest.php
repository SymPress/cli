<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Generator;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Catalog\DefaultPackageCatalog;
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

    public function testApplyPreservesTemplateConstraintsForSuggestedPackages(): void
    {
        $projectDir = $this->temporaryDirectory();
        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'sympress/starter',
            'require-dev' => [
                'sympress/profiler' => 'dev-main as 1.0.x-dev',
                'symfony/var-dumper' => '^8.1.0',
            ],
        ], JSON_PRETTY_PRINT));
        $catalog = new DefaultPackageCatalog();
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
            devPackages: [
                $catalog->referenceFor('sympress/profiler', true),
                $catalog->referenceFor('symfony/var-dumper', true),
            ],
        );

        (new ComposerJsonEditor())->apply($projectDir, $configuration);

        $data = json_decode((string) file_get_contents($projectDir . '/composer.json'), true);

        self::assertSame('dev-main as 1.0.x-dev', $data['require-dev']['sympress/profiler']);
        self::assertSame('^8.1.0', $data['require-dev']['symfony/var-dumper']);
    }

    public function testApplyWritesInstallMetadataForUnpublishedSuggestions(): void
    {
        $projectDir = $this->temporaryDirectory();
        file_put_contents($projectDir . '/composer.json', '{}');
        $catalog = new DefaultPackageCatalog();
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
            packages: [
                $catalog->referenceFor('sympress/mailer'),
                $catalog->referenceFor('sympress/nginx-cache'),
            ],
        );

        (new ComposerJsonEditor())->apply($projectDir, $configuration);

        $data = json_decode((string) file_get_contents($projectDir . '/composer.json'), true);
        $repositoryUrls = array_column($data['repositories'], 'url');

        self::assertSame('dev-main', $data['require']['sympress/mailer']);
        self::assertSame('dev-main', $data['require']['sympress/nginx-cache']);
        self::assertContains('https://github.com/SymPress/mailer', $repositoryUrls);
        self::assertContains('https://github.com/SymPress/nginx-cache', $repositoryUrls);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $directory;
    }
}
