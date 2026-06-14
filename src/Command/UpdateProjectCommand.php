<?php

declare(strict_types=1);

namespace SymPress\Cli\Command;

use InvalidArgumentException;
use RuntimeException;
use SymPress\Cli\Catalog\DefaultPackageCatalog;
use SymPress\Cli\Catalog\DefaultProfileCatalog;
use SymPress\Cli\Catalog\DefaultTemplateCatalog;
use SymPress\Cli\Project\ProjectMetadata;
use SymPress\Cli\Project\ProjectMetadataStore;
use SymPress\Cli\Repository\RepositoryManifest;
use SymPress\Cli\Repository\RepositoryManifestLoader;
use SymPress\Cli\Update\ProjectUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'project:update',
    description: 'Update a SymPress project created by this CLI.',
    aliases: ['update', 'upgrade'],
)]
final class UpdateProjectCommand extends Command
{
    public function __construct(
        private readonly ProjectMetadataStore $metadataStore = new ProjectMetadataStore(),
        private readonly RepositoryManifestLoader $manifestLoader = new RepositoryManifestLoader(),
        private readonly ProjectUpdater $updater = new ProjectUpdater(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::OPTIONAL, 'Project directory. Defaults to current project.')
            ->addOption('level', null, InputOption::VALUE_REQUIRED, 'Update level: patch, minor or major.')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Target project type.')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Target template id. Requires --level=major.')
            ->addOption('template-version', null, InputOption::VALUE_REQUIRED, 'Target template version.')
            ->addOption('repository', null, InputOption::VALUE_REQUIRED, 'Target template VCS repository URL.')
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
                'Skip automatic manifest discovery in the project template repository.',
            )
            ->addOption('composer-bin', null, InputOption::VALUE_REQUIRED, 'Composer executable.', 'composer')
            ->addOption('no-install', null, InputOption::VALUE_NONE, 'Do not run composer update after patching.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the update plan without changing files.')
            ->setHelp(<<<'HELP'
Run this inside a project created by the SymPress CLI:

  <info>sympress update --level=patch</info>
  <info>sympress update --level=minor --type=app</info>
  <info>sympress update --level=major --template=sympress-starter --type=website</info>

Patch keeps the current project type, minor allows type changes and major allows
template/repository changes as well.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $projectDir = $this->projectDir($input, getcwd() ?: '.');
            $metadata = $this->metadataStore->read($projectDir);
            [$templates, $profiles, $packages] = $this->catalogs($input, $metadata);
            $level = $this->level($input, $io);
            $targetType = $this->targetType($input, $io, $level, $metadata, $profiles);
            $targetTemplate = $this->targetTemplate($input, $level, $metadata);
            $templateRepository = $this->stringOption($input, 'repository');
            $templateVersion = $this->stringOption($input, 'template-version');
            $plan = $this->updater->plan(
                $projectDir,
                $level,
                $metadata,
                $templates,
                $profiles,
                $packages,
                $targetType,
                $targetTemplate,
                $templateRepository,
                $templateVersion,
            );

            return $this->updater->apply(
                $plan,
                $io,
                (bool) $input->getOption('dry-run'),
                !(bool) $input->getOption('no-install'),
                (string) $input->getOption('composer-bin'),
            );
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function projectDir(InputInterface $input, string $cwd): string
    {
        $directory = $this->stringArgument($input, 'directory');

        if ($directory !== null) {
            return $this->absolutePath($directory, $cwd);
        }

        $projectDir = $this->metadataStore->findProjectDir($cwd);

        if ($projectDir === null) {
            throw new RuntimeException('Run this command inside a project created by the SymPress CLI.');
        }

        return $projectDir;
    }

    /**
     * @return array{0: DefaultTemplateCatalog, 1: DefaultProfileCatalog, 2: DefaultPackageCatalog}
     */
    private function catalogs(InputInterface $input, ProjectMetadata $metadata): array
    {
        $templates = new DefaultTemplateCatalog();
        $profiles = new DefaultProfileCatalog();
        $packages = new DefaultPackageCatalog();
        $explicitManifest = $this->loadExplicitManifest($input);

        if ($explicitManifest !== null) {
            [$templates, $profiles, $packages] = $this->applyManifest(
                $explicitManifest,
                $templates,
                $profiles,
                $packages,
            );
        }

        if ((bool) $input->getOption('no-remote-manifest')) {
            return [$templates, $profiles, $packages];
        }

        $remoteManifest = $this->manifestLoader->loadFromRepository(
            $metadata->templateRepositoryUrl,
            $this->manifestRef($input),
        );

        if ($remoteManifest === null) {
            return [$templates, $profiles, $packages];
        }

        return $this->applyManifest($remoteManifest, $templates, $profiles, $packages);
    }

    private function level(InputInterface $input, SymfonyStyle $io): string
    {
        $level = $this->stringOption($input, 'level');

        if ($level !== null) {
            return $level;
        }

        if (!$input->isInteractive()) {
            return 'patch';
        }

        return (string) $io->choice('Update level', ['patch', 'minor', 'major'], 'patch');
    }

    private function targetType(
        InputInterface $input,
        SymfonyStyle $io,
        string $level,
        ProjectMetadata $metadata,
        DefaultProfileCatalog $profiles,
    ): ?string {
        $type = $this->stringOption($input, 'type');

        if ($type !== null || $level === 'patch' || !$input->isInteractive()) {
            return $type;
        }

        return (string) $io->choice(
            'Target project type',
            array_map(static fn ($profile): string => $profile->id, $profiles->all()),
            $metadata->profileId,
        );
    }

    private function targetTemplate(InputInterface $input, string $level, ProjectMetadata $metadata): ?string
    {
        $template = $this->stringOption($input, 'template');

        if ($template !== null || $level === 'major') {
            return $template;
        }

        return $metadata->templateId;
    }

    private function loadExplicitManifest(InputInterface $input): ?RepositoryManifest
    {
        $source = $this->stringOption($input, 'manifest');

        if ($source === null) {
            return null;
        }

        return $this->manifestLoader->loadRequired($source, $this->manifestRef($input));
    }

    /**
     * @return array{0: DefaultTemplateCatalog, 1: DefaultProfileCatalog, 2: DefaultPackageCatalog}
     */
    private function applyManifest(
        RepositoryManifest $manifest,
        DefaultTemplateCatalog $templates,
        DefaultProfileCatalog $profiles,
        DefaultPackageCatalog $packages,
    ): array {
        if ($manifest->isEmpty()) {
            return [$templates, $profiles, $packages];
        }

        return [
            $templates->withTemplates($manifest->templates),
            $profiles->withProfiles($manifest->profiles),
            $packages->withSuggestions($manifest->packageSuggestions),
        ];
    }

    private function manifestRef(InputInterface $input): string
    {
        return $this->stringOption($input, 'manifest-ref') ?: 'main';
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function stringArgument(InputInterface $input, string $name): ?string
    {
        $value = $input->getArgument($name);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function absolutePath(string $path, string $cwd): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
            return rtrim($path, '/');
        }

        return rtrim($cwd, '/') . '/' . trim($path, '/');
    }
}
