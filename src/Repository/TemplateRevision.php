<?php

declare(strict_types=1);

namespace SymPress\Cli\Repository;

use RuntimeException;
use Symfony\Component\Process\Process;

final class TemplateRevision
{
    public function resolve(string $repository, string $version): string
    {
        if (preg_match('/^[a-f0-9]{40}$/D', $version) === 1) {
            return $version;
        }
        $ref = str_starts_with($version, 'dev-') ? substr($version, 4) : preg_replace('/\.x-dev$/', '', $version);
        if (!is_string($ref) || $ref === '' || str_starts_with($ref, '-') || str_contains($ref, "\0")) {
            throw new RuntimeException('Template version must identify an explicit Git ref.');
        }
        $process = new Process(['git',
            'ls-remote',
            '--',
            $repository,
            'refs/heads/' . $ref,
            'refs/tags/' . $ref,
            'refs/tags/' . $ref . '^{}',
            'refs/tags/v' . $ref,
            'refs/tags/v' . $ref . '^{}']);
        $process->setWorkingDirectory(sys_get_temp_dir());
        $process->setEnv(['GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_INDEX_FILE' => false]);
        $process->mustRun();
        $matches = [];
        preg_match_all('/^([a-f0-9]{40})\s+([^\r\n]+)$/m', $process->getOutput(), $matches, PREG_SET_ORDER);
        if ($matches === []) {
            throw new RuntimeException('Selected template ref does not exist; no main fallback is allowed.');
        }
        $revisions = [];
        foreach ($matches as $match) {
            $revisions[$match[2]] = $match[1];
        }
        foreach ($revisions as $name => $sha) {
            if (str_ends_with($name, '^{}')) {
                unset($revisions[substr($name, 0, -3)]);
            }
        }
        if (count(array_unique($revisions)) !== 1) {
            throw new RuntimeException('Template ref is ambiguous between different Git revisions.');
        }
        return (string) reset($revisions);
    }
}
