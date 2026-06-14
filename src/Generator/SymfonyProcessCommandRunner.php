<?php

declare(strict_types=1);

namespace SymPress\Cli\Generator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class SymfonyProcessCommandRunner implements CommandRunner
{
    public function run(array $command, ?string $cwd, OutputInterface $output): int
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(null);
        $process->run(static function (string $type, string $buffer) use ($output): void {
            unset($type);

            $output->write($buffer);
        });

        return $process->getExitCode() ?? 1;
    }
}
