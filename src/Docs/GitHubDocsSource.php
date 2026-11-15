<?php

declare(strict_types=1);

namespace SymPress\Cli\Docs;

use RuntimeException;

/** Reads docs/ of a SymPress repository from GitHub: the tree from the API, the files from raw URLs. */
final class GitHubDocsSource implements DocsSource
{
    public function __construct(private readonly ?string $token = null)
    {
    }

    public function files(string $repository, string $commit): array
    {
        $tree = json_decode(
            $this->get(sprintf(
                'https://api.github.com/repos/SymPress/%s/git/trees/%s?recursive=1',
                $repository,
                $commit,
            )),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $files = [];
        foreach (is_array($tree) && is_array($tree['tree'] ?? null) ? $tree['tree'] : [] as $entry) {
            $path = is_array($entry) && is_string($entry['path'] ?? null) ? $entry['path'] : '';
            $relative = DocsPath::relative($path);
            if (($entry['type'] ?? null) === 'blob' && $relative !== null) {
                $files[$relative] = $this->get(
                    sprintf('https://raw.githubusercontent.com/SymPress/%s/%s/%s', $repository, $commit, $path),
                );
            }
        }

        return $files;
    }

    private function get(string $url): string
    {
        $headers = ['User-Agent: sympress-cli', 'Accept: application/vnd.github+json'];
        if ($this->token !== null && $this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        $context = stream_context_create(['http' => ['header' => $headers, 'timeout' => 20, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        // After redirects the headers hold one status line per response; the last one counts.
        $status = 0;
        foreach (http_get_last_response_headers() ?? [] as $header) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }
        if (!is_string($body) || $status !== 200) {
            throw new RuntimeException(sprintf('GitHub request failed (%d): %s', $status, $url));
        }

        return $body;
    }
}
