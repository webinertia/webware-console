# Tasks: Console TUI

**Input**: Design documents from `/specs/001-console-tui/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: Included — the constitution's Quality Gates mandate 100% line + mutation coverage.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1–US4)

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Tooling and dependency baseline.

- [X] T001 Add `symfony/console`, `php-standard-library/terminal`, `laminas/laminas-config-aggregator`, and `laminas/laminas-servicemanager` to `require` in `composer.json`; keep `webware/webware-tools` in `require-dev`; run `composer update`
- [X] T002 [P] Create `phpunit.xml.dist` with `requireCoverageMetadata="true"` and strict fail flags + unit/integration testsuites
- [X] T003 [P] Create `mago.toml` extending `vendor/webware/webware-tools/mago.toml`

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The command contract, discovery seam, and runtime skeleton every story depends on.

**⚠️ CRITICAL**: No user story work until this phase is complete.

- [X] T004 Implement the `ConsoleInterface` marker (`src/ConsoleInterface.php`) — the stable discovery contract whose `::class` is the config key
- [X] T005 Implement `CommandLoaderFactory` in `src/Container/CommandLoaderFactory.php` — build a lazy `ContainerCommandLoader` from `config[ConsoleInterface::class]['commands']`, merging `config['laminas-cli']['commands']` (mezzio-tooling's key); surface duplicate names (FR-005/FR-006)
- [X] T006 [P] Symfony `Command` classes are the contract — no adapter; resolved lazily by the loader (superseded the planned adapter)
- [X] T007 [P] Unit test `CommandLoaderFactory` (merge, duplicate detection, lazy resolution) in `test/unit/Container/`
- [X] T008 [P] Adapt the moved `config/` + `data/` skeleton to `Webware\Console\` — replace the placeholder `App\ConfigProvider` and remove phpdb providers; wire ConfigAggregator + ServiceManager bootstrap
- [X] T009 Wire the Symfony `Application` + `bin/console` entry (shebang, executable) over the config/container bootstrap

**Checkpoint**: discovery seam + runtime skeleton ready; no story blocked.

## Phase 3: User Story 1 — Browse commands via a menu (Priority: P1) 🎯 MVP

**Goal**: Launch the console and navigate a list of discovered commands.

**Independent Test**: Launch against a known command set; the menu lists all commands and is keyboard-navigable.

### Tests for User Story 1

- [X] T010 [P] [US1] Unit test menu navigation/selection in `test/unit/Menu/MenuTest.php`
- [X] T011 [US1] Integration test menu renders command names in `test/integration/Menu/MenuIntegrationTest.php`

### Implementation for User Story 1

- [X] T012 [P] [US1] Implement `Menu` in `src/Menu/Menu.php` — keyboard navigation state (up/down/quit) over the command names
- [X] T013 [P] [US1] Implement `MenuRenderer` in `src/Menu/MenuRenderer.php` — render the menu with `Psl\Terminal`/`Psl\Ansi`; render a graceful empty state when no commands are available (FR-007)
- [X] T014 [US1] Wire the `menu` command (`MenuCommand`) into the Application via `ApplicationFactory`; `bin/console` resolves the Application from the container and runs it

**Checkpoint**: US1 independently functional — menu lists and navigates commands.

## Phase 4: User Story 2 — View help for a command (Priority: P2)

**Goal**: Show a focused command's purpose, arguments, and options.

**Independent Test**: Open help on a command with known args/options; each is shown with a description.

### Tests for User Story 2

- [X] T015 [US2] Unit test help derivation in `test/unit/Help/HelpFormatterTest.php`

### Implementation for User Story 2

- [X] T016 [P] [US2] Implement `HelpFormatter` in `src/Help/HelpFormatter.php` — derive purpose/arguments/options from the command definition; group or scroll very long argument/option lists so help stays readable
- [X] T017 [US2] Wire help rendering into the menu (focus command → show help)

**Checkpoint**: US2 independently functional — help view reflects the command.

## Phase 5: User Story 3 — Run a command (Priority: P2)

**Goal**: Select a command, supply inputs, run it, see output + exit status, return to the menu.

**Independent Test**: Run a command from the menu; output and status match direct invocation.

### Tests for User Story 3

- [X] T018 [US3] Unit test prompt/run flow in `test/unit/Prompt/CommandInputPrompterTest.php` and `test/unit/Runner/CommandRunnerTest.php`

### Implementation for User Story 3

- [X] T019 [P] [US3] Implement `CommandInputPrompter` in `src/Prompt/CommandInputPrompter.php` — collect arguments/options via `Psl\Terminal`; abort cleanly back to the menu when the operator cancels input
- [X] T020 [US3] Implement `CommandRunner` in `src/Runner/CommandRunner.php` — invoke in-process, capture output + exit status, show a working indicator for long-running commands, return to menu (FR-003/FR-004/FR-008)

**Checkpoint**: US3 independently functional — run + report + return.

## Phase 6: User Story 4 — Discover commands from installed components (Priority: P3)

**Goal**: The command map reflects installed components automatically.

**Independent Test**: Change the installed component set; the menu reflects additions/removals.

### Tests for User Story 4

- [X] T021 [US4] Integration test command registration reflects installed components in `test/integration/`

### Implementation for User Story 4

- [X] T022 [US4] Implement `ConfigProvider` in `src/ConfigProvider.php` — wire the loader/Application/menu factories so components contribute commands under `ConsoleInterface::class` (FR-005)

**Checkpoint**: US4 independently functional — runtime discovery, no manual list.

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Coverage gate, CI alignment, final validation.

- [X] T023 [P] Add empty-command-set handling coverage (FR-007) and duplicate-name surfacing (FR-006)
- [X] T024 [P] Run `mago format`/`lint`/`analyze`/`guard` and fix findings at source
- [ ] T025 Run `composer test` + `composer test-integration` and close coverage gaps to 100% line + mutation
- [ ] T026 Add the reusable-workflow wrapper `.github/workflows/continuous-integration.yml` + `codecov.yml` + `infection.json5.dist` + `renovate.json`
- [ ] T027 [P] Update `README.md` with badges and usage
- [ ] T028 Run `quickstart.md` validation end-to-end

## Dependencies & Execution Order

### Phase Dependencies

- Setup → Foundational → User Stories (P1→P2→P3) → Polish.

### User Story Dependencies

- US1: after Foundational; no story deps.
- US2: after US1 (help renders from the focused command in the menu).
- US3: after US1 (run from the menu).
- US4: after Foundational; independent (command-map population).

### Within Each Story

- Tests first → models/contracts → services → wiring.

### Parallel Opportunities

- T002/T003 (setup tooling), T006/T007 (command contract + tests), T008 (config skeleton) are [P].
- US1 menu/menu-renderer (T012/T013) are [P].
- US2/US3 components are [P] within their phases.

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 + Phase 2.
2. Phase 3 (US1) — menu lists + navigates commands.
3. STOP and validate: menu renders the command names.
4. Ship/demo as MVP.

### Incremental Delivery

US1 → US2 (help) → US3 (run) → US4 (discovery) → Polish.

## Notes

- Consumer-agnostic: no hard-coupling to a specific application (constitution III).
- Generic CLI host with zero migration knowledge; webware-migration depends on this package (one-way: migration → console).
- Discovery goes through the `ConsoleInterface::class` config key; `CommandLoaderFactory` merges `config['laminas-cli']['commands']` (mezzio-tooling's key) into a lazy `ContainerCommandLoader`.
- The console presents/invokes commands; it does not reimplement command logic.
- Commit after each task or group; squash-merge PRs to `0.1.x`.
- Commit author MUST be `Joey Smith <jsmith@webinertia.net>`.
