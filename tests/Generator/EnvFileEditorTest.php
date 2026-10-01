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

        self::assertStringContainsString('WP_HOME="https://acme-website.ddev.site"', $env);
        self::assertStringContainsString('WP_SITEURL=${WP_HOME}' . "\n", $env);
        self::assertStringContainsString('WP_ADMIN_PASSWORD="secret-secret"', $env);
    }

    public function testValuesRoundTripThroughRealDotenvParserAndFileIsPrivate(): void
    {
        $directory = $this->temporaryDirectory();
        $secret = " space # hash $ dollar ' apostrophe \" quote \\ slash\nnewline\rreturn Ä ";
        $configuration = new ProjectConfiguration(
            template: (new DefaultTemplateCatalog())->first(),
            profile: (new DefaultProfileCatalog())->default(),
            directory: $directory,
            projectName: 'Test',
            projectSlug: 'test',
            composerPackageName: 'acme/test',
            ddevTld: 'ddev.site',
            wpAdminUsername: $secret,
            wpAdminPassword: $secret,
        );
        (new EnvFileEditor())->apply($directory, $configuration);
        $values = (new \Symfony\Component\Dotenv\Dotenv())->parse((string) file_get_contents($directory . '/.env'));
        self::assertSame($secret, $values['WP_ADMIN_PASSWORD']);
        self::assertSame($secret, $values['WP_ADMIN_USERNAME']);
        self::assertSame('https://test.ddev.site', $values['WP_SITEURL']);
        self::assertSame(0600, fileperms($directory . '/.env') & 0777);
    }

    public function testSelectedTemplateRootAndSubdirectorySiteUrlExpressionsArePreserved(): void
    {
        $expressions = [
            'WP_SITEURL=${WP_HOME}',
            'WP_SITEURL=${WP_HOME}/wp',
            'export WP_SITEURL = "${WP_HOME}/core"',
        ];
        foreach ($expressions as $expression) {
            $directory = $this->temporaryDirectory();
            file_put_contents(
                $directory . '/.env.example',
                'WP_HOME=https://example.test' . "\n" . $expression . "\n",
            );
            $configuration = new ProjectConfiguration(
                template: (new DefaultTemplateCatalog())->first(),
                profile: (new DefaultProfileCatalog())->default(),
                directory: $directory,
                projectName: 'Test',
                projectSlug: 'test',
                composerPackageName: 'acme/test',
                ddevTld: 'ddev.site',
                wpAdminUsername: 'test',
                wpAdminPassword: 'private-test',
            );
            (new EnvFileEditor())->apply($directory, $configuration);
            $contents = (string) file_get_contents($directory . '/.env');
            self::assertStringContainsString($expression . "\n", $contents);
            self::assertSame(1, substr_count($contents, 'WP_SITEURL'));
        }
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sympress-cli-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $directory;
    }
}
