<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Util;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SymPress\Cli\Util\ProjectNameNormalizer;

final class ProjectNameNormalizerTest extends TestCase
{
    public function testSlugNormalizesProjectNames(): void
    {
        $normalizer = new ProjectNameNormalizer();

        self::assertSame('customer-portal-api', $normalizer->slug('Customer Portal API!'));
    }

    public function testComposerNameDefaultsToAppVendor(): void
    {
        $normalizer = new ProjectNameNormalizer();

        self::assertSame('app/customer-portal', $normalizer->composerName('customer-portal'));
    }

    public function testComposerNameRejectsMissingVendor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ProjectNameNormalizer())->composerName('site', 'site');
    }
}
