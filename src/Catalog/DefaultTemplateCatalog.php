<?php

declare(strict_types=1);

namespace SymPress\Cli\Catalog;

use InvalidArgumentException;
use SymPress\Cli\Model\TemplateDefinition;

final class DefaultTemplateCatalog
{
    /**
     * @var array<string, TemplateDefinition>
     */
    private array $templates;

    /**
     * @param list<TemplateDefinition> $templates
     */
    public function __construct(array $templates = [])
    {
        $defaultTemplates = [
            new TemplateDefinition(
                id: 'sympress-starter',
                label: 'SymPress Starter',
                packageName: 'sympress/starter',
                repositoryUrl: 'https://github.com/SymPress/starter',
                description: 'WordPress website starter with DDEV, WPStarter, SymPress kernel, WP-CLI'
                    . ' and quality tooling.',
            ),
        ];

        $this->templates = array_combine(
            array_map(static fn (TemplateDefinition $template): string => $template->id, $defaultTemplates),
            $defaultTemplates,
        );

        foreach ($templates as $template) {
            $this->templates[$template->id] = $template;
        }
    }

    /**
     * @param list<TemplateDefinition> $templates
     */
    public function withTemplates(array $templates): self
    {
        return new self([...$this->all(), ...$templates]);
    }

    /**
     * @return list<TemplateDefinition>
     */
    public function all(): array
    {
        return array_values($this->templates);
    }

    public function first(): TemplateDefinition
    {
        return $this->all()[0];
    }

    public function get(string $id): TemplateDefinition
    {
        if (!isset($this->templates[$id])) {
            throw new InvalidArgumentException(sprintf('Unknown template "%s".', $id));
        }

        return $this->templates[$id];
    }
}
