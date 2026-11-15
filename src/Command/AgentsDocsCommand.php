<?php

declare(strict_types=1);

namespace SymPress\Cli\Command;

use InvalidArgumentException;
use RuntimeException;
use SymPress\Cli\Docs\AgentsDocsWriter;
use SymPress\Cli\Docs\GitHubDocsSource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'agents:md',
    description: 'Copy the docs of the installed SymPress packages into the project for coding agents.',
)]
final class AgentsDocsCommand extends Command
{
    private const INDEX_FILES = ['AGENTS.md', 'CLAUDE.md'];

    public function __construct(private readonly ?AgentsDocsWriter $writer = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::OPTIONAL, 'Project directory with composer.lock.', '.')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Index file: AGENTS.md or CLAUDE.md.', 'AGENTS.md')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the packages and versions without fetching.')
            ->setHelp(<<<'HELP'
Reads composer.lock, downloads the docs/ folder of every installed SymPress package at
its exact commit into <info>.sympress-docs/</info>, lists them in AGENTS.md (or CLAUDE.md)
and adds the folder to .gitignore. Run it again after <info>composer update</info>.

  <info>sympress agents:md</info>
  <info>sympress agents:md --output=CLAUDE.md</info>

Set GITHUB_TOKEN to raise GitHub's API rate limit.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = (string) $input->getArgument('directory');
        $index = (string) $input->getOption('output');
        if (!in_array($index, self::INDEX_FILES, true)) {
            $io->error('--output must be AGENTS.md or CLAUDE.md.');
            return Command::INVALID;
        }
        $writer = $this->writer ?? new AgentsDocsWriter(new GitHubDocsSource(
            is_string(getenv('GITHUB_TOKEN')) ? getenv('GITHUB_TOKEN') : null,
        ));
        try {
            $packages = $writer->lockedPackages($directory);
            if ($packages === []) {
                $io->warning('composer.lock contains no SymPress packages from github.com/SymPress.');
                return Command::SUCCESS;
            }
            $io->listing(array_map(
                static fn (array $package): string => $package['name'] . ' ' . $package['version'],
                array_values($packages),
            ));
            if ((bool) $input->getOption('dry-run')) {
                return Command::SUCCESS;
            }
            $written = $writer->write($directory, $packages, $index);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }
        $io->success(sprintf(
            'Wrote %d docs files to %s/ and indexed them in %s.',
            array_sum(array_map('count', $written)),
            AgentsDocsWriter::DIRECTORY,
            $index,
        ));

        return Command::SUCCESS;
    }
}
