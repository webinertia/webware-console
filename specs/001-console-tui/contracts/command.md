# Contract: Command

What a Webware component or Mezzio provides so the console can surface it.

## Shape

A Symfony Console `Command` with:

- a `name` (e.g. `migrate`, `status`, `rollback`);
- a `description` (short purpose shown in the menu);
- defined `arguments` and `options`, each with a description.

## Semantics

- The command is invocable independently of the console and returns an exit status (`0` success, non-zero failure).
- The console presents the command's name, description, arguments, and options; it does not reimplement command logic.
