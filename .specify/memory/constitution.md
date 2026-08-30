<!--
Sync Impact Report
==================
Version change: 1.0.0 → 1.1.0
Modified principles: V (removed the "no redundant namespace prefix" clause — naming evolves by logical sense; a redundant-prefix class name is no longer a violation)
Added sections: none
Removed sections: none
Follow-up TODOs: none
-->

# Webware Console Constitution

## Core Principles

### I. Library-First
Every capability ships as a self-contained, independently testable library component. Components MUST have a clear, singular purpose; organizational-only or kitchen-sink packages are prohibited.

### II. TUI-First
The package provides a text user interface — a menu and better help — for CLI commands. It presents and invokes commands; it does not reimplement command logic that belongs to other components.

### III. Consumer-Agnostic
Commands are discovered and surfaced from Webware components and Mezzio at runtime; the package MUST NOT hard-couple to a specific application. Long-term it wraps mezzio-tooling rather than replacing it.

### IV. Webware Quality Gates (NON-NEGOTIABLE)
Every change MUST pass the shared webware gates: Mago format, lint, analyze, and guard with no silent suppression; PHPUnit 13 strict mode (coverage metadata, mock/stub split, `failOnNotice`/`failOnDeprecation`/`failOnWarning`); and Infection mutation coverage at or above the configured thresholds. Test doubles follow PHPUnit 13 rules — `createStub()` for value doubles, `createMock()` only with `expects()`.

### V. Naming & Compatibility
Interface names end in `Interface`; trait names end in `Trait`. Support only current supported PHP versions (`~8.4.1 || ~8.5.0`). The namespace root is `Webware\Console\`.

## Dependencies & Compatibility

- The TUI stack (e.g. Symfony Console, terminal/ANSI libraries) is decided during spec/plan, never pinned prematurely in the constitution.
- `webware/webware-tools` is a development-only dependency supplying the shared Mago configuration and CI conventions.
- VCS repository entries appear only for pre-release dev dependencies and are removed once the dependency is tagged on Packagist.

## Development Workflow

- Spec-driven development: each step (constitution, specify, plan, tasks, implement, converge) lands as its own branch and squash-merged pull request.
- Commits use Conventional Commits and carry the maintainer's real identity (`Joey Smith <jsmith@webinertia.net>`).

## Governance

- This constitution supersedes other practices; conflicts are resolved in its favor or the constitution is amended via pull request.
- Amendments require a pull request that updates the version and Last Amended date.
- Every pull request is reviewed for compliance with the Core Principles.

**Version**: 1.1.0 | **Ratified**: 2026-08-28 | **Last Amended**: 2026-08-29
