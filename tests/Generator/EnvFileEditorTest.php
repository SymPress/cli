<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Generator;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Catalog\DefaultProfileCatalog;
use SymPress\Cli\Catalog\DefaultTemplateCatalog;
use SymPress\Cli\Generator\EnvFileEditor;
use SymPress\Cli\Model\ProjectConfiguration;

final class EnvFileEditorTest extends TestCase
{
    public function testApplyCreatesEnvFromExampleAndUpdatesProjectValues(): void
    {
        $projectDir = $this->temporaryDirectory();
        file_put_contents($projectDir . '/.env.example', "WP_HOME=https://example.test\n");

        $configuration = new ProjectConfiguration(
            template: (new DefaultTemplateCatalog())->first(),
            profile: (new DefaultProfileCatalog())->default(),
            directory: $projectDir,
            projectName: 'Acme Website',
            projectSlug: 'acme-website',
            composerPackageName: 'acme/website',
            ddevTld: 'ddev.site',
            wpAdminUsername: 'admin',
            wpAdminPassword: 'secret-secret',
        );

        (new EnvFileEditor())->apply($projectDir, $configuration);

        $env = (string) file_get_contents($projectDir . '/.env');

        self::assertStringContainsString('WP_HOME=https://acme-website.ddev.site', $env);
        self::assertStringContainsString('WP_SITEURL=${WP_HOME}/wp', $env);
        self::assertStringContainsString('WP_ADMIN_PASSWORD=secret-secret', $env);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $directory;
    }
}
