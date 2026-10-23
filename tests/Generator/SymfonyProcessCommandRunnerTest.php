<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Generator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SymPress\Cli\Generator\SymfonyProcessCommandRunner;
use SymPress\Cli\Repository\RepositoryManifestLoader;
use SymPress\Cli\Repository\TemplateRevision;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class SymfonyProcessCommandRunnerTest extends TestCase
{
    private string $root;
    private string $repository;
    private string $revision;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cli-git-environment-' . bin2hex(random_bytes(6));
        $this->repository = $this->root . '/template repository';
        mkdir($this->repository . '/.sympress', 0700, true);
        $this->git(['init', '-b', 'main']);
        $this->git(['config', 'user.name', 'Git environment fixture']);
        $this->git(['config', 'user.email', 'fixture@example.test']);
        file_put_contents($this->repository . '/.sympress/cli.json', json_encode([
            'schemaVersion' => 1,
            'profiles' => [[
                'id' => 'website', 'label' => 'Pinned release',
                'description' => 'Committed manifest.', 'exampleName' => 'fixture-site',
            ]],
        ], JSON_THROW_ON_ERROR));
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'Committed template manifest']);
        $this->revision = $this->git(['rev-parse', 'HEAD']);
        $this->git(['tag', 'v1.0.0']);
        file_put_contents($this->repository . '/.sympress/cli.json', '{"schemaVersion":999}');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    /** @return iterable<string, array{bool}> */
    public static function workingDirectories(): iterable
    {
        yield 'explicit working directory' => [true];
        yield 'temporary working directory with git -C' => [false];
    }

    #[DataProvider('workingDirectories')]
    public function testGitUsesOnlyItsOwnDirectoryWorktreeAndIndex(bool $explicitCwd): void
    {
        $this->withForeignGitEnvironment(function () use ($explicitCwd): void {
            $output = new BufferedOutput();
            $command = ['git', ...($explicitCwd ? [] : ['-C', $this->repository]),
                'rev-parse', '--path-format=absolute', '--absolute-git-dir', '--show-toplevel', '--git-path', 'index'];
            self::assertSame(0, (new SymfonyProcessCommandRunner())->run(
                $command,
                $explicitCwd ? $this->repository : null,
                $output,
            ));
            $repository = str_replace('\\', '/', (string) realpath($this->repository));
            self::assertSame(
                [$repository . '/.git', $repository, $repository . '/.git/index'],
                preg_split('/\r?\n/', trim(str_replace('\\', '/', $output->fetch()))),
            );
        });
    }

    public function testRevisionAndPinnedManifestIgnoreForeignGitEnvironment(): void
    {
        $this->withForeignGitEnvironment(function (): void {
            self::assertSame($this->revision, (new TemplateRevision())->resolve($this->repository, '1.0.0'));
            $manifest = (new RepositoryManifestLoader())->loadFromRepository($this->repository, $this->revision);
            self::assertNotNull($manifest);
            self::assertSame('Pinned release', $manifest->profiles[0]->label);
            self::assertSame('{"schemaVersion":999}', file_get_contents($this->repository . '/.sympress/cli.json'));
        });
    }

    public function testNonGitCommandRetainsItsEnvironmentWorkingDirectoryAndExitCode(): void
    {
        $this->withForeignGitEnvironment(function (): void {
            $output = new BufferedOutput();
            $script = 'echo json_encode([getenv("GIT_DIR"), getenv("GIT_WORK_TREE"), '
                . 'getenv("GIT_INDEX_FILE"), getcwd()]); exit(7);';
            self::assertSame(7, (new SymfonyProcessCommandRunner())->run(
                [PHP_BINARY, '-r', $script],
                $this->root,
                $output,
            ));
            self::assertSame([
                $this->root . '/foreign.git', $this->root . '/foreign-worktree', $this->root . '/foreign.index',
                realpath($this->root),
            ], json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR));
        });
    }

    public function testGitFailurePreservesExitCodeAndForwardedError(): void
    {
        $this->withForeignGitEnvironment(function (): void {
            $output = new BufferedOutput();
            self::assertSame(128, (new SymfonyProcessCommandRunner())->run(
                ['git', '-C', $this->repository, 'rev-parse', '--verify', 'refs/heads/missing'],
                null,
                $output,
            ));
            self::assertNotSame('', $output->fetch());
        });
    }

    /** @param callable(): void $operation */
    private function withForeignGitEnvironment(callable $operation): void
    {
        $values = [
            'GIT_DIR' => $this->root . '/foreign.git',
            'GIT_WORK_TREE' => $this->root . '/foreign-worktree',
            'GIT_INDEX_FILE' => $this->root . '/foreign.index',
        ];
        $previous = [];
        foreach ($values as $key => $value) {
            $previous[$key] = [getenv($key), array_key_exists($key, $_ENV), $_ENV[$key] ?? null,
                array_key_exists($key, $_SERVER), $_SERVER[$key] ?? null];
            putenv($key . '=' . $value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        try {
            $operation();
            foreach ($values as $key => $value) {
                self::assertSame($value, getenv($key));
                self::assertSame($value, $_ENV[$key]);
                self::assertSame($value, $_SERVER[$key]);
            }
            self::assertFileDoesNotExist($this->root . '/foreign.index');
        } finally {
            foreach ($previous as $key => [$environment, $hadEnv, $env, $hadServer, $server]) {
                putenv($environment === false ? $key : $key . '=' . $environment);
                if ($hadEnv) {
                    $_ENV[$key] = $env;
                } else {
                    unset($_ENV[$key]);
                }
                if ($hadServer) {
                    $_SERVER[$key] = $server;
                } else {
                    unset($_SERVER[$key]);
                }
            }
        }
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        $process = new Process(['git', '-C', $this->repository, ...$arguments], env: [
            'GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_INDEX_FILE' => false,
        ]);
        $process->mustRun();
        return trim($process->getOutput());
    }
}
