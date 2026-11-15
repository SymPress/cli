<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Docs;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Command\AgentsDocsCommand;
use SymPress\Cli\Docs\AgentsDocsWriter;
use SymPress\Cli\Docs\DocsPath;
use SymPress\Cli\Docs\DocsSource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class AgentsDocsWriterTest extends TestCase
{
    private const COMMIT = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/sympress-agents-' . bin2hex(random_bytes(4));
        $package = static fn (string $name, string $url, string $reference): array => [
            'name' => $name,
            'version' => 'v1.4.2',
            'source' => ['type' => 'git', 'url' => $url, 'reference' => $reference],
        ];
        (new Filesystem())->dumpFile($this->projectDir . '/composer.lock', (string) json_encode([
            'packages' => [
                $package('sympress/kernel', 'https://github.com/SymPress/kernel.git', self::COMMIT),
                $package('acme/kernel', 'https://github.com/SymPress/kernel-fork.git', self::COMMIT),
                $package('sympress/fork', 'https://github.com/acme/fork.git', self::COMMIT),
                $package('sympress/branch', 'https://github.com/SymPress/branch.git', 'main'),
            ],
        ]));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testFindsOnlySymPressPackagesPinnedToACommit(): void
    {
        $packages = $this->writer()->lockedPackages($this->projectDir);

        $kernel = ['name' => 'sympress/kernel', 'version' => 'v1.4.2', 'commit' => self::COMMIT];
        self::assertSame(['kernel' => $kernel], $packages);
    }

    public function testWritesDocsIndexAndIgnoreEntryIdempotently(): void
    {
        file_put_contents($this->projectDir . '/AGENTS.md', "# Project rules\n");
        $writer = $this->writer();
        $packages = $writer->lockedPackages($this->projectDir);

        $writer->write($this->projectDir, $packages, 'AGENTS.md');
        $writer->write($this->projectDir, $packages, 'AGENTS.md');

        self::assertStringEqualsFile($this->projectDir . '/.sympress-docs/kernel/guides/hooks.md', "# Hooks\n");
        self::assertFileDoesNotExist($this->projectDir . '/escape.md');
        $index = (string) file_get_contents($this->projectDir . '/AGENTS.md');
        self::assertStringStartsWith("# Project rules\n\n<!-- sympress-docs:start -->", $index);
        self::assertSame(1, substr_count($index, '<!-- sympress-docs:start -->'));
        $line = '`sympress/kernel` v1.4.2: `.sympress-docs/kernel/` (README.md, guides/hooks.md)';
        self::assertStringContainsString($line, $index);
        self::assertSame("/.sympress-docs/\n", file_get_contents($this->projectDir . '/.gitignore'));
    }

    public function testCommandRejectsOtherIndexFilesAndListsPackagesOnDryRun(): void
    {
        $tester = new CommandTester(new AgentsDocsCommand($this->writer()));

        $invalid = $tester->execute(['directory' => $this->projectDir, '--output' => '../x.md']);
        self::assertSame(Command::INVALID, $invalid);
        self::assertSame(Command::SUCCESS, $tester->execute(['directory' => $this->projectDir, '--dry-run' => true]));
        self::assertStringContainsString('sympress/kernel v1.4.2', $tester->getDisplay());
        self::assertDirectoryDoesNotExist($this->projectDir . '/.sympress-docs');
    }

    public function testDocsPathAcceptsOnlyMarkdownBelowDocs(): void
    {
        self::assertSame('guides/hooks.md', DocsPath::relative('docs/guides/hooks.md'));
        self::assertNull(DocsPath::relative('docs/../composer.json'));
        self::assertNull(DocsPath::relative('docs/guides/../../x.md'));
        self::assertNull(DocsPath::relative('src/Kernel.md'));
        self::assertNull(DocsPath::relative('docs/image.png'));
    }

    private function writer(): AgentsDocsWriter
    {
        return new AgentsDocsWriter(new class implements DocsSource {
            public function files(string $repository, string $commit): array
            {
                return ['README.md' => "# Kernel\n", 'guides/hooks.md' => "# Hooks\n", '../../escape.md' => 'x'];
            }
        });
    }
}
