<?php

declare(strict_types=1);

namespace SymPress\Cli\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SymPress\Cli\Command\CreateProjectCommand;
use SymPress\Cli\Command\UpdateProjectCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class DirectoryCompletionTest extends TestCase
{
    /** @return iterable<string, array{Command, list<string>, int, bool}> */
    public static function completions(): iterable
    {
        foreach ([new CreateProjectCommand(), new UpdateProjectCommand()] as $command) {
            foreach (
                [
                'empty' => [[], 0, true],
                'relative' => [['./project'], 0, true],
                'spaces' => [['/tmp/my project'], 0, true],
                'after option' => [['--dry-run', './project'], 1, true],
                'option name' => [['--te'], 0, false],
                'option value' => [['--template', 'starter'], 1, false],
                'inline option value' => [['--template=starter'], 0, false],
                ] as $case => [$tokens, $index, $directory]
            ) {
                yield $command->getName() . ' ' . $case => [$command, $tokens, $index, $directory];
            }
        }
    }

    #[DataProvider('completions')]
    public function testOnlyDirectoryArgumentRequestsNativePaths(
        Command $command,
        array $tokens,
        int $index,
        bool $directory,
    ): void {
        $input = CompletionInput::fromTokens([$command->getName(), ...$tokens], $index + 1);
        $input->bind($command->getDefinition());
        $suggestions = new CompletionSuggestions();

        $command->complete($input, $suggestions);

        self::assertSame([], $suggestions->getValueSuggestions());
        self::assertSame([], $suggestions->getOptionSuggestions());
        if (method_exists($suggestions, 'getPathSuggestion')) {
            self::assertSame($directory ? 'directories' : null, $suggestions->getPathSuggestion());
        } else {
            self::assertFalse(method_exists($suggestions, 'suggestDirectories'));
        }
    }
    /** @return iterable<string, array{string, string}> */
    public static function shells(): iterable
    {
        foreach (['bash', 'fish', 'zsh'] as $shell) {
            foreach (['1', '2'] as $version) {
                yield $shell . ' API ' . $version => [$shell, $version];
            }
        }
    }

    #[DataProvider('shells')]
    public function testShellProtocolAndInstalledScriptCompatibility(string $shell, string $version): void
    {
        $application = new Application();
        $application->addCommand(new CreateProjectCommand());
        $application->addCommand(new UpdateProjectCommand());
        $native = method_exists(CompletionSuggestions::class, 'suggestDirectories') && $version === '2';
        foreach (['project:create', 'new', 'project:update', 'upgrade'] as $name) {
            foreach ([['./my project', true], ['--template=starter', false]] as [$token, $directory]) {
                $tester = new CommandTester($application->find('_complete'));
                self::assertSame(0, $tester->execute([
                    '--shell' => $shell,
                    '--api-version' => $version,
                    '--input' => ['sympress', $name, $token],
                    '--current' => '2',
                ]), $tester->getDisplay());
                self::assertSame($native && $directory, str_contains($tester->getDisplay(), ':directories'));
            }
        }
        $tester = new CommandTester($application->find('completion'));
        self::assertSame(0, $tester->execute(['shell' => $shell]), $tester->getDisplay());
        self::assertStringNotContainsString('{{', $tester->getDisplay());
    }
}
