<?php

declare(strict_types=1);

namespace SymPress\Cli\Repository;

use RuntimeException;

final readonly class RepositoryManifestLoader
{
    /**
     * @var list<string>
     */
    private const MANIFEST_PATHS = [
        '.sympress/cli.json',
        '.sympress-cli.json',
        'sympress-cli.json',
    ];

    public function __construct(
        private RepositoryManifestParser $parser = new RepositoryManifestParser(),
    ) {
    }

    public function loadRequired(string $source, string $ref = 'main'): RepositoryManifest
    {
        $manifest = $this->load($source, $ref);

        if ($manifest === null) {
            throw new RuntimeException(sprintf('No SymPress CLI manifest found at "%s".', $source));
        }

        return $manifest;
    }

    public function load(string $source, string $ref = 'main'): ?RepositoryManifest
    {
        $source = trim($source);

        if ($source === '') {
            return null;
        }

        if (is_dir($source)) {
            return $this->loadFromDirectory($source);
        }

        if (is_file($source)) {
            return $this->parser->parse((string) file_get_contents($source), $source);
        }

        foreach ($this->candidateUrls($source, $ref) as $url) {
            $json = $this->readUrl($url);

            if ($json !== null) {
                return $this->parser->parse($json, $url);
            }
        }

        return null;
    }

    public function loadFromRepository(string $repositoryUrl, string $ref = 'main'): ?RepositoryManifest
    {
        return $this->load($repositoryUrl, $ref);
    }

    private function loadFromDirectory(string $directory): ?RepositoryManifest
    {
        foreach (self::MANIFEST_PATHS as $path) {
            $manifest = rtrim($directory, '/') . '/' . $path;

            if (is_file($manifest)) {
                return $this->parser->parse((string) file_get_contents($manifest), $manifest);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidateUrls(string $source, string $ref): array
    {
        if (preg_match('#^https?://#', $source) !== 1) {
            return [];
        }

        if (str_ends_with($source, '.json')) {
            return [$source];
        }

        if (
            preg_match(
                '#^https?://github\.com/(?P<owner>[^/]+)/(?P<repo>[^/#?]+)#',
                $source,
                $matches,
            ) !== 1
        ) {
            return [];
        }

        $owner = $matches['owner'];
        $repo = preg_replace('/\.git$/', '', $matches['repo']) ?: $matches['repo'];

        return array_map(
            static fn (string $path): string => sprintf(
                'https://raw.githubusercontent.com/%s/%s/%s/%s',
                $owner,
                $repo,
                rawurlencode($ref),
                $path,
            ),
            self::MANIFEST_PATHS,
        );
    }

    private function readUrl(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 5,
                'user_agent' => 'sympress-cli',
            ],
        ]);

        $contents = @file_get_contents($url, false, $context);

        if (!is_string($contents) || $contents === '') {
            return null;
        }

        $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : [];

        if (isset($headers[0]) && !str_contains($headers[0], '200')) {
            return null;
        }

        return $contents;
    }
}
