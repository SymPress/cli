<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Catalog;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Catalog\DefaultPackageCatalog;

final class DefaultPackageCatalogTest extends TestCase
{
    public function testWebsiteProfileHasRuntimeAndDevRecommendations(): void
    {
        $catalog = new DefaultPackageCatalog();

        $runtime = array_map(
            static fn ($suggestion): string => $suggestion->name,
            $catalog->recommendedForProfile('website', false),
        );
        $dev = array_map(
            static fn ($suggestion): string => $suggestion->name,
            $catalog->recommendedForProfile('website', true),
        );

        self::assertContains('sympress/assets', $runtime);
        self::assertNotContains('sympress/consent', $runtime);
        self::assertContains('sympress/profiler', $dev);
    }

    public function testUnpublishedPublicRecommendationsIncludeInstallMetadata(): void
    {
        $catalog = new DefaultPackageCatalog();

        foreach (['sympress/mailer', 'sympress/nginx-cache'] as $package) {
            $reference = $catalog->referenceFor($package);

            self::assertSame('dev-main', $reference->constraint);
            self::assertSame('https://github.com/SymPress/' . substr($package, 9), $reference->repositoryUrl);
            self::assertTrue($reference->suggested);
        }
    }

    public function testExplicitConstraintWinsOverCatalogDefault(): void
    {
        $reference = (new DefaultPackageCatalog())->referenceFor('sympress/assets:^1.2', false);

        self::assertSame('sympress/assets', $reference->name);
        self::assertSame('^1.2', $reference->constraint);
    }
}
