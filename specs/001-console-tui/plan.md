# Implementation Plan: Console TUI

**Branch**: `001-console-tui` | **Date**: 2026-08-28 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-console-tui/spec.md`

## Summary

Provide a text user interface for the Webware component stack: a
keyboard-navigable menu of CLI commands, per-command help, and command
invocation with output and exit status. Commands are discovered from installed
Webware components and Mezzio at runtime. Built in PHP with a terminal/ANSI
rendering stack and Symfony Console as the command contract.

webware-console is a **generic CLI host with zero migration knowledge**: it owns
the Symfony runtime (Application, `bin/` entry, config/container bootstrap) and
command discovery. webware-migration keeps its own Symfony commands and has a
hard `require` on this package — the dependency direction is one-way
(**migration → console**); components register commands through their
`ConfigProvider`.

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
- **III. Consumer-Agnostic** — PASS: generic CLI host with zero migration knowledge; components register commands via their `ConfigProvider`; one-way dependency (migration → console); no application hard-coupling.
- **IV. Webware Quality Gates** — PASS: PHPUnit 13 strict mode, Mago gates, Infection coverage are planned in.
- **V. Naming & Compatibility** — PASS: `Webware\Console\` namespace; PHP ~8.4.1 || ~8.5.0.

No violations requiring justification.

## Design Decisions — command input machinery (issue #31)

Added 2026-09-25. Implements FR-009…FR-013. The defects these address are recorded in issue #31;
this section is the design record for the fixes.

### Prompt layout and the key contract

Fields occupy rows `y … y + count - 1` and the footer is drawn at `y + count + 1`, so **row
`y + count` is already free**. That row becomes the status row — no new widget kinds, no extra
rows, and no change to the frame size.

```text
y + 0           > field 0: <value>
…
y + count - 1   > field n-1: <value>
y + count       status row — refusal message, else the active field's description
y + count + 1   footer — key hints
```

| Key | Action |
|---|---|
| `Tab`, `Down` | next field (wraps) |
| `Up` | previous field (wraps) |
| `Space` | toggle a flag field |
| `Left`, `Right`, `Backspace`, printable | edit the active text field |
| `Enter` | submit — refused if any required field is blank |
| `Esc` | cancel, back to the menu |

Enter no longer advances the active index; navigation keeps its own keys, so the overload
disappears. Nothing becomes unreachable — `PromptState::$activeIndex` already clamps modulo the
field count.

### Required-field validation in the submit path

Only arguments can be required: `CommandInputPrompter::prompt()` reads `$argument->isRequired()`
for arguments and hard-codes `required: false` for options. Validation is therefore "every field
with `required === true` holds a non-empty value", evaluated when Enter is pressed, before
`toParameters()` runs.

On refusal the console MUST NOT run the command and MUST NOT pass `''`. Today a blank required
argument is passed as an empty string, which Symfony accepts as a supplied value, so the failure
surfaces deep inside the command instead of the prompt naming what is missing — `mezzio:handler:create`
would generate a class named the empty string. The state records the offending field names for
the status row and moves the active index to the first of them; every value already entered is
preserved.

`toParameters()` keeps omitting blank *optional* fields — Symfony then applies its own default,
which is the existing and correct behaviour.

### Active-field help text

`TextInput::placeholder()` is documented as *"text shown when value is empty"*. A description
handed to it disappears the moment the field carries a value — precisely the case a declared
default creates, since defaults are read from the definition and pre-filled into the field. The
description therefore moves off the placeholder entirely and onto the status row, rendered for the
active field whatever its value. A pending refusal message takes precedence over it, so one row
carries both meanings and the form does not grow.

### Wrapped commands that ask their own questions

`CommandRunner` runs the command with a `BufferedOutput`. `QuestionHelper` writes the question to
the *output* and then blocks reading STDIN, so the question lands in a buffer that is never
flushed while the terminal waits — the console looks hung with the question invisible. Terminal
state is not the cause: `Psl\Terminal\Application::run()` restores raw mode and resets the
alternate screen in its `finally`, as part of the TUI teardown, so the terminal is already normal
by the time the command runs.

**Decision — hand the command its terminal back while still capturing.** The runner writes the
command's formatted output to STDOUT *and* accumulates the same bytes for the result screen.
`MirroringOutput` extends `BufferedOutput` and calls `parent::doWrite()` (byte-identical capture)
before writing the same already-formatted message to the stream. Decoration stays off, so the
captured text is unchanged from today.

Consequences:

- every wrapped command keeps its captured output and `CommandRunner::run()` keeps its
  `array{status: int, output: string}` contract (FR-013);
- a command that asks questions has them shown and answerable, with no change to the command and
  none in webware-usermanager (FR-012) — `user:init-db` keeps working exactly as written;
- Symfony's own question features work unmodified rather than being reimplemented:
  `setHidden(true)` password masking, `ChoiceQuestion` defaults, validators, autocomplete;
- the operator sees the command's output live and then again on the result screen. That
  duplication is the accepted cost of a design that cannot know in advance whether a command will
  prompt.

**Why not a TUI-native question helper.** `Application::getDefaultHelperSet()` does register
`QuestionHelper` under the name `question`, and `Command::getHelper()` resolves through the
Application's `HelperSet`, so substituting our own helper would intercept commands that call
`$this->getHelper('question')`. It cannot be sufficient, however: `user:init-db` constructs its
own (`$helper = new QuestionHelper();`, `InitDbCommand.php:143`), and any third-party command may
do the same. Reimplementing choice/hidden/validator semantics in the TUI would also trade tested
library behaviour for bespoke code that silently degrades on any question kind we did not cover.
It remains available later as a presentation refinement layered on top of this floor.

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
├── ConsoleInterface.php             # discovery contract — config key (marker)
├── Menu/
│   ├── Menu.php                     # keyboard navigation state over command names
│   ├── MenuRenderer.php             # Widget\Menu rendering of the menu
│   └── MenuCommand.php              # Symfony 'menu' command (TUI entry)
├── Help/
│   └── HelpFormatter.php            # render purpose/arguments/options
├── Prompt/
│   ├── CommandInputPrompter.php     # collect inputs for a selected command
│   ├── PromptField.php              # one argument/option as editable state
│   ├── PromptKeyAction.php          # per-key behaviour, incl. the submit/validate step
│   └── PromptState.php              # active field, submission, cancellation, missing-required list
├── Runner/
│   ├── CommandRunner.php            # invoke command, capture output + status
│   └── MirroringOutput.php          # write output through to the terminal and capture it
├── Container/                       # CommandLoaderFactory, ApplicationFactory, MenuCommandFactory
├── Exception/                       # DuplicateCommandException
└── ConfigProvider.php               # DI wiring

bin/
└── webware                         # Symfony Application + menu launch entry

config/                              # ConfigAggregator + ServiceManager skeleton (moved from webware-migration)
├── autoload/
│   ├── dependencies.global.php
│   └── global.php
├── config.php
├── container.php
└── development.config.php.dist

data/
└── cache/

test/
├── unit/
└── integration/
```

**Structure Decision**: Single CLI package (`src/` + `test/` + `bin/` + `config/`).
Discovery is lazy — components register commands under the `ConsoleInterface::class`
config key, and `CommandLoaderFactory` merges `config['laminas-cli']['commands']`
into a Symfony `ContainerCommandLoader` (no command is instantiated until invoked).
The menu/help/prompt/runner are presentation and invocation layers. The runner writes a command's
output through to the terminal as well as capturing it, so a command that asks questions of its
own stays usable from the console. No persistence layer — the TUI is stateless.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No violations — this section is intentionally empty.
