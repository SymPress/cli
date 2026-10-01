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
        if (($command[0] ?? null) === 'git') {
            if ($cwd === null) {
                $process->setWorkingDirectory(sys_get_temp_dir());
            }
            $process->setEnv(['GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_INDEX_FILE' => false]);
        }
        $process->setTimeout(null);
        $process->run(static function (string $type, string $buffer) use ($output): void {
            unset($type);

            $output->write($buffer);
        });

        return $process->getExitCode() ?? 1;
    }
}
