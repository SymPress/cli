# Changelog

## 0.1.2 — 2026-10-09

- Require Symfony Process 8.0.5 or newer to exclude CVE-2026-24739 from consumer dependency resolution.

## 0.1.1 — 2026-10-06

- Use stable Starter 1.1.8 for new projects instead of the removed development branch.
- Keep the requested version and verified commit fixed while loading repository catalogs. A catalog's default version is metadata; its repository and package identity must still match.
- Cover default and explicitly selected tagged templates without creating files during dry runs.
