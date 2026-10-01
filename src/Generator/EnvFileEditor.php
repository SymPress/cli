<?php

declare(strict_types=1);

namespace SymPress\Cli\Generator;

use RuntimeException;
use SymPress\Cli\Model\ProjectConfiguration;
use Symfony\Component\Filesystem\Filesystem;

final readonly class EnvFileEditor
{
    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function apply(string $projectDir, ProjectConfiguration $configuration): void
    {
        $envFile = $projectDir . '/.env';
        $envExample = $projectDir . '/.env.example';

        $previous = umask(0077);
        try {
            if (!is_file($envFile)) {
                if (is_file($envExample)) {
                    $this->filesystem->copy($envExample, $envFile);
                } else {
                    $this->filesystem->dumpFile($envFile, '');
                }
            }

            chmod($envFile, 0600);
        } finally {
            umask($previous);
        }
        $this->update($envFile, [
            'DDEV_PROJECT_NAME' => $configuration->projectSlug,
            'DDEV_PROJECT_TLD' => $configuration->ddevTld,
            'WP_HOME' => $configuration->wpHome(),
            'WP_SITEURL' => '${WP_HOME}',
            'WP_ADMIN_USERNAME' => $configuration->wpAdminUsername,
            'WP_ADMIN_PASSWORD' => $configuration->wpAdminPassword,
        ]);
    }

    /**
     * @param array<string, string> $values
     */
    private function update(string $envFile, array $values): void
    {
        $lines = is_file($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES) : [];

        if ($lines === false) {
            throw new RuntimeException(sprintf('Unable to read "%s".', $envFile));
        }

        $seen = [];

        foreach ($lines as $index => $line) {
            foreach ($values as $key => $value) {
                if (preg_match('/^(?:export\s+)?' . preg_quote($key, '/') . '\s*=/', trim((string) $line)) === 1) {
                    if ($key === 'WP_SITEURL') {
                        // The selected template owns its WordPress root/subdirectory layout.
                        $seen[$key] = true;
                        continue;
                    }
                    $lines[$index] = $key . '=' . ($key === 'WP_SITEURL' ? $value : $this->quote($value));
                    $seen[$key] = true;
                }
            }
        }

        foreach ($values as $key => $value) {
            if (!isset($seen[$key])) {
                $lines[] = $key . '=' . ($key === 'WP_SITEURL' ? $value : $this->quote($value));
            }
        }

        $previous = umask(0077);
        try {
            $this->filesystem->dumpFile($envFile, implode("\n", $lines) . "\n");
            chmod($envFile, 0600);
        } finally {
            umask($previous);
        }
    }

    private function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '\\r', '\\n'], $value) . '"';
    }
}
