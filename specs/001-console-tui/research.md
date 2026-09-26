# Research: Console TUI

Phase 0 output — resolves the technical unknowns from the plan's Technical Context.

## R-001: TUI rendering stack

- **Decision**: php-standard-library/terminal (`Psl\Terminal`, `Psl\Ansi`, `Psl\Type`) for terminal rendering, colors, and widget primitives; Symfony Console `Command` as the command contract components expose.
- **Rationale**: The console PoC already validated this exact stack, and it is the webware-approved terminal library. Using Symfony Console as the command shape gives a uniform, well-documented contract to surface.
- **Alternatives considered**: Hand-rolled ANSI (rejected — reinvents the wheel); a full TUI framework (rejected — heavier than the menu/help scope needs).

## R-002: Command discovery

- **Decision**: Commands are discovered through the `Webware\Console\ConsoleInterface` marker — its `::class` is the config key. Components register commands under that key (a `commands` map of name → command class). `CommandLoaderFactory` reads it, merges `config['laminas-cli']['commands']` (mezzio-tooling's key), and returns Symfony's lazy `ContainerCommandLoader` — commands are resolved from the shared PSR container only when invoked (the same mechanism laminas-cli uses), never instantiated eagerly. Duplicate names throw `DuplicateCommandException`.
- **Rationale**: Satisfies the consumer-agnostic principle and FR-005/FR-006; new components contribute commands without console changes, and mezzio-tooling commands ride along via the merged `laminas-cli` key.
- **Alternatives considered**: A static command list (rejected — contradicts runtime discovery); filesystem scanning for command classes (rejected — registration is explicit and container-friendly); a console-only key without the `laminas-cli` merge (rejected — misses mezzio-tooling commands).

## R-003: Menu interaction model

- **Decision**: Keyboard-driven navigation — up/down to move, enter to select/run, a filter/search input for large lists, and a quit key. State is held in an in-memory menu state, not persisted.
- **Rationale**: Standard TUI patterns; keeps the menu usable as command counts grow (FR-001).
- **Alternatives considered**: Numbered-only selection (rejected — does not scale or filter well); mouse input (rejected — not portable across terminals).

## R-004: Help rendering

- **Decision**: Help is derived from the command's own definition — name, description, arguments, and options — rendered by a `HelpFormatter`; nothing is duplicated by hand.
- **Rationale**: Guarantees the help view matches reality (FR-002, SC-003) and avoids drift.
- **Alternatives considered**: Hand-written help text per command (rejected — drifts from the actual signature).

## R-005: Command invocation

- **Decision**: Run the selected command in-process, collect its output and exit code, display both, then return to the menu. Long-running commands do not block the menu state machine.
- **Rationale**: FR-003/FR-004/FR-008 — output/status must be faithfully reported and the menu must remain usable.
- **Alternatives considered**: Spawning a subprocess per command (rejected — unnecessary for in-process Symfony commands).

## R-006: mezzio-tooling integration

- **Decision**: mezzio-tooling command **discovery** is in scope — `CommandLoaderFactory` merges `config['laminas-cli']['commands']` (mezzio-tooling's key) so mezzio-tooling commands are discovered for free, no adapter needed. Fully **wrapping** mezzio-tooling's TUI (rendering/UX) is deferred to a later iteration.
- **Rationale**: The merge is free (ConfigAggregator already merges the `laminas-cli` key) and covers the Mezzio half of discovery; wrapping the TUI is a larger effort with no current consumer.
- **Alternatives considered**: Building a full mezzio-tooling adapter in v1 (rejected — expands scope); ignoring mezzio-tooling commands entirely (rejected — the laminas-cli merge is zero-cost and keeps Mezzio discovery honest).

## R-007: Symfony Console version alignment

- **Decision**: Declare `symfony/console` as `^7.4 || ^8.0` — the exact range `laminas/laminas-cli` allows. Composer resolves a single shared version; webware-console owns the Symfony runtime and runs laminas-cli commands in-process, so both must agree on the major.
- **Rationale**: `laminas/laminas-cli` requires `symfony/console ^7.4 || ^8.0`; `mezzio/mezzio-tooling` has no runtime `symfony/console` requirement (dev-only pin). Mirroring laminas-cli's range lets any consuming app install both packages with one joint version — no conflict whether it targets Symfony 7 or 8.
- **Alternatives considered**: Single-major pin `^7.4` (works, but drags Symfony 8 apps down to 7.4); unbounded `*` (rejects nothing, loses the version guard).

## R-008: Prompt key contract and submission

- **Decision**: Enter is the only submit key, and it submits from any field — gated on the required fields being filled. Field movement stays on Tab/Down/Up, `Space` toggles a flag, `Esc` cancels. Enter no longer advances the active index.
- **Rationale**: FR-009. Today Enter means "next field" until the operator happens to be standing on the last field, so whether a command can be submitted depends on how many fields it declares — `dev:mode`'s flag in the middle of its list is why the habit broke there and nowhere else. One key, one action; the navigation keys are unaffected, and the active index already wraps, so no field becomes unreachable.
- **Alternatives considered**: Keep Enter as next/confirm and add a dedicated submit key (rejected — two keys for one mental model, and an extra keystroke everywhere); gate Enter on the last field but add a modifier form (rejected — the same overload with more surface).

## R-009: Required-field validation in the submit path

- **Decision**: required fields are validated when Enter is pressed, before `toParameters()` runs. A refusal names the blank required field(s), moves the active index to the first of them, preserves every value already entered, and does not run the command.
- **Rationale**: FR-010. A blank required argument is currently passed as `''`, which Symfony treats as a supplied value, so the failure surfaces deep inside the command rather than at the prompt — `mezzio:handler:create` would try to generate a class named the empty string. Only arguments can be required (`prompt()` hard-codes `required: false` for options), so the check is a single pass over the fields flagged `required`.
- **Alternatives considered**: mark the console's `ArrayInput` non-interactive and let Symfony reject the empty value downstream (rejected — moves the failure further from the operator, and answers any inner question with its default); render an error and re-run the command (rejected — re-running discards the operator's work).

## R-010: Where the active field's description is drawn

- **Decision**: on the status row at `y + count` — the row already free between the last field and the footer — for the active field only, replacing the `TextInput` placeholder. A pending refusal message takes precedence.
- **Rationale**: FR-011. `TextInput::placeholder()` is documented as "text shown when value is empty", so the description vanishes exactly when it is most needed: a declared default pre-fills the field, and `mezzio:routes:list` then shows `format: table` with no hint that `table` is legal or what the alternatives are. Reusing the free row avoids adding widget kinds or growing the form, and giving one row clear precedence avoids a second layout rule.
- **Alternatives considered**: a right-hand description column (rejected — eats the input width on narrow terminals); a description row per field (rejected — the form grows a row per field in a fixed-height frame); keeping the placeholder alongside the row (rejected — duplicated text, and the placeholder is still invisible in the case that matters).

## R-011: Wrapped commands that ask their own questions

- **Decision**: the runner gives the command a live terminal while still capturing its output. `MirroringOutput` writes the already-formatted message through to the terminal (STDOUT) as well as accumulating it, so a question asked via `QuestionHelper` is shown and answerable and the result screen keeps its captured output.
- **Rationale**: FR-012/FR-013. The question is invisible today because it is written into the `BufferedOutput` the runner passes, while `QuestionHelper` blocks reading STDIN — terminal state is already restored by the TUI teardown in `Psl\Terminal\Application::run()`'s `finally`. Handing the terminal over needs no cooperation from the wrapped command, so it covers a command that constructs its own `QuestionHelper` — which ours does (`InitDbCommand.php:143`) — and it inherits Symfony's hidden-input masking, choice defaults and validators instead of reimplementing them.
- **Alternatives considered**: a TUI-native `question` helper substituted into the Application's `HelperSet` (rejected as a sole mechanism — it cannot intercept a locally constructed `QuestionHelper`, so it would fail for `user:init-db` and for any third-party command doing the same; kept as a possible presentation refinement); marking the `ArrayInput` non-interactive so Symfony answers with defaults (rejected — it would make a prompting command useless); giving the command a bare `StreamOutput` and dropping capture (rejected — it would break the `run()` contract, the `Command output` screen and SC-004).
- **Accepted cost**: command output is visible live and again on the result screen. Duplicating it is the price of not being able to know in advance whether a command will prompt.
