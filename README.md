# SymPress CLI

[![QA](https://github.com/SymPress/cli/actions/workflows/qa.yml/badge.svg)](https://github.com/SymPress/cli/actions/workflows/qa.yml)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

Standalone Symfony Console CLI for creating configured SymPress projects from
Composer starter templates such as `sympress/starter`.

This package is intentionally independent from a website installation. It does
not boot WordPress, does not require the SymPress kernel and does not need a
starter project around it. The only runtime dependencies are PHP, Composer and
Symfony components declared in this package.

The goal is similar to Symfony Maker's guided flow, but for a complete project:
choose a starter, choose the project type, fill the important first-run values,
review package suggestions, then let the wizard create and configure the project.

## Install

```bash
composer global require sympress/cli
```

After a global Composer install, make sure Composer's global `vendor/bin`
directory is in your shell `PATH`, then run:

```bash
sympress --help
```

Install directly from GitHub before a Packagist release:

```bash
composer global config repositories.sympress-cli vcs https://github.com/SymPress/cli
composer global require sympress/cli:dev-main
sympress --help
```

Clone and run locally:

```bash
git clone https://github.com/SymPress/cli.git
cd cli
composer install
bin/sympress --help
```

From the local SymPress workspace:

```bash
cd cli
composer install
bin/sympress --help
```

## Create A Project

Interactive:

```bash
sympress new
```

With a target directory:

```bash
sympress new my-site
```

Non-interactive examples:

```bash
sympress new customer-portal \
  --type=app \
  --name="Customer Portal" \
  --package-name=acme/customer-portal
```

```bash
sympress new content-sync-service \
  --type=microservice \
  --no-setup \
  --package=sympress/orm \
  --package=sympress/migration
```

```bash
sympress new shop-platform \
  --type=commerce \
  --package=wpackagist-plugin/woocommerce
```

Use a repository-provided manifest:

```bash
sympress new my-site --manifest=https://github.com/SymPress/starter
```

The CLI also tries to discover a manifest in the selected template repository by
default. Use `--no-remote-manifest` for offline or fully pinned runs.

## Update A Project

Projects created by the CLI contain `.sympress/project.json`. Run `sympress
update` anywhere inside that project to update it later:

```bash
sympress update --level=patch
```

Update levels are intentionally conservative:

- `patch`: refresh metadata and missing package recommendations for the current
  project type.
- `minor`: allow changing the project type, for example from `microservice` to
  `app` or `website`; missing recommendations for the new type are added.
- `major`: allow project type changes and starter template/repository changes.

Examples:

```bash
sympress update --level=minor --type=app
sympress update --level=minor --type=website --no-install
sympress update --level=major --template=sympress-starter --type=website
```

By default the update command patches `composer.json`, updates
`.sympress/project.json` and runs `composer update` for newly added packages. Use
`--dry-run` to inspect the plan and `--no-install` to skip Composer execution.

## What It Does

1. Runs `composer create-project sympress/starter <dir> --no-install`.
2. Writes project metadata and selected package suggestions to `composer.json`.
3. Creates `.env` from `.env.example` and fills first-run values:
   `WP_HOME`, `WP_SITEURL`, `WP_ADMIN_USERNAME`, `WP_ADMIN_PASSWORD`,
   `DDEV_PROJECT_NAME` and `DDEV_PROJECT_TLD`.
4. Writes `.sympress/project.json` so future update runs know the project type,
   starter template and repository.
5. Runs the starter setup command, unless `--no-setup` is passed.

## Project Types

The default catalog ships with these profiles:

- `website`: editorial site with assets, consent and cache suggestions.
- `app`: portal or application with ORM, migrations, events and mail.
- `microservice`: lean service boundary with events, mail, ORM and migrations.
- `commerce`: WooCommerce-oriented project with operational tooling.

## Useful Options

```bash
sympress project:create [directory] [options]
```

- `--template=sympress-starter`
- `--type=website|app|microservice|commerce`
- `--name="Acme Website"`
- `--project-slug=acme-website`
- `--package-name=acme/website`
- `--package=vendor/name[:constraint]`
- `--dev-package=vendor/name[:constraint]`
- `--no-suggested-packages`
- `--repository=https://github.com/SymPress/starter`
- `--template-version=1.0.x-dev`
- `--manifest=./sympress-cli.json`
- `--manifest=https://github.com/SymPress/starter`
- `--manifest-ref=main`
- `--no-remote-manifest`
- `--no-setup`
- `--dry-run`

Update options:

- `--level=patch|minor|major`
- `--type=website|app|microservice|commerce`
- `--template=sympress-starter`
- `--repository=https://github.com/SymPress/starter`
- `--template-version=1.0.x-dev`
- `--manifest=./sympress-cli.json`
- `--manifest-ref=main`
- `--no-remote-manifest`
- `--no-install`
- `--dry-run`

## Repository Manifest

A starter repository can ship one of these files:

- `.sympress/cli.json`
- `.sympress-cli.json`
- `sympress-cli.json`

When the starter changes, update this manifest in the starter repository. The CLI
will pick it up on the next run, so setup commands, project types and package
suggestions can evolve with the repository instead of being hardcoded in the CLI.

Example:

```json
{
  "$schema": "https://raw.githubusercontent.com/sympress/cli/main/schema/repository-manifest.schema.json",
  "schemaVersion": 1,
  "templates": [
    {
      "id": "sympress-starter",
      "label": "SymPress Starter",
      "packageName": "sympress/starter",
      "repositoryUrl": "https://github.com/SymPress/starter",
      "description": "DDEV-ready SymPress WordPress starter.",
      "defaultVersion": "1.0.x-dev",
      "setupCommand": ["bin/console", "setup", "{project_slug}"]
    }
  ],
  "profiles": [
    {
      "id": "website",
      "label": "Website",
      "description": "Editorial WordPress site with SymPress defaults.",
      "exampleName": "acme-website"
    }
  ],
  "packageSuggestions": [
    {
      "name": "sympress/assets",
      "label": "Assets",
      "description": "Asset registration and output filtering.",
      "recommendedProfiles": ["website"]
    },
    {
      "name": "sympress/profiler",
      "label": "Profiler",
      "description": "Development profiler.",
      "dev": true,
      "recommendedProfiles": ["website"]
    }
  ]
}
```

The canonical format is defined by
[`schema/repository-manifest.schema.json`](schema/repository-manifest.schema.json).
Malformed entries fail as a whole instead of being silently ignored.

A remote manifest is trusted executable input because `setupCommand` is run as
an argv array after project creation. Remote loading requires HTTPS. Pin
`--manifest-ref` to a reviewed tag or commit for reproducible automation. Use
`--no-setup` to inspect generated files before running a repository-provided
setup command.

## Extending

The command is intentionally thin. Prefer changing repository manifests for
starter-specific behavior. Built-in fallback defaults live in these catalog
classes:

- `SymPress\Cli\Catalog\DefaultTemplateCatalog`
- `SymPress\Cli\Catalog\DefaultProfileCatalog`
- `SymPress\Cli\Catalog\DefaultPackageCatalog`

The generator layer is split into:

- `ProjectGenerator` for orchestration.
- `ComposerJsonEditor` for package and metadata changes.
- `EnvFileEditor` for first-run environment values.
- `CommandRunner` for process execution, so tests can swap it out.

## Quality Checks

```bash
composer qa
```

The repository workflow calls the shared `sympress/workflows` QA workflow. Org
level issue templates, pull request template, security policy and contribution
guidelines are provided by the SymPress `.github` repository.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
