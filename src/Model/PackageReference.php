<?php

declare(strict_types=1);

namespace SymPress\Cli\Model;

use InvalidArgumentException;

final readonly class PackageReference
{
    private const NAME_PATTERN = '/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/';

    public function __construct(
        public string $name,
        public string $constraint = '*',
        public ?string $repositoryUrl = null,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid Composer package name.', $name));
        }
    }

    public static function fromComposerString(string $package): self
    {
        $package = trim($package);

        if ($package === '') {
            throw new InvalidArgumentException('Composer package cannot be empty.');
        }

        if (!str_contains($package, ':')) {
            return new self($package);
        }

        [$name, $constraint] = explode(':', $package, 2);

        return new self(trim($name), trim($constraint) ?: '*');
    }
}
