# Data Model: Console TUI

## Entities

### Command (registered by a component)

A Symfony Console `Command`, registered in the container and mapped by name.

- `name`: string — the config map key (unique identifier).
- `description`: string — short purpose shown in help.
- `arguments`: list — each with name, description, required flag.
- `options`: list — each with name, description, required flag.

### Command map (config)

- `array<string, class-string>` — name => command class, merged from the
  `ConsoleInterface::class` key and the `laminas-cli` key. Turned into a lazy
  `ContainerCommandLoader`; no command instance exists until it is invoked.

### MenuState (in-memory)

- `selection`: index — currently highlighted command name.
- `filter`: string — optional search/filter term.

### HelpView (derived, on demand)

- Rendered purpose, arguments, and options for the focused command. Derived only
  when the operator focuses a command (the command instance is resolved lazily
  at that point).

## Relationships

- The command map maps one `name` to one command class (FR-005); duplicates are surfaced.
- `MenuState` indexes into the list of command names.
- `HelpView` is derived from a single `Command`, resolved on demand.

## Discovery

- Components register commands under the config key `ConsoleInterface::class` (`commands` map of name → command class).
- `CommandLoaderFactory` reads `config[ConsoleInterface::class]['commands']` and merges `config['laminas-cli']['commands']` (mezzio-tooling's key), returning a lazy `ContainerCommandLoader`.

## Validation rules

- A command name MUST be unique within the map (FR-006); duplicates throw `DuplicateCommandException`, never hidden.
- An empty command set is valid (FR-007); the console still launches and reports it.
- After a command runs or fails, the menu MUST return to a valid `MenuState` (FR-008).
