<?php

declare(strict_types=1);

namespace SymPress\Cli\Util;

use InvalidArgumentException;

final class ProjectNameNormalizer
{
    private const COMPOSER_NAME_PATTERN = '/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/';

    public function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?: '';
        $slug = trim($slug, '-');
        $slug = preg_replace('/-+/', '-', $slug) ?: '';

        if ($slug === '') {
            throw new InvalidArgumentException('Project slug cannot be empty.');
        }

        return $slug;
    }

    public function composerName(string $projectSlug, ?string $value = null): string
    {
        $composerName = strtolower(trim($value ?: 'app/' . $projectSlug));
        $composerName = preg_replace('/[^a-z0-9_.\/-]+/', '-', $composerName) ?: '';

        if (preg_match(self::COMPOSER_NAME_PATTERN, $composerName) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid Composer package name.', $composerName));
        }

        return $composerName;
    }

    public function displayNameFromSlug(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }
}
