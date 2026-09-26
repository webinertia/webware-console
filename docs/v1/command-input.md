# Command input

Selecting a command from the menu opens a form built from the command's own
definition — one field per argument and per option — so the operator can see what
the command needs without going back and forth to its docs or source.

## Keys

| Key | Action |
|---|---|
| `Tab`, `Down` | next field (wraps) |
| `Up` | previous field (wraps) |
| `Space` | toggle a flag |
| `Left`, `Right`, `Backspace`, printable characters | edit the active value |
| `Enter` | execute the command |
| `Esc` | cancel and return to the menu |

## Required input

A field the command declares as required is marked with an asterisk:

```text
> * configFile: config.php
    class: My\Class
    ignore-unresolved: [ ]
```

`Enter` executes only while every marked field holds a value. Otherwise the command
is not run: the row beneath the fields names the blank required fields, focus moves
to the first of them, and every value already entered is left as it is.

Requiredness is read from the command's own definition, and only an *argument* can
carry it — Symfony's `InputOption` has no required concept, so an option is never
marked or validated. A command that needs a mandatory value must declare it as a
required argument:

```php
$command->addArgument(
    name       : 'handler',
    mode       : InputArgument::REQUIRED,
    description: 'Handler class to create.',
);
```

A requirement expressed only in control flow — an option consulted with `??` and
asked for when absent — cannot be seen by the console, so it can be neither marked
nor validated.

## Defaults and help

A declared default is read from the definition and pre-filled into the field, so it
is submitted explicitly rather than left blank. The active field's description is
shown on the row beneath the fields, which keeps it visible even when the field
already holds a value.

A field that is left blank and is *not* required is omitted from the command's
input, so the command's own default applies.

## Commands that ask their own questions

The console does not display a question a command asks itself. If a command needs to
prompt for a value the operator did not supply, that belongs in the command's own
`interact()` — which Symfony runs before it validates the definition, so it also
works when the command is run directly. Declaring the value as a required argument
means the console handles it instead, and the command never has to ask.
