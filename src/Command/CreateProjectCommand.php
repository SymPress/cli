<?php

declare(strict_types=1);

namespace SymPress\Cli\Command;

use InvalidArgumentException;
use RuntimeException;
use SymPress\Cli\Catalog\DefaultPackageCatalog;
use SymPress\Cli\Catalog\DefaultProfileCatalog;
use SymPress\Cli\Catalog\DefaultTemplateCatalog;
use SymPress\Cli\Generator\ProjectGenerator;
use SymPress\Cli\IO\ProjectQuestionnaire;
use SymPress\Cli\Util\ProjectNameNormalizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'project:create',
    description: 'Create a configured SymPress project from a starter template.',
    aliases: ['new', 'create'],
)]
final class CreateProjectCommand extends Command
{
    private ProjectQuestionnaire $questionnaire;

    private ProjectGenerator $generator;

    public function __construct(?ProjectQuestionnaire $questionnaire = null, ?ProjectGenerator $generator = null)
    {
        parent::__construct();

        $this->questionnaire = $questionnaire ?: new ProjectQuestionnaire(
            new DefaultTemplateCatalog(),
            new DefaultProfileCatalog(),
            new DefaultPackageCatalog(),
            new ProjectNameNormalizer(),
        );
        $this->generator = $generator ?: new ProjectGenerator();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::OPTIONAL, 'Target project directory.')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Template id, for example sympress-starter.')
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED,
                'Project type: website, app, microservice or commerce.',
            )
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Human-readable project name.')
            ->addOption('project-slug', null, InputOption::VALUE_REQUIRED, 'DDEV-safe project slug.')
            ->addOption(
                'package-name',
                null,
                InputOption::VALUE_REQUIRED,
                'Composer package name, for example acme/site.',
            )
            ->addOption('admin-user', null, InputOption::VALUE_REQUIRED, 'WordPress admin username.')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'WordPress admin password.')
            ->addOption('ddev-tld', null, InputOption::VALUE_REQUIRED, 'DDEV project TLD.')
            ->addOption(
                'package',
                'p',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Runtime package to add, optionally vendor/name:constraint.',
            )
            ->addOption(
                'dev-package',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Development package to add, optionally vendor/name:constraint.',
            )
            ->addOption(
                'no-suggested-packages',
                null,
                InputOption::VALUE_NONE,
                'Only add packages passed through --package/--dev-package.',
            )
            ->addOption('template-version', null, InputOption::VALUE_REQUIRED, 'Template version constraint.')
            ->addOption('repository', null, InputOption::VALUE_REQUIRED, 'Template VCS repository URL.')
            ->addOption(
                'manifest',
                null,
                InputOption::VALUE_REQUIRED,
                'Manifest file, URL or GitHub repository to load template metadata from.',
            )
            ->addOption('manifest-ref', null, InputOption::VALUE_REQUIRED, 'Git ref used for remote manifests.', 'main')
            ->addOption(
                'no-remote-manifest',
                null,
                InputOption::VALUE_NONE,
                'Skip automatic manifest discovery in the selected template repository.',
            )
            ->addOption('composer-bin', null, InputOption::VALUE_REQUIRED, 'Composer executable.', 'composer')
            ->addOption(
                'no-setup',
                null,
                InputOption::VALUE_NONE,
                'Create files only and skip the starter setup command.',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the plan without creating files.')
            ->setHelp(<<<'HELP'
The wizard asks the same kind of incremental questions you know from Symfony Maker,
but for a complete project:

  <info>sympress new my-site</info>
  <info>sympress new my-site --manifest=https://github.com/acme/starter</info>
  <info>sympress new customer-api --type=microservice --no-setup</info>
  <info>sympress project:create shop --type=commerce --package=wpackagist-plugin/woocommerce</info>

Interactive mode shows examples, explains package suggestions and writes the
selected defaults into composer.json and .env before the starter setup runs.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $configuration = $this->questionnaire->ask($input, $output, getcwd() ?: '.');

            return $this->generator->generate($configuration, $io);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
