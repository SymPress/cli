<?php

declare(strict_types=1);

namespace SymPress\Cli\Completion;

/** Capability bridge for Console versions before and after native path completion. */
final class DirectoryCompletion
{
    public static function suggest(object $suggestions): void
    {
        if (method_exists($suggestions, 'suggestDirectories')) {
            $suggestions->suggestDirectories();
        }
    }
}
