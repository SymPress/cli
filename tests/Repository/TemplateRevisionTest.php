<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Repository;

use PHPUnit\Framework\TestCase;
use SymPress\Cli\Catalog\DefaultProfileCatalog;
use SymPress\Cli\Generator\ProjectGenerator;
use SymPress\Cli\Model\ProjectConfiguration;
use SymPress\Cli\Model\TemplateDefinition;
use SymPress\Cli\Repository\RepositoryManifestLoader;
use SymPress\Cli\Repository\TemplateRevision;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class TemplateRevisionTest extends TestCase
{
    public function testReleaseManifestAndMaterializedTemplateUseSameCommitWhileMainDiffers(): void
    {
        $directory = sys_get_temp_dir() . '/cli-revision-' . bin2hex(random_bytes(5));
        $repository = $directory . '/repository';
        mkdir($repository . '/.sympress', 0700, true);
        $git = static function (array $arguments) use ($repository): string {
            $process = new Process(['git', '-C', $repository, ...$arguments]);
            $process->setEnv(['GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_INDEX_FILE' => false]);
            $process->mustRun();
            return trim($process->getOutput());
        };
        try {
            $git(['init', '-b', 'main']);
            $git(['config', 'user.name', 'Review fixture']);
            $git(['config', 'user.email', 'fixture@example.test']);
            file_put_contents($repository . '/composer.json', '{"name":"fixture/template","require":{"php":"^8.5"}}');
            file_put_contents($repository . '/release.txt', 'release snapshot');
            file_put_contents($repository . '/.sympress/cli.json', '{"schemaVersion":1,"profiles":[]}');
            $git(['add', '.']);
            $git(['commit', '-m', 'Release']);
            $sha = $git(['rev-parse', 'HEAD']);
            $git(['tag', 'v1.2.3']);
            file_put_contents($repository . '/release.txt', 'mutable main snapshot');
            file_put_contents($repository . '/.sympress/cli.json', '{"schemaVersion":999}');
            $git(['add', '.']);
            $git(['commit', '-m', 'Different main']);
            self::assertSame($sha, (new TemplateRevision())->resolve($repository, '1.2.3'));
            self::assertNotNull((new RepositoryManifestLoader())->loadFromRepository($repository, $sha));
            $configuration = new ProjectConfiguration(
                template: new TemplateDefinition(
                    'fixture',
                    'Fixture',
                    'fixture/template',
                    $repository,
                    'Fixture',
                    '1.2.3',
                ),
                profile: (new DefaultProfileCatalog())->default(),
                directory: $directory . '/project',
                projectName: 'Test',
                projectSlug: 'test',
                composerPackageName: 'acme/test',
                ddevTld: 'ddev.site',
                wpAdminUsername: 'admin',
                wpAdminPassword: 'uniquePasswordSentinel',
                templateRevision: $sha,
            );
            $output = new BufferedOutput();
            $style = new SymfonyStyle(new ArrayInput([]), $output);
            self::assertSame(0, (new ProjectGenerator())->generate($configuration, $style));
            self::assertSame('release snapshot', file_get_contents($directory . '/project/release.txt'));
            $metadata = (string) file_get_contents($directory . '/project/.sympress/project.json');
            self::assertStringContainsString($sha, $metadata);
            self::assertStringNotContainsString('uniquePasswordSentinel', $output->fetch());
            self::assertDirectoryDoesNotExist($directory . '/project/.git');
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    public function testSnapshotManifestCannotRedirectSelectedRepository(): void
    {
        $root = sys_get_temp_dir() . '/cli-redirect-' . bin2hex(random_bytes(5));
        $repository = $root . '/source';
        mkdir($repository . '/.sympress', 0700, true);
        $git = static function (array $arguments) use ($repository): string {
            $process = new Process(['git', '-C', $repository, ...$arguments]);
            $process->setEnv(['GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_INDEX_FILE' => false]);
            $process->mustRun();
            return trim($process->getOutput());
        };
        try {
            $git(['init', '-b', 'main']);
            $git(['config', 'user.name', 'Review fixture']);
            $git(['config', 'user.email', 'fixture@example.test']);
            file_put_contents($repository . '/composer.json', '{"name":"fixture/template"}');
            $manifest = json_encode(['schemaVersion' => 1, 'templates' => [[
                'id' => 'fixture', 'label' => 'Fixture', 'packageName' => 'fixture/template',
                'repositoryUrl' => 'https://example.test/redirect',
                'description' => 'Redirect', 'defaultVersion' => 'main',
            ]]], JSON_THROW_ON_ERROR);
            file_put_contents($repository . '/.sympress/cli.json', $manifest);
            $git(['add', '.']);
            $git(['commit', '-m', 'Fixture']);
            $configuration = new ProjectConfiguration(
                template: new TemplateDefinition(
                    'fixture',
                    'Fixture',
                    'fixture/template',
                    $repository,
                    'Fixture',
                    'main',
                ),
                profile: (new DefaultProfileCatalog())->default(),
                directory: $root . '/project',
                projectName: 'Test',
                projectSlug: 'test',
                composerPackageName: 'acme/test',
                ddevTld: 'ddev.site',
                wpAdminUsername: 'admin',
                wpAdminPassword: 'secret',
                templateRevision: $git(['rev-parse', 'HEAD']),
            );
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('cannot redirect');
            (new ProjectGenerator())->generate(
                $configuration,
                new SymfonyStyle(new ArrayInput([]), new BufferedOutput()),
            );
        } finally {
            (new Filesystem())->remove($root);
        }
    }

    public function testRemoteManifestHasNoMutableMainFallback(): void
    {
        $this->expectException(\RuntimeException::class);
        (new RepositoryManifestLoader())->loadFromRepository('https://github.com/example/template');
    }
}
