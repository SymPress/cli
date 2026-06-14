<?php

declare(strict_types=1);

namespace SymPress\Cli\Model;

final readonly class ProjectProfile
{
    public function __construct(
        public string $id,
        public string $label,
        public string $description,
        public string $exampleName,
    ) {
    }
}
