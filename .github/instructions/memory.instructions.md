---
description: Session handoff for webware-console — spec-kit state, CLI-runtime architecture, command-discovery contract, and next steps as of 2026-08-30.
applyTo: '**/*'
---

# Webware Console Memory

Tagline: Generic CLI host — owns the Symfony runtime + lazy command discovery, stays migration-agnostic. Spec-kit scaffold + constitution/spec/plan/tasks MERGED; US1–US4 + polish T001–T026 DONE (100% line + mutation coverage). Discovery via `ConsoleInterface::class` key → `ContainerCommandLoader` (lazy).

## Component

- Package: `webware/webware-console` — the Webware CLI provider. Owns the Symfony Console
  runtime (Application, `bin/` entry, config/container bootstrap) and presents/invokes commands;
  does NOT reimplement command logic.
- Namespace: `Webware\Console\`. PHP `~8.4.1 || ~8.5.0`. Dev: `webware/webware-tools`.
- Runtime deps (the point of this package): `symfony/console`, `laminas/laminas-config-aggregator`,
  `laminas/laminas-servicemanager`, `php-standard-library/terminal` (TUI).
- The old `tyrsson/console` PoC is reference-only; NOT copied into this repo.

## Spec-kit state (specify 1.0.1)

- Scaffold committed; `.specify/`, `.github/skills/`, and `specs/` ARE tracked.
  `.specify/feature.json` gitignored. Root `.gitignore` = `/vendor/` only.
- Feature: `specs/001-console-tui/`.
  - constitution (`/speckit-constitution`) — MERGED.
  - spec (`/speckit-specify`) — MERGED (US1–US4; FR-001…FR-008).
  - plan (`/speckit-plan`) — MERGED (plan.md, research.md R-001…R-006, data-model.md,
    contracts/, quickstart.md).
  - tasks (`/speckit-tasks`) — task list generated on `tasks/console-tui`; that branch became the
    implementation branch. US1–US4 + polish T023–T026 DONE; T027–T028 open. PR #7
    (`refactor/prompt-key-dispatch`) was squash-merged into it; PR #6 (`tasks/console-tui` →
    `0.1.x`) is the open implementation PR.
  - Speckit docs aligned 2026-08-30 to the handoff decisions below
    (`ConsoleInterface::class` config key, lazy `ContainerCommandLoader` +
    `laminas-cli` merge, generic-host/one-way-dep, moved config/data skeleton,
    `MenuCommand` + `Widget\Menu` TUI).
- Workflow: each step = own branch + squash-merged PR to `0.1.x`. Conventional Commits;
  commit author must be `Joey Smith <jsmith@webinertia.net>` (never derive an identity).

## Locked architecture — THIS IS THE CLI PROVIDER (decided 2026-08-29)

- **webware-console is a generic CLI host with zero migration knowledge.** It owns the Symfony
  runtime (Application, `bin/` entry, config skeleton) and a command-discovery mechanism.
- **webware-migration keeps its own Symfony commands** (`migrate`/`status`/`rollback` in
  `Webware\Migration\Console\`) and has a **hard `require` on `webware/webware-console`** —
  that is how Symfony is present for it. Dependency direction is one-way: **migration → console**.
  Console never depends on migration.
- Components register their commands through their `ConfigProvider` under the
  `ConsoleInterface::class` config key; the console builds a Symfony `ContainerCommandLoader`
  (lazy — no command is instantiated until invoked) and a `menu` command over it (no hardcoded list).

## Command discovery contract (decided 2026-08-29, revised 2026-08-30)

- **`Webware\Console\ConsoleInterface`** is the stable discovery contract — a marker interface
  whose `::class` is the config key (name matches the component).
- **Config key = `ConsoleInterface::class`**, shape matching mezzio-tooling:

  ```php
  Webware\Console\ConsoleInterface::class => [
      'commands' => [
          'migrate'  => Some\Command::class,
          'status'   => Some\Command::class,
          'rollback' => Some\Command::class,
      ],
  ],
  ```

- **`Webware\Console\Container\CommandLoaderFactory`** reads `config[ConsoleInterface::class]['commands']`
  **and merges in `config['laminas-cli']['commands']`** (mezzio-tooling's key) so mezzio-tooling
  commands are discovered for free — ConfigAggregator already merges the `laminas-cli` key.
  It returns Symfony's **`ContainerCommandLoader`** (lazy: commands resolve from the shared PSR
  container only when invoked — the same mechanism laminas-cli uses). Duplicate names throw
  `Webware\Console\Exception\DuplicateCommandException`.
  (Do NOT register our commands under `laminas-cli`; we use our own key and merge theirs in the factory.)

- **`Webware\Console\Container\ApplicationFactory`** builds the Symfony `Application`, sets the
  loader, and adds the host's own `menu` command (`Webware\Console\Menu\MenuCommand`, a
  `Widget\Menu`-driven TUI that lists `$loader->getNames()` and resolves only the selected command).

## CLI runtime skeleton — MOVED HERE from webware-migration (2026-08-29)

- `config/` + `data/` were created in webware-migration by mistake, then moved here:
  - `config/autoload/dependencies.global.php`, `config/autoload/global.php`,
    `config/autoload/.gitignore` (ignores `local.php`, `*.local.php`)
  - `config/config.php` (ConfigAggregator over providers + autoload dir + cache),
    `config/container.php` (ServiceManager build), `config/development.config.php.dist`
  - `data/cache/.gitkeep`
- They still reference a placeholder `App\ConfigProvider` and need adapting to
  `Webware\Console\` providers + Symfony Application init.
- Runtime is Mezzio-style (ConfigAggregator + ServiceManager) but **NOT mezzio/mezzio itself**.
- This is why we always use config-aggregator + servicemanager: free config merge + overridable
  dev config (`*.local.php`) for every component that registers commands.

## bin entry requirements

- `#!/usr/bin/env php` shebang, executable (`chmod +x`).
- Build DB connections via the php-db DSN array (`['dsn' => ...]`), never raw `PDO`.

## Hard requirements (all webware ecosystem work)

- **Named parameters = hard requirement.** Always use named arguments (saved to global memory).
- mago lint is the backstop; rules docs (reference for "what to prefer"):
  https://mago.carthage.software/1.47.3/en/tools/linter/rules/
- Greenfield: **no mago baselines** — resolve every lint/analyze/guard finding at source.
- PHPUnit 13: `#[CoversClass]` + `#[CoversMethod]` per class, `createStub()` for value doubles,
  `createMock()` only with `expects()`, `static::assert*`.

## Prompt key dispatch (added 2026-08-30)

- `src/Prompt/PromptKey.php` — `enum PromptKey: string` of control keys the prompt dispatches on
  (`escape`, `tab`, `down`, `up`, `enter`, `left`, `right`, `backspace`, `space`).
- `CommandInputPrompter::onKey()` dispatches via `match (PromptKey::tryFrom($event->name))`;
  `null` falls to `PromptKeyAction::character()`. `src/Prompt/PromptKeyAction.php` holds the
  extracted per-key helpers (kept `CommandInputPrompter` under mago's `too-many-methods`).
- `PromptKeyAction` private-ish helpers are tested through the public `onKey()`/render paths, not directly.

## Infection ignore config (`infection.json5.dist`)

- 8 equivalent mutants are ignored via per-mutator `ignore` patterns keyed by `Class::method::line`:
  7 guard `ReturnRemoval`s (confirm `::158/165/172`, runMenu `::123/129/135/145` — fall-through is a
  no-op for the matched key) and 1 `Minus` (input-area width, `render::83`).
- Everything else is killed by tests — no blanket mutator disables, no `@infection-ignore-all`.
- Run locally as `composer mutation-test` (infection auto-enables xdebug coverage).

## Cross-component — webware-migration relationship

- webware-migration is a pure library PLUS its own Symfony commands in `Webware\Migration\Console\`.
  Its `ConfigProvider` registers those commands under `ConsoleInterface::class` and declares
  the hard dep on this package.
- See webware-migration's handoff for the migration-side contracts (checksum, `schema_migrations`,
  message-bus commands/queries, runner, repository).

## Next actions

1. Remaining polish: T027 (README badges + usage) and T028 (`quickstart.md` end-to-end validation).
2. Done so far (all merged-ready on `tasks/console-tui`, PR #6): Phase 1–2, US1–US4,
   and polish T023–T026. Quality Gate IV is met — 100% line coverage (373/373) and
   100% mutation coverage (MSI 100: 252 generated, 249 killed, 3 static-analysis, 0 escaped,
   8 ignored equivalent mutants).
3. CI/alignment with webware-tools — later step.
4. Queued 2026-08-29: strip the "no redundant namespace prefix" clause from Principle V in
   webware-migration and the webware-tools `webware-alignment` preset constitution template.
   (webware-console's own constitution clause stripped here — version 1.1.0.)
