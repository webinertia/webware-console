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

Added 2026-09-25. Implements FR-009…FR-012. The defects these address are recorded in issue #31;
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
| `Enter` | execute — refused if any required field is blank |
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
would generate a class named the empty string. The state records that a submission was refused and
moves the active index to the first blank required field; the report itself is computed from the
current field values each time the row is drawn, so it shrinks as the operator fills the fields in
rather than going stale. Every value already entered is preserved.

`toParameters()` keeps omitting blank *optional* fields — Symfony then applies its own default,
which is the existing and correct behaviour.

### Active-field help text

`TextInput::placeholder()` is documented as *"text shown when value is empty"*. A description
handed to it disappears the moment the field carries a value — precisely the case a declared
default creates, since defaults are read from the definition and pre-filled into the field. The
description therefore moves off the placeholder entirely and onto the status row, rendered for the
active field whatever its value. A pending refusal message takes precedence over it, so one row
carries both meanings and the form does not grow.

### Requiredness, and the asterisk marker

Requiredness is declared on **arguments only**. Symfony's `InputOption` has no required concept at
all — its modes only describe whether a value is accepted — while `InputArgument` carries
`REQUIRED = 1`, `OPTIONAL = 2`, `IS_ARRAY = 4`. The console therefore reads requiredness from
`$argument->isRequired()` and from nowhere else, and marks every required argument with an
asterisk in its field label:

```text
> * configFile: <value>
    class: <value>
    ignore-unresolved: [ ]
```

Field labels reserve the marker column — the asterisk for a required argument, a
space otherwise — and an option's label carries its bare name, without the `--` its
input parameter is built with.

That is why `dev:mode` shows none (three `VALUE_NONE` flags, and options are never required) while
`mezzio:handler:create` shows one (`handler` is `InputArgument::REQUIRED`). The marker exists so
the operator can see what is mandatory without going back and forth to the command's docs or
code — the complaint that started this work.

A command that needs a mandatory value must declare it as a required argument. A value declared as
an option with a `?? $helper->ask(...)` fallback states its requirement in control flow, where
nothing outside the class can see it — which is exactly `user:init-db`'s situation today.

### Cross-repository change: `user:init-db`

`user:init-db` (webware-usermanager) requires first name, last name, email and password — a user
must be created — but declares all four as optional options and asks for them inside `execute()`.
The console cannot see that, so it cannot mark or validate them. The fix belongs to that component
and is its own PR there:

- declare the four as `InputArgument::REQUIRED`;
- move the prompting into `interact()`, filling the arguments with `setArgument()`.

`Command::run()` runs `bind()` → `initialize()` → **`interact()`** → **`validate()`** →
`execute()`, and `interact()`'s own docblock says it "is executed before the InputDefinition is
validated. This means that this is the only place where the command can interactively ask for
values of missing required arguments." Direct CLI use therefore keeps prompting exactly as it does
today, while the definition now tells the console the values are mandatory. `--role` keeps its
`developer` default; `--drop` stays a flag.

The console needs no new machinery for this — `*` and validate-on-Enter already read
`isRequired()`. The two changes are independent and the console work does not wait on this one.

### Out of scope: a command's own interactive questions

The console does not display a command's own interactive questions, and this work does not change
that. It wraps commands whose input the command declares; a command that asks its own questions is
left as it is.

The hazard is real but narrow, and it lives in our own components. `CommandRunner` gives the
command a `BufferedOutput`, so `QuestionHelper::writePrompt()` writes into a buffer nobody flushes
and `fgets(STDIN)` then blocks with nothing on screen. It is reachable only when a value the
command consults with `??` is neither declared nor filled — for `user:init-db`, one of the four
value options left blank. Declaring them as required arguments removes the case that matters, and
the console's form then validates them like any other required field.

Terminal state was never the cause: `Psl\Terminal\Application::run()` restores raw mode and resets
the alternate screen in its `finally`, so the terminal is normal by the time the command runs.

Two remedies were evaluated and rejected for this scope: handing the command the terminal, and
substituting the Application's `question` helper so questions render in the TUI. Neither is needed
once requiredness is declared, and helper substitution could never be sufficient on its own — it
only sees commands that call `$this->getHelper('question')`, while `user:init-db` constructs its
own.

### Implementation conventions

- **Conditional dispatch uses `match`, not chained `if` statements.** Key handling, the submit /
  refuse step and the status-row selection are each a discrete selection over a known set of
  cases, so they are written as `match` expressions. This applies to new code and to code being
  touched, and matches the precedent already set by `CommandInputPrompter::onKey()`.

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
│   └── CommandRunner.php            # invoke command, capture output + status
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
The menu/help/prompt/runner are presentation and invocation layers. No persistence
layer — the TUI is stateless.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No violations — this section is intentionally empty.
