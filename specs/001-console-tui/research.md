# Research: Console TUI

Phase 0 output — resolves the technical unknowns from the plan's Technical Context.

## R-001: TUI rendering stack

- **Decision**: php-standard-library/terminal (`Psl\Terminal`, `Psl\Ansi`, `Psl\Type`) for terminal rendering, colors, and widget primitives; Symfony Console `Command` as the command contract components expose.
- **Rationale**: The console PoC already validated this exact stack, and it is the webware-approved terminal library. Using Symfony Console as the command shape gives a uniform, well-documented contract to surface.
- **Alternatives considered**: Hand-rolled ANSI (rejected — reinvents the wheel); a full TUI framework (rejected — heavier than the menu/help scope needs).

## R-002: Command discovery

- **Decision**: A `CommandCatalog` is populated at runtime from commands registered by components (through their config providers), never from a hardcoded list. Duplicate names are surfaced (disambiguated or flagged) rather than silently dropped.
- **Rationale**: Satisfies the consumer-agnostic principle and FR-005/FR-006; new components contribute commands without console changes.
- **Alternatives considered**: A static command list (rejected — contradicts runtime discovery); filesystem scanning for command classes (rejected — registration is explicit and container-friendly).

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

- **Decision**: Deferred to a later iteration. v1 surfaces Symfony-style commands registered by components; long-term the console wraps mezzio-tooling commands in addition.
- **Rationale**: The constitution names mezzio-tooling as a long-term direction, not a v1 requirement; keeping v1 to the Symfony command contract keeps scope bounded.
- **Alternatives considered**: Building the mezzio-tooling adapter in v1 (rejected — expands scope without a current consumer).
