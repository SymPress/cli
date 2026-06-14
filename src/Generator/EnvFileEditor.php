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

        if (!is_file($envFile)) {
            if (is_file($envExample)) {
                $this->filesystem->copy($envExample, $envFile);
            } else {
                $this->filesystem->dumpFile($envFile, '');
            }
        }

        $this->update($envFile, [
            'DDEV_PROJECT_NAME' => $configuration->projectSlug,
            'DDEV_PROJECT_TLD' => $configuration->ddevTld,
            'WP_HOME' => $configuration->wpHome(),
            'WP_SITEURL' => '${WP_HOME}/wp',
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
                if (str_starts_with((string) $line, $key . '=')) {
                    $lines[$index] = $key . '=' . $value;
                    $seen[$key] = true;
                }
            }
        }

        foreach ($values as $key => $value) {
            if (!isset($seen[$key])) {
                $lines[] = $key . '=' . $value;
            }
        }

        $this->filesystem->dumpFile($envFile, implode("\n", $lines) . "\n");
    }
}
