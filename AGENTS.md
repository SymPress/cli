# SymPress CLI agent contract

## Purpose and boundaries

This standalone CLI creates and updates SymPress projects without booting WordPress or the kernel. Repository manifests and process execution are trust boundaries: reject malformed input and preserve dry-run controls.

## Read first

- `src/Command`: user-facing create/update options and exit behavior.
- `src/IO/ProjectQuestionnaire.php`: interactive/default selection flow.
- `src/Repository`: local and remote manifest loading and strict parsing.
- `schema/repository-manifest.schema.json`: canonical starter-owned manifest contract.
- `src/Generator` and `src/Update`: filesystem mutation, subprocess and update-plan boundaries.

## Verification

- Fast: `composer tests -- --filter <changed flow>`.
- Full: `composer qa` (PHPCS, PHPStan and PHPUnit).
- Exercise mutation changes through `--dry-run`, `--no-setup`, `--no-install` or a temporary directory first.

## Invariants

- Keep Symfony Process commands as argv arrays; never execute manifest strings through a shell.
- A manifest requires `schemaVersion: 1`; malformed entries fail instead of disappearing.
- Treat `setupCommand` from remote manifests as executable supply-chain input.
- Preserve conservative patch/minor/major update rules and plan-before-apply behavior.
- The CLI remains independent of a WordPress or kernel runtime.

## Cross-repository impact

`SymPress/starter` owns the default repository manifest consumed by this CLI. Coordinate schema or default setup-command changes with that repository and keep the fallback catalogs compatible for offline use.

## Definition of done

Schema, parser, README example and starter manifest agree; dry-run and failure paths are covered; `composer qa` passes; no test writes outside its temporary directory.
