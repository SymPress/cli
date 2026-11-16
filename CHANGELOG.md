# Changelog

## 0.3.0 — 2026-11-16

- Security: `project:create` with a remote JSON `--manifest` URL and setup enabled now requires `--manifest-sha256`, so the reviewed manifest (setup command, template repository, package repositories) cannot be swapped before the real run. Local manifest files are unaffected.
- Security: `project:update` lists new Composer repositories in its plan and only runs `composer update` with `--allow-template-execution` when a template or manifest catalog adds a VCS repository outside the CLI's built-in catalog. `--no-install` still only patches `composer.json`.
- Security: `agents:md` sends `GITHUB_TOKEN` only to `api.github.com` and no longer forwards it on redirects.
- CI: pin `sympress/workflows` to v1.3.2.

## 0.2.0 — 2026-11-15

- Security: check out starter templates with `core.symlinks=false`. A malicious template could previously use symlinks such as `.env -> ../.bash_aliases` to make `project:create` write outside the project directory, even with `--no-setup`.
- Add `sympress agents:md`: copies the `docs/` folders of the installed SymPress packages, at the commits in `composer.lock`, into `.sympress-docs/` and indexes them in `AGENTS.md` or `CLAUDE.md` for coding agents.

## 0.1.4 — 2026-11-06

- Use the Symfony 8.1.11 security patches released on November 4.

## 0.1.3 — 2026-10-25

- Complete the `directory` argument of `project:create` and `project:update` (and their aliases) with the native Symfony Console 8.2 directory suggestions. Files are not suggested, and Console 8.0 and 8.1 behave as before.
- Pass the Git environment isolation of the Process-based helpers through the constructor, so static analysis passes with Symfony Process 8.0 as well.

## 0.1.2 — 2026-10-09

- Require Symfony Process 8.0.5 or newer to exclude CVE-2026-24739 from consumer dependency resolution.

## 0.1.1 — 2026-10-06

- Use stable Starter 1.1.8 for new projects instead of the removed development branch.
- Keep the requested version and verified commit fixed while loading repository catalogs. A catalog's default version is metadata; its repository and package identity must still match.
- Cover default and explicitly selected tagged templates without creating files during dry runs.
