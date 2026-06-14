<?php

declare(strict_types=1);

namespace SymPress\Cli\Project;

use JsonException;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final readonly class ProjectMetadataStore
{
    public const RELATIVE_PATH = '.sympress/project.json';

    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function write(string $projectDir, ProjectMetadata $metadata): void
    {
        $path = $this->path($projectDir);
        $this->filesystem->mkdir(dirname($path));
        $this->filesystem->dumpFile(
            $path,
            json_encode($metadata->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            . "\n",
        );
    }

    public function read(string $projectDir): ProjectMetadata
    {
        $path = $this->path($projectDir);

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('No SymPress project metadata found at "%s".', $path));
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('Unable to parse "%s": %s', $path, $exception->getMessage()));
        }

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Expected "%s" to contain a JSON object.', $path));
        }

        return ProjectMetadata::fromArray($data);
    }

    public function findProjectDir(string $cwd): ?string
    {
        $directory = realpath($cwd) ?: $cwd;

        while ($directory !== dirname($directory)) {
            if (is_file($this->path($directory))) {
                return $directory;
            }

            $directory = dirname($directory);
        }

        return is_file($this->path($directory)) ? $directory : null;
    }

    public function path(string $projectDir): string
    {
        return rtrim($projectDir, '/') . '/' . self::RELATIVE_PATH;
    }
}
