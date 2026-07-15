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
            'schemaVersion' => 1,
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

        self::assertSame(1, $manifest->schemaVersion);
        self::assertSame('custom-starter', $manifest->templates[0]->id);
        self::assertSame('dev-main', $manifest->templates[0]->defaultVersion);
        self::assertSame(['bin/acme', 'setup', '{project_slug}'], $manifest->templates[0]->setupCommand);
        self::assertSame('service', $manifest->profiles[0]->id);
        self::assertSame('sympress/mailer', $manifest->packageSuggestions[0]->name);
    }

    public function testParseRejectsMissingSchemaVersion(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('schemaVersion 1');

        (new RepositoryManifestParser())->parse('{}');
    }

    public function testParseRejectsMalformedEntriesInsteadOfDroppingThem(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('templates[0] requires');

        (new RepositoryManifestParser())->parse('{"schemaVersion":1,"templates":[{"id":"incomplete"}]}');
    }

    public function testParseRejectsNonStringSetupCommandArguments(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('setupCommand must contain only non-empty strings');

        (new RepositoryManifestParser())->parse(json_encode([
            'schemaVersion' => 1,
            'templates' => [[
                'id' => 'starter',
                'packageName' => 'acme/starter',
                'repositoryUrl' => 'https://github.com/acme/starter',
                'setupCommand' => ['bin/console', 123],
            ]],
        ], JSON_THROW_ON_ERROR));
    }

    public function testParseRejectsValuesOutsideThePublishedSchema(): void
    {
        $invalid = [
            'unknown root property' => '{"schemaVersion":1,"unexpected":true}',
            'wrong optional string type' => '{"schemaVersion":1,"profiles":[{"id":"site","label":123}]}',
            'wrong boolean type' => '{"schemaVersion":1,"packageSuggestions":[{"name":"a/b","dev":"false"}]}',
            'legacy aliases' => '{"schemaVersion":1,"template":'
                . '{"id":"x","package":"a/b","repository":"https://example.test"}}',
            'duplicate list value' => '{"schemaVersion":1,"packageSuggestions":'
                . '[{"name":"a/b","recommendedProfiles":["site","site"]}]}',
        ];

        foreach ($invalid as $case => $json) {
            $rejected = false;

            try {
                (new RepositoryManifestParser())->parse($json);
            } catch (\RuntimeException) {
                $rejected = true;
            }

            self::assertTrue($rejected, "Expected the {$case} manifest to be rejected.");
        }
    }
}
