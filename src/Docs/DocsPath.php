<?php

declare(strict_types=1);

namespace SymPress\Cli\Docs;

/** The trust boundary for paths, repository names and commits that come from composer.lock or GitHub. */
final class DocsPath
{
    /** Path below docs/ for a Markdown file in the repository, or null for anything else or anything unsafe. */
    public static function relative(string $path): ?string
    {
        if (preg_match('#^docs/((?:[A-Za-z0-9_.-]+/)*[A-Za-z0-9_.-]+\.md)$#', $path, $match) !== 1) {
            return null;
        }

        return in_array('..', explode('/', $match[1]), true) ? null : $match[1];
    }

    public static function isRepository(string $name): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/', $name) === 1;
    }

    public static function isCommit(string $reference): bool
    {
        return preg_match('/^[0-9a-f]{40}$/', $reference) === 1;
    }
}
