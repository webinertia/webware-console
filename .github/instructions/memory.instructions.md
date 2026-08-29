---
description: Session handoff for webware-console — spec-kit state, architecture decisions, and next steps as of 2026-08-29.
applyTo: '**/*'
---

# Webware Console Memory

Tagline: Full spec-kit scaffold + constitution/spec/plan all MERGED. Next: `/speckit-tasks`, then implement.

## Component

- Package: `webware/webware-console` — text user interface (menu + better help) for Webware
  and Mezzio CLI commands. Presents/invokes commands; does NOT reimplement command logic.
- Namespace: `Webware\Console\`. PHP `~8.4.1 || ~8.5.0`. Dev: `webware/webware-tools`.
- The old `tyrsson/console` PoC is reference-only; NOT copied into this repo.

## Spec-kit state (specify 1.0.1)

- Scaffold committed; `.specify/`, `.github/skills/`, and `specs/` ARE tracked.
  `.specify/feature.json` gitignored. Root `.gitignore` = `/vendor/` only.
- Feature: `specs/001-console-tui/`.
  - constitution (`/speckit-constitution`) — MERGED.
  - spec (`/speckit-specify`) — MERGED (US1–US4; FR-001…FR-008).
  - plan (`/speckit-plan`) — MERGED (plan.md, research.md R-001…R-006, data-model.md,
    contracts/, quickstart.md).
  - tasks (`/speckit-tasks`) — NOT STARTED. Next step.
- Workflow: each step = own branch + squash-merged PR to `0.1.x`. Conventional Commits;
  commit author must be `Joey Smith <jsmith@webinertia.net>` (never derive an identity).

## Locked architecture decisions

- TUI stack: php-standard-library/terminal (`Psl\Terminal`/`Psl\Ansi`/`Psl\Type`) + Symfony
  Console `Command` as the command contract components expose.
- Runtime command discovery from component-registered commands (no hardcoded list);
  duplicate names surfaced; an empty catalog is valid.
- Keyboard-driven menu (up/down/enter/quit) with a filter; help derived from the command definition.
- In-process invocation; output + exit status shown; menu returns after run/fail.
- mezzio-tooling integration DEFERRED (constitution: long-term; v1 = Symfony-style commands).

## Cross-component

- Surfaces `webware/webware-migration`'s CLI commands (`migrate`/`status`/`rollback`).
- See webware-migration's handoff for the migration-side contracts (checksum, `schema_migrations`, etc.).

## Next actions

1. `/speckit-tasks` for `001-console-tui` (branch + PR).
2. `/speckit-implement` ⇄ `/speckit-converge`.
3. CI/alignment with webware-tools — later step.
4. Queued 2026-08-29: strip the "no redundant namespace prefix" clause from Principle V here, in webware-migration, and in the webware-tools `webware-alignment` preset constitution template.
