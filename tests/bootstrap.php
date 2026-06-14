<?php

declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoloadCandidates as $candidate) {
    if (is_readable($candidate)) {
        require_once $candidate;

        spl_autoload_register(static function (string $class): void {
            $prefixes = [
                'SymPress\\Cli\\Tests\\' => __DIR__ . '/',
                'SymPress\\Cli\\' => __DIR__ . '/../src/',
            ];

            foreach ($prefixes as $prefix => $directory) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }

                $relativeClass = substr($class, strlen($prefix));
                $path = $directory . str_replace('\\', '/', $relativeClass) . '.php';

                if (is_readable($path)) {
                    require_once $path;
                }
            }
        });

        return;
    }
}

fwrite(STDERR, "Composer autoload file not found. Run composer install first.\n");
exit(1);
