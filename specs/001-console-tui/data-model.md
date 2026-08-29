# Data Model: Console TUI

## Entities

### Command (registered by a component)

- `name`: string — unique identifier.
- `description`: string — short purpose shown in the menu and help.
- `arguments`: list — each with name, description, required flag.
- `options`: list — each with name, description, required flag.

### CommandCatalog (in-memory)

- Ordered collection of discovered `Command` instances.

### MenuState (in-memory)

- `selection`: index — currently highlighted command.
- `filter`: string — optional search/filter term.

### HelpView (derived)

- Rendered purpose, arguments, and options for the focused command.

## Relationships

- `CommandCatalog` aggregates zero or more `Command` instances (FR-005).
- `MenuState` references one entry in the `CommandCatalog` at a time.
- `HelpView` is derived from a single `Command`.

## Validation rules

- `Command.name` MUST be unique within a catalog (FR-006); duplicates are surfaced, not hidden.
- A catalog MAY be empty (FR-007).
- After a command runs or fails, the menu MUST return to a valid `MenuState` (FR-008).
