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
