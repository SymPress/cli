<?php

declare(strict_types=1);

namespace SymPress\Cli\Docs;

interface DocsSource
{
    /**
     * Markdown files below docs/ of a SymPress repository at one commit.
     *
     * @return array<string, string> Contents keyed by path relative to docs/.
     */
    public function files(string $repository, string $commit): array;
}
