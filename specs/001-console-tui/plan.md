# Implementation Plan: Console TUI

**Branch**: `001-console-tui` | **Date**: 2026-08-28 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-console-tui/spec.md`

## Summary

Provide a text user interface for the Webware component stack: a
keyboard-navigable menu of CLI commands, per-command help, and command
invocation with output and exit status. Commands are discovered from installed
Webware components and Mezzio at runtime. Built in PHP with a terminal/ANSI
rendering stack and Symfony Console as the command contract.

## Technical Context

**Language/Version**: PHP ~8.4.1 || ~8.5.0

**Primary Dependencies**: symfony/console (command contract), php-standard-library/terminal (ANSI/terminal rendering), webware/webware-tools (dev)

**Storage**: N/A — stateless TUI; no persistence

**Testing**: PHPUnit 13.3 (strict: coverage metadata, mock/stub split), Infection mutation testing, Mago format/lint/analyze/guard

**Target Platform**: PHP 8.4/8.5 CLI on Linux/macOS/WSL terminals

**Project Type**: cli (text user interface)

**Performance Goals**: Menu and help render sub-second for typical command counts (tens to low hundreds)

**Constraints**: Consumer-agnostic (no hard-coupling to a specific application); presents and invokes commands without reimplementing their logic

**Scale/Scope**: Library + CLI surfacing commands from the Webware component stack and Mezzio

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Library-First** — PASS: single-purpose, self-contained console component.
- **II. TUI-First** — PASS: menu + help; presents and invokes commands without reimplementing them.
- **III. Consumer-Agnostic** — PASS: runtime discovery from Webware components and Mezzio; no application hard-coupling.
- **IV. Webware Quality Gates** — PASS: PHPUnit 13 strict mode, Mago gates, Infection coverage are planned in.
- **V. Naming & Compatibility** — PASS: `Webware\Console\` namespace; no redundant prefixes; PHP ~8.4.1 || ~8.5.0.

No violations requiring justification.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
src/
├── Menu/
│   ├── Menu.php                     # keyboard-navigable menu
│   └── MenuRenderer.php             # terminal rendering of the menu
├── Catalog/
│   ├── CommandCatalog.php           # discovered commands
│   └── CommandCatalogFactory.php    # build catalog from registered commands
├── Help/
│   └── HelpFormatter.php            # render purpose/arguments/options
├── Prompt/
│   └── CommandInputPrompter.php     # collect inputs for a selected command
├── Runner/
│   └── CommandRunner.php            # invoke command, capture output + status
├── Container/                       # factories for catalog, menu, runner
└── ConfigProvider.php               # DI wiring

test/
├── unit/
└── integration/
```

**Structure Decision**: Single CLI package (`src/` + `test/`). The catalog is the
discovery boundary (commands registered by components); the menu/help/prompt/runner
are presentation and invocation layers. No persistence layer — the TUI is stateless.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No violations — this section is intentionally empty.
