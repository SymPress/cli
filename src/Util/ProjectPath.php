<?php

declare(strict_types=1);

namespace SymPress\Cli\Util;

use RuntimeException;

final class ProjectPath
{
    /**
     * Refuse a write that a symlink in a checked-in project would redirect outside the project directory.
     * Links that stay inside the project (e.g. AGENTS.md -> CLAUDE.md) remain allowed.
     *
     * @throws RuntimeException When the path, or its directory for a new file, resolves outside the project.
     */
    public static function assertInside(string $projectDir, string $path): void
    {
        $root = realpath($projectDir);
        $prefix = rtrim((string) $root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $resolved = match (true) {
            file_exists($path) => realpath($path),
            is_link($path) => false, // dangling link: writing would create its target wherever it points
            default => realpath(dirname($path)),
        };

        if ($root === false || $resolved === false || ($resolved !== $root && !str_starts_with($resolved, $prefix))) {
            throw new RuntimeException(sprintf(
                'Refusing to write "%s": it resolves outside the project directory.',
                $path,
            ));
        }
    }
}
