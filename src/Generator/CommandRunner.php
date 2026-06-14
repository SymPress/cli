<?php

declare(strict_types=1);

namespace SymPress\Cli\Generator;

use Symfony\Component\Console\Output\OutputInterface;

interface CommandRunner
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, ?string $cwd, OutputInterface $output): int;
}
