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

### PromptField (in-memory)

One argument or option of the selected command, rendered as an editable field.

- `name`: string — the argument name, or the option name without its `--` prefix.
- `description`: string — the help text shown on the status row while this field is active.
- `kind`: `Argument` | `Option` | `Flag` — flags are toggled with `Space`; the others are typed.
- `required`: bool — `true` only for arguments declared required; options are never required.
- `isArray`: bool — a comma-separated value becomes a list when the command's input is built.
- `value`: string — seeded from the declared default.
- `flagValue`: bool — seeded from a boolean default.
- `cursor`: int — edit position, clamped to the value's length.

### PromptState (in-memory)

- `fields`: list<PromptField>.
- `activeIndex`: int — wraps modulo the field count.
- `submitted`: bool.
- `cancelled`: bool.
- `missingRequired`: list<string> — the names of the blank required fields from the last refused submission; empty while the form is valid.

### PromptStatusRow (derived, on demand)

The single row between the last field and the footer.

- Shows the refusal message when `missingRequired` is non-empty, otherwise the active field's description.
- Precedence: the refusal message wins.

## Relationships

- The command map maps one `name` to one command class (FR-005); duplicates are surfaced.
- `MenuState` indexes into the list of command names.
- `HelpView` is derived from a single `Command`, resolved on demand.
- `PromptState` holds one `PromptField` per argument and per option of the selected command, in that
  order; the status row reads the active one.

## Discovery

- Components register commands under the config key `ConsoleInterface::class` (`commands` map of name → command class).
- `CommandLoaderFactory` reads `config[ConsoleInterface::class]['commands']` and merges `config['laminas-cli']['commands']` (mezzio-tooling's key), returning a lazy `ContainerCommandLoader`.

## Validation rules

- A command name MUST be unique within the map (FR-006); duplicates throw `DuplicateCommandException`, never hidden.
- An empty command set is valid (FR-007); the console still launches and reports it.
- After a command runs or fails, the menu MUST return to a valid `MenuState` (FR-008).
- Submission is accepted only while every field with `required === true` holds a non-empty value (FR-009/FR-010).
- A refused submission MUST run nothing, name the blank required fields, focus the first of them, and preserve every value already entered.
- Fields that are not required and left blank are omitted from the command's input, so the command's own default applies rather than an empty value being passed.
