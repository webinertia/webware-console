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

## R-011: How a command declares that a value is required

- **Decision**: requiredness is read from the command's own definition, from `InputArgument::REQUIRED` alone. Every required argument is marked with an asterisk in the form; options never are.
- **Rationale**: FR-012. Symfony offers no other way to express it — `InputOption` has no required concept at all (its modes only describe whether the value is accepted), while `InputArgument` carries `REQUIRED = 1`, `OPTIONAL = 2`, `IS_ARRAY = 4`. A value a command must have is therefore declarable only as a required argument, and the console has nothing else to infer from. Marking it is what saves the operator from going back and forth to the command's docs or code, which is the complaint this work started from.
- **Alternatives considered**: inferring requiredness from "an option with no declared default" (rejected — it is wrong: `mezzio:routes:list`'s options declare no useful default yet are genuinely optional); a console-side convention for marking an option required (rejected — a private convention no other tool follows, duplicating what the definition can already express and invisible to anything that reads the definition directly).

## R-012: Where a command's interactive prompting belongs

- **Decision**: prompting for values the operator did not supply belongs in the command itself, in `Command::interact()`. The console does not display a wrapped command's own questions. `user:init-db` declares its four mandatory values as `REQUIRED` arguments and asks for them in `interact()`.
- **Rationale**: `Command::run()` runs `bind()` → `initialize()` → `interact()` → `validate()` → `execute()`, and `interact()`'s docblock states it "is executed before the InputDefinition is validated. This means that this is the only place where the command can interactively ask for values of missing required arguments." So the requirement becomes visible in the definition — which is what the console needs — while direct CLI use keeps prompting exactly as before, and the requirement stays with the component that owns it.
- **Alternatives considered**: handing the command the terminal so its own `QuestionHelper` works unmodified (rejected for this scope — unnecessary once requiredness is declared); substituting the Application's `question` helper so questions render in the TUI (rejected — it only sees commands that call `$this->getHelper('question')`, while `user:init-db` constructs its own, so it could never be sufficient); marking the `ArrayInput` non-interactive so Symfony answers with each question's default (rejected — a user would be created from placeholder values, which defeats the command).
- **Recorded hazard, not fixed here**: a command that consults a value with `??` and asks for it only when absent has that question written into the `BufferedOutput` the runner passes, after which `fgets(STDIN)` blocks with nothing on screen. Declaring the mandatory values as required arguments removes the case that matters.
