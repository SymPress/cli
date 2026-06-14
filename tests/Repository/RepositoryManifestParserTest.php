<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Repository;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Repository\RepositoryManifestParser;

final class RepositoryManifestParserTest extends TestCase
{
    public function testParseBuildsTemplatesProfilesAndPackageSuggestions(): void
    {
        $manifest = (new RepositoryManifestParser())->parse(json_encode([
            'templates' => [
                [
                    'id' => 'custom-starter',
                    'label' => 'Custom Starter',
                    'packageName' => 'acme/starter',
                    'repositoryUrl' => 'https://github.com/acme/starter',
                    'description' => 'Custom starter.',
                    'defaultVersion' => 'dev-main',
                    'setupCommand' => ['bin/acme', 'setup', '{project_slug}'],
                ],
            ],
            'profiles' => [
                [
                    'id' => 'service',
                    'label' => 'Service',
                    'description' => 'Background service.',
                    'exampleName' => 'content-service',
                ],
            ],
            'packageSuggestions' => [
                [
                    'name' => 'sympress/mailer',
                    'label' => 'Mailer',
                    'description' => 'Send mail.',
                    'recommendedProfiles' => ['service'],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertSame('custom-starter', $manifest->templates[0]->id);
        self::assertSame('dev-main', $manifest->templates[0]->defaultVersion);
        self::assertSame(['bin/acme', 'setup', '{project_slug}'], $manifest->templates[0]->setupCommand);
        self::assertSame('service', $manifest->profiles[0]->id);
        self::assertSame('sympress/mailer', $manifest->packageSuggestions[0]->name);
    }
}
