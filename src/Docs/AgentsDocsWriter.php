<?php

declare(strict_types=1);

namespace SymPress\Cli\Docs;

use InvalidArgumentException;
use JsonException;
use SymPress\Cli\Util\ProjectPath;
use Symfony\Component\Filesystem\Filesystem;

/** Copies the docs of the installed SymPress packages into the project and indexes them for coding agents. */
final class AgentsDocsWriter
{
    public const DIRECTORY = '.sympress-docs';
    private const START = '<!-- sympress-docs:start -->';
    private const END = '<!-- sympress-docs:end -->';

    public function __construct(
        private readonly DocsSource $source = new GitHubDocsSource(),
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * SymPress packages in composer.lock with their exact commit, keyed by repository name.
     *
     * @return array<string, array{name: string, version: string, commit: string}>
     * @throws InvalidArgumentException When composer.lock is missing or invalid.
     */
    public function lockedPackages(string $projectDir): array
    {
        $lock = $projectDir . '/composer.lock';
        try {
            $data = json_decode((string) @file_get_contents($lock), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException(sprintf('No readable composer.lock in %s.', $projectDir));
        }
        $packages = [];
        foreach ([...(array) ($data['packages'] ?? []), ...(array) ($data['packages-dev'] ?? [])] as $package) {
            $name = is_array($package) && is_string($package['name'] ?? null) ? $package['name'] : '';
            $url = is_string($package['source']['url'] ?? null) ? $package['source']['url'] : '';
            $commit = is_string($package['source']['reference'] ?? null) ? $package['source']['reference'] : '';
            $repository = preg_match('#^https://github\.com/SymPress/([^/]+?)(?:\.git)?$#', $url, $match) === 1
                ? $match[1]
                : '';
            $pinned = DocsPath::isRepository($repository) && DocsPath::isCommit($commit);
            if (str_starts_with($name, 'sympress/') && $pinned) {
                $version = is_string($package['version'] ?? null) ? $package['version'] : $commit;
                $packages[$repository] = ['name' => $name, 'version' => $version, 'commit' => $commit];
            }
        }
        ksort($packages);

        return $packages;
    }

    /**
     * Replace .sympress-docs/ with the docs at the installed commits and update the index and .gitignore.
     *
     * @param array<string, array{name: string, version: string, commit: string}> $packages
     * @return array<string, list<string>> Written files per repository.
     */
    public function write(string $projectDir, array $packages, string $indexFile): array
    {
        // Fetch everything before deleting the old copy, so a network error leaves it intact.
        $docs = [];
        foreach ($packages as $repository => $package) {
            $files = $this->source->files($repository, $package['commit']);
            $docs[$repository] = array_filter(
                $files,
                static fn (string $path): bool => DocsPath::relative('docs/' . $path) === $path,
                ARRAY_FILTER_USE_KEY,
            );
        }
        $target = $projectDir . '/' . self::DIRECTORY;
        ProjectPath::assertInside($projectDir, $projectDir . '/' . $indexFile);
        ProjectPath::assertInside($projectDir, $projectDir . '/.gitignore');
        $this->filesystem->remove($target);
        $written = [];
        foreach ($docs as $repository => $files) {
            foreach ($files as $path => $contents) {
                $this->filesystem->dumpFile($target . '/' . $repository . '/' . $path, $contents);
            }
            $written[$repository] = array_keys($files);
        }
        $this->updateIndex($projectDir . '/' . $indexFile, $packages, $written);
        $this->ignore($projectDir . '/.gitignore');

        return $written;
    }

    /**
     * @param array<string, array{name: string, version: string, commit: string}> $packages
     * @param array<string, list<string>>                                        $written
     */
    private function updateIndex(string $file, array $packages, array $written): void
    {
        $lines = [
            self::START,
            '## SymPress documentation',
            '',
            'The docs of the installed SymPress packages, at the installed versions, are in `'
                . self::DIRECTORY . '/`.',
            'Read them before changing code that uses these packages.',
            'After `composer update`, run `sympress agents:md`.',
            '',
        ];
        foreach ($packages as $repository => $package) {
            $files = implode(', ', $written[$repository] ?? []);
            $folder = self::DIRECTORY . '/' . $repository;
            $lines[] = sprintf('- `%s` %s: `%s/` (%s)', $package['name'], $package['version'], $folder, $files);
        }
        $block = implode("\n", [...$lines, self::END]);
        $current = is_file($file) ? (string) file_get_contents($file) : '';
        $pattern = '/' . preg_quote(self::START, '/') . '.*?' . preg_quote(self::END, '/') . '/s';
        $updated = preg_match($pattern, $current) === 1
            ? (string) preg_replace_callback($pattern, static fn (): string => $block, $current, 1)
            : rtrim($current) . ($current === '' ? '' : "\n\n") . $block . "\n";
        $this->filesystem->dumpFile($file, $updated);
    }

    private function ignore(string $file): void
    {
        $current = is_file($file) ? (string) file_get_contents($file) : '';
        if (preg_match('#^/?' . preg_quote(self::DIRECTORY, '#') . '/?$#m', $current) !== 1) {
            $prefix = $current === '' ? '' : rtrim($current) . "\n";
            $this->filesystem->dumpFile($file, $prefix . '/' . self::DIRECTORY . "/\n");
        }
    }
}
