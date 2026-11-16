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

    public function loadRequired(string $source, string $ref = '', string $sha256 = ''): RepositoryManifest
    {
        $manifest = $this->load($source, $ref, $sha256);

        if ($manifest === null) {
            throw new RuntimeException(sprintf('No SymPress CLI manifest found at "%s".', $source));
        }

        return $manifest;
    }

    public function load(string $source, string $ref = '', string $sha256 = ''): ?RepositoryManifest
    {
        $source = trim($source);

        if ($source === '') {
            return null;
        }

        if (is_dir($source)) {
            if (preg_match('/^[a-f0-9]{40}$/D', $ref) === 1) {
                foreach (self::MANIFEST_PATHS as $path) {
                    $process = new \Symfony\Component\Process\Process(
                        [
                            'git',
                            '-C',
                            $source,
                            'show',
                            $ref . ':' . $path,
                        ],
                        env: ['GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_INDEX_FILE' => false],
                    );
                    if ($process->run() === 0) {
                        return $this->parse($process->getOutput(), $source . '@' . $ref . ':' . $path, $sha256);
                    }
                }
                return null;
            }
            return $this->loadFromDirectory($source, $sha256);
        }

        if (is_file($source)) {
            return $this->parse((string) file_get_contents($source), $source, $sha256);
        }

        if (!str_ends_with($source, '.json') && preg_match('/^[a-f0-9]{40}$/D', $ref) !== 1) {
            throw new RuntimeException('Remote repository manifests require an explicit immutable Git SHA.');
        }
        foreach ($this->candidateUrls($source, $ref) as $url) {
            $json = $this->readUrl($url);

            if ($json !== null) {
                return $this->parse($json, $url, $sha256);
            }
        }

        return null;
    }

    public function loadFromRepository(string $repositoryUrl, string $ref = ''): ?RepositoryManifest
    {
        return $this->load($repositoryUrl, $ref);
    }

    /**
     * Explicit HTTPS JSON manifests are fetched as served; no Git SHA pins their content.
     */
    public function isUnpinnedRemote(string $source): bool
    {
        $source = trim($source);

        return str_ends_with($source, '.json') && !is_dir($source) && !is_file($source);
    }

    private function parse(string $json, string $source, string $sha256): RepositoryManifest
    {
        if ($sha256 !== '' && !hash_equals(strtolower($sha256), hash('sha256', $json))) {
            throw new RuntimeException(sprintf('Manifest "%s" does not match the expected SHA-256.', $source));
        }

        return $this->parser->parse($json, $source);
    }

    private function loadFromDirectory(string $directory, string $sha256): ?RepositoryManifest
    {
        foreach (self::MANIFEST_PATHS as $path) {
            $manifest = rtrim($directory, '/') . '/' . $path;

            if (is_file($manifest)) {
                return $this->parse((string) file_get_contents($manifest), $manifest, $sha256);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidateUrls(string $source, string $ref): array
    {
        if (preg_match('#^https://#', $source) !== 1) {
            return [];
        }

        if (str_ends_with($source, '.json')) {
            return [$source];
        }

        if (
            preg_match(
                '~^https://github\.com/(?P<owner>[^/]+)/(?P<repo>[^/#?]+)~',
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
