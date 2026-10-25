# Changelog

## 0.1.3 — 2026-10-25

- Complete the `directory` argument of `project:create` and `project:update` (and their aliases) with the native Symfony Console 8.2 directory suggestions. Files are not suggested, and Console 8.0 and 8.1 behave as before.
- Pass the Git environment isolation of the Process-based helpers through the constructor, so static analysis passes with Symfony Process 8.0 as well.

## 0.1.2 — 2026-10-09

- Require Symfony Process 8.0.5 or newer to exclude CVE-2026-24739 from consumer dependency resolution.

## 0.1.1 — 2026-10-06

- Use stable Starter 1.1.8 for new projects instead of the removed development branch.
- Keep the requested version and verified commit fixed while loading repository catalogs. A catalog's default version is metadata; its repository and package identity must still match.
- Cover default and explicitly selected tagged templates without creating files during dry runs.
