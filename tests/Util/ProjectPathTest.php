<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Util;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SymPress\Cli\Generator\ComposerJsonEditor;
use SymPress\Cli\Model\PackageReference;
use SymPress\Cli\Project\ProjectMetadata;
use SymPress\Cli\Project\ProjectMetadataStore;
use SymPress\Cli\Util\ProjectPath;
use Symfony\Component\Filesystem\Filesystem;

final class ProjectPathTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sympress-path-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/project', 0777, true);
        mkdir($this->root . '/outside');
        file_put_contents($this->root . '/outside/composer.json', '{}');
        file_put_contents($this->root . '/project/CLAUDE.md', '');
        symlink('CLAUDE.md', $this->root . '/project/AGENTS.md');
        symlink('../outside/composer.json', $this->root . '/project/composer.json');
        symlink('../outside', $this->root . '/project/.sympress');
        symlink('../outside/new.md', $this->root . '/project/dangling.md');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testAllowsPathsAndLinksInsideTheProject(): void
    {
        ProjectPath::assertInside($this->root . '/project', $this->root . '/project/AGENTS.md');
        ProjectPath::assertInside($this->root . '/project', $this->root . '/project/.gitignore');
        $this->addToAssertionCount(2);
    }

    public function testRejectsDanglingLinksAndNewFilesInLinkedDirectories(): void
    {
        foreach (['dangling.md', '.sympress/project.json'] as $path) {
            try {
                ProjectPath::assertInside($this->root . '/project', $this->root . '/project/' . $path);
                self::fail($path . ' accepted.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('outside the project', $exception->getMessage());
            }
        }
        self::assertFileDoesNotExist($this->root . '/outside/new.md');
    }

    public function testWritersRefuseToFollowLinksOutOfTheProject(): void
    {
        $writes = [
            fn () => (new ComposerJsonEditor())->mergePackageReferences(
                $this->root . '/project',
                [new PackageReference('acme/x')],
                [],
            ),
            fn () => (new ProjectMetadataStore())->write($this->root . '/project', ProjectMetadata::fromArray([])),
        ];

        foreach ($writes as $write) {
            try {
                $write();
                self::fail('Write through a link accepted.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('outside the project', $exception->getMessage());
            }
        }
        self::assertStringEqualsFile($this->root . '/outside/composer.json', '{}');
        self::assertFileDoesNotExist($this->root . '/outside/project.json');
    }
}
