# Implementation Plan: Windows Menu Support

**Branch**: `spec/002-windows-menu-support` | **Date**: 2026-10-05 | **Spec**: [`spec.md`](./spec.md)

**Input**: Feature specification from `specs/002-windows-menu-support/spec.md`

## Summary

The `menu` command cannot run on Windows: it enters `php-standard-library/terminal`'s raw mode, whose
Windows branch spawns PowerShell with stdin set to the NUL device, and whose helper script then calls
`GetConsoleMode` on that handle — failing with `ERROR_INVALID_HANDLE` (6) before any frame is drawn.
The library's `Stdio::tty()` type is Unix-only and throws on Windows, so the obvious mirror of its Unix
path is unavailable, and inherited stdio does not help when the process was started by Composer,
which pipes stdio on Windows.

The approach is to stop depending on that raw-mode path where it cannot work. The package gains a
platform-neutral interaction contract that the menu depends on; the existing PSL behaviour becomes one
implementation of it (unchanged on POSIX hosts), and a new Symfony Console driven implementation serves
Windows and any console-less invocation. The choice is made by an **injectable** capability decision so
the Windows path is exercisable on Linux CI, which matters because this repository's mutation floors are
100/100.

No `vendor/` patch, no fork, no new runtime dependency: everything the new implementation needs is
already provided by `symfony/console`.

## Technical Context

**Language/Version**: PHP `~8.4.1 || ~8.5.0` (repository constraint; `config.platform.php` pins 8.4.99
for resolution)

**Primary Dependencies**: `symfony/console` `^7.4 || ^8.0` (already required — supplies
`QuestionHelper`, `Question`, `ChoiceQuestion`, `ConfirmationQuestion`, `SymfonyStyle`, `StreamInput`,
`ArrayInput`); `php-standard-library/terminal` `^6.2` (installed 6.3.0 — retained, used on POSIX);
`laminas/laminas-servicemanager` `^4.5` for wiring

**Storage**: N/A

**Testing**: PHPUnit 13 strict mode (`requireCoverageMetadata`, `failOnNotice`, `failOnWarning`,
`failOnDeprecation`); Infection with `min_msi` **100** and `min_covered_msi` **100** from
`webware-ci.json`; Mago format/lint/analyze/guard in CI

**Target Platform**: CLI on Windows (Windows Terminal, PowerShell, `cmd.exe`) and POSIX terminals; also
must work with piped stdin

**Project Type**: Single library with a `bin` entry point (Composer package)

**Performance Goals**: The menu must appear without a perceptible delay; the new path performs no
subprocess spawn and no file I/O beyond reading input, so it removes the helper-process round trip the
PSL path performs on Windows.

**Constraints**: No changes to `@api` constructor contracts (`ConsoleInterface`, `App`); no vendor
patch; non-Windows behaviour byte-identical; every new branch covered by tests to satisfy MSI 100

**Scale/Scope**: One command (`menu`), one new contract with two implementations, one extracted
field-discovery collaborator, one container factory, one capability collaborator, plus tests and docs.
No change to command discovery, the help formatter, the runner, or the dev-mode command.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Assessment |
|---|---|
| **I. Library-First** | PASS. The change is additive inside one package: a small contract plus two implementations. No new package, no kitchen-sink scope; the contract's single purpose is "what the menu needs from a terminal". |
| **II. TUI-First** | PASS. The menu still presents and invokes commands and reimplements no command logic; the runner and input collection continue to delegate to Symfony Console's own metadata. |
| **III. Consumer-Agnostic** | PASS. Nothing is bound to a specific application; command discovery, the loader and the command map are untouched. |
| **IV. Webware Quality Gates (NON-NEGOTIABLE)** | PASS, with obligations: new classes need `#[CoversClass]`/`#[CoversMethod]`, `createStub()` used for value doubles, and **100% MSI** — so the design deliberately makes the platform decision injectable instead of calling `PHP_OS_FAMILY`/`stream_isatty()` inside the factory, which would leave a branch unreachable on the Linux runner. |
| **V. Naming & Compatibility** | PASS. The new interface ends in `Interface`; namespace stays under `Webware\Console\`; supported PHP versions unchanged; `@api` surface unchanged (FR-014). |
| **Development Workflow** | PASS. Spec-driven steps land as their own branches; commits use Conventional Commits under the maintainer's identity; the PR is squash-merged per the constitution. |

No violations, so `Complexity Tracking` below records only the one design decision worth justifying.

## Design Decisions

### D1 — Where the seam goes

`ConsoleInterface::run(string $title, object $state, Closure $onKey, Closure $render)` is intrinsically
key-driven and frame-based: its contract is "replay key events, render frames". A prompt-driven
implementation cannot honestly satisfy it, and both `ConsoleInterface` and `App` are `@api`. The seam
therefore sits **above** it, at the level the menu actually needs.

New contract, `Webware\Console\Interaction\InteractionInterface`:

```php
namespace Webware\Console\Interaction;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * The terminal interaction the menu needs.
 *
 * @api
 */
interface InteractionInterface
{
    /**
     * Present the command list and return the chosen command name, or null to quit.
     *
     * @param list<string>                  $names
     * @param callable(string): string      $help  Formats help for a command name.
     */
    public function select(string $title, array $names, callable $help): ?string;

    /**
     * Show a block of text (help, command output, a status line) and wait for the operator.
     */
    public function show(string $title, string $text, ?string $status = null, bool $failed = false): void;

    /**
     * Collect the command's input, or null to go back to the menu.
     *
     * @param array<string, mixed> $presetValues
     */
    public function prompt(SymfonyCommand $command, array $presetValues = []): ?ArrayInput;

    /**
     * Ask a yes/no question, returning $default when the operator submits nothing.
     */
    public function confirm(string $message, bool $default = true): bool;
}
```

Two implementations:

- **`PslInteraction`** — the existing behaviour, moved rather than rewritten. It owns the key handling
  currently inside `MenuCommand::runMenu()` (`up`, `down`, `h`, `enter`, `q`, `ctrl+c`), the frame
  render closures, `Menu` state, and delegation to `MenuRenderer` and `CommandInputPrompter`, all
  through `ConsoleInterface`. On POSIX this must remain observably identical.
- **`SymfonyInteraction`** — `SymfonyStyle`/`OutputInterface` for text and an injected
  `QuestionHelper` for input. No frames, no key events, no raw mode, no helper process.

`MenuCommand` (declared `@internal`) is reduced to the loop: select → prompt → run → show → repeat.

### D2 — How the implementation is chosen

`Webware\Console\Interaction\SupportsRawModeInterface` with `supportsRawMode(): bool`, implemented by
`NativeSupportsRawMode` (checks the OS family and whether stdin is attached to a console) and consumed
by the container factory. Injecting it is what makes the Windows path reachable from Linux CI and keeps
MSI at 100 (FR-004). Default rule: **PSL when `supportsRawMode()` is true, otherwise the Symfony
implementation** — so POSIX consoles keep today's behaviour, and Windows or a piped invocation gets the
new one.

### D3 — Input collection

Field discovery must not be duplicated, or the two implementations will drift. Extract the
argument/option/flag enumeration now performed inside `CommandInputPrompter` into
`Prompt\FieldDiscovery` (PSL-free), returning the existing `PromptField`/`FieldKind` model, and have
both implementations consume it. Mapping from the model to Symfony questions:

| Field | Symfony implementation |
|---|---|
| Required argument | `Question` whose validator rejects an empty string; re-asks (FR-007) |
| Optional argument with default | `Question` with `setDefault()`, empty submission yields the default |
| Option with value | `Question` with `setDefault()` from the option's default |
| Boolean flag | `ConfirmationQuestion` with the option's default |

*The mapping is derived from `PromptField`/`FieldKind` and the prompter's public methods; the exact
per-field semantics (including any array/multiple handling) must be reconciled against
`CommandInputPrompter::onKey()`/`render()` during Phase 3 and recorded in `tasks.md`.*

### D4 — Rendering, quitting and terminal state

`SymfonyInteraction::select()` prints the numbered command list once per iteration and reads a choice
(by number, or by name), with an empty answer or `q` meaning quit — the line-oriented analogue of the
PSL menu's `q`. `Ctrl+C` terminates the process as Symfony Console normally allows; nothing is left in
raw mode on this path because raw mode is never entered, satisfying the "terminal left usable"
acceptance criterion structurally rather than by restoration logic. Long output is written straight
through `OutputInterface` and followed by an explicit wait for input, so the "press enter to return"
line is always the last thing printed. `HelpFormatter` output is already plain text and is shown
verbatim.

### D5 — Wiring

- `ConfigProvider`: register `InteractionInterface` with a new `Container\InteractionFactory`, and
  register `SupportsRawModeInterface` with a factory for `NativeSupportsRawMode`. Keep the existing
  `ConsoleInterface::class => App::class` alias and the `PslTerminalFactoryFactory` invokable
  untouched.
- `Container\MenuCommandFactory`: resolve `InteractionInterface` from the container instead of
  constructing `MenuRenderer`, `CommandInputPrompter` and `ConsoleInterface` inline. `MenuCommand`'s
  constructor then takes the interaction, the loader, the formatter and the runner.
- `Container\PslInteractionFactory` constructs `PslInteraction` from `ConsoleInterface`,
  `MenuRenderer` and `CommandInputPrompter`, preserving today's object graph on POSIX.

### D6 — Compatibility and non-goals

`App`, `ConsoleInterface`, `MenuRenderer` and `CommandInputPrompter` keep their public contracts and
their behaviour; they are simply no longer the only way to drive the menu. `MenuCommand` is
`@internal`, so its constructor may change; its direct constructor calls in tests are updated in the
same change. The PSL dependency stays declared. No attempt is made to fix, patch, fork or work around
the library's Windows raw mode, and none of the Out of Scope items in the spec are addressed here.

## Project Structure

### Documentation (this feature)

```text
specs/002-windows-menu-support/
├── spec.md      # Feature specification (/speckit-specify output)
├── plan.md      # This file (/speckit-plan output)
├── research.md  # Phase 0 output — measurements, source trace, option analysis
└── tasks.md     # Phase 2 output (/speckit-tasks — not created here)
```

### Source Code (repository root)

```text
src/
├── Interaction/                      # NEW namespace
│   ├── InteractionInterface.php      # NEW — the contract (D1)
│   ├── PslInteraction.php            # NEW — existing behaviour, extracted from MenuCommand
│   ├── SymfonyInteraction.php        # NEW — prompt-driven implementation for Windows/no console
│   ├── SupportsRawModeInterface.php  # NEW — capability contract (D2)
│   └── NativeSupportsRawMode.php     # NEW — OS family + stdin-is-a-console check
├── Container/
│   ├── InteractionFactory.php        # NEW — chooses the implementation (D2)
│   ├── PslInteractionFactory.php     # NEW — builds PslInteraction from the PSL collaborators
│   └── MenuCommandFactory.php        # CHANGED — inject InteractionInterface
├── Menu/
│   └── MenuCommand.php               # CHANGED — loop only; PSL imports removed
├── Prompt/
│   └── FieldDiscovery.php            # NEW — shared field enumeration, PSL-free (D3)
├── ConfigProvider.php                # CHANGED — register the two new contract/factory pairs
└── (unchanged) App.php, ConsoleInterface.php, Menu/MenuRenderer.php, Prompt/CommandInputPrompter.php,
    Runner/CommandRunner.php, Help/HelpFormatter.php, Container/PslTerminalFactoryFactory.php

test/
├── unit/
│   ├── Interaction/
│   │   ├── PslInteractionTest.php              # NEW — parity with today's key handling
│   │   ├── SymfonyInteractionTest.php          # NEW — selection, quit, prompt, confirm, show
│   │   ├── NativeSupportsRawModeTest.php       # NEW — both branches via injection
│   │   └── Fixture/
│   │       ├── FakeInteraction.php             # NEW — records calls for MenuCommand tests
│   │       └── FakeSupportsRawMode.php         # NEW — stub returning a fixed capability
│   ├── Container/
│   │   └── InteractionFactoryTest.php          # NEW — capability selects the implementation
│   ├── Prompt/
│   │   └── FieldDiscoveryTest.php              # NEW — argument/option/flag enumeration
│   └── Menu/
│       └── MenuCommandTest.php                 # CHANGED — drives the fake interaction
└── integration/
    └── Menu/MenuIntegrationTest.php            # CHANGED — add a console-less (piped stdin) journey
```

**Structure Decision**: Single project, matching the repository's existing layout — `src/` with
one namespace directory per concern (`Menu`, `Prompt`, `Runner`, `Help`, `Container`) and a parallel
`test/unit` / `test/integration` split. The new `Interaction` namespace follows that pattern rather
than introducing a second structure.

## Implementation Phases

*(Phase 2, the task breakdown, is produced by `/speckit-tasks`; this section is the ordering the plan
commits to.)*

- **Phase 0 — Research** (complete): measurement of the failure, the source trace, the Composer
  interaction, and the option analysis are recorded in [`research.md`](./research.md).
- **Phase 1 — Design** (this document): the contract, the selection rule, the input mapping, the
  wiring and the structure.
- **Phase 3 — Extract, without changing behaviour.** Introduce `InteractionInterface`, move the key
  handling and render closures out of `MenuCommand` into `PslInteraction`, and add the capability
  contract plus factory so the PSL implementation is what is still selected everywhere. The full
  existing suite must pass at 100% MSI at the end of this phase; this is the phase most likely to
  disturb the tests, so it lands before any new behaviour.
- **Phase 4 — Add the Symfony implementation.** `SymfonyInteraction` plus the extracted
  `FieldDiscovery`, with unit tests driving scripted answers through Symfony's own testing facilities.
- **Phase 5 — Flip the selection.** Windows and console-less invocations resolve to
  `SymfonyInteraction`; POSIX consoles stay on `PslInteraction`.
- **Phase 6 — Tests for the Windows path.** Unit coverage of both capability branches, an integration
  journey that supplies piped stdin, and a parity test asserting that the same scripted answers produce
  the same `ArrayInput` from both implementations.
- **Phase 7 — Documentation.** README platform notes, `docs/v1/development.md`, and
  `docs/v1/command-input.md` where the Windows prompting differs.
- **Phase 8 — Verification.** The checklist below, on Windows and POSIX, plus CI on both PHP legs.

## Complexity Tracking

| Decision | Why needed | Simpler alternative rejected because |
|---|---|---|
| Two implementations of one interaction contract | The platform's terminal backend cannot run the raw-mode path at all on Windows, and the Composer invocation cannot supply a console even where it can | A `vendor/` patch is discarded by the next `composer install` and would only fix the console case; forking the dependency trades a local defect for a permanent maintenance burden on a package the fleet does not own |

## Verification Checklist

Windows (manual, WAMP-backed PHP, WSL not involved):

1. `php bin/webware menu` in Windows Terminal → list appears; no `RawMode` or `console_mode.ps1` text
   in the output. *(SC-001)*
2. `composer menu` in the consuming application → same result. *(SC-002)*
3. Select a command, supply input, run it, read the output and status, return to the menu.
4. Request help for a command; confirm the menu returns.
5. Quit with `q` and confirm the terminal is left usable — the next prompt echoes normally.
6. Repeat 1 with stdin piped (`echo "" | php bin/webware menu`) and confirm a clear outcome rather
   than a library exception. *(SC-003)*

POSIX (manual + automated):

7. `bin/webware menu` renders as before: same frames, same keys, same prompts. *(SC-005)*
8. `composer test`, `composer test-integration` and `composer mutation-test` pass on 8.4 and 8.5 with
   MSI 100/100. *(SC-004)*
