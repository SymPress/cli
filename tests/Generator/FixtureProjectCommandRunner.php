<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Generator;

use SymPress\Cli\Generator\CommandRunner;
use Symfony\Component\Console\Output\OutputInterface;

final class FixtureProjectCommandRunner implements CommandRunner
{
    public ?bool $lockPresentDuringSetup = null;
    public int $calls = 0;

    /** @param array<string, mixed> $composer */
    public function __construct(
        private readonly array $composer,
    ) {
    }

    public function run(array $command, ?string $cwd, OutputInterface $output): int
    {
        ++$this->calls;
        unset($output);

        if ($cwd !== null && $command[0] === 'git') {
            return 0;
        }
        if ($cwd !== null) {
            $this->lockPresentDuringSetup = is_file($cwd . '/composer.lock');

            return 0;
        }

        $projectDir = $command[count($command) - 1];
        mkdir($projectDir, 0777, true);
        file_put_contents(
            $projectDir . '/composer.json',
            json_encode($this->composer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
        file_put_contents($projectDir . '/composer.lock', '{"content-hash":"starter"}');

        return 0;
    }
}
