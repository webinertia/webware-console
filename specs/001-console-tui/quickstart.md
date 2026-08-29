# Quickstart: Console TUI

Validation guide — proves the feature end-to-end. Implementation details belong in `tasks.md`.

## Prerequisites

- PHP 8.4.1+ (8.4 or 8.5).
- Composer.
- A terminal.

## Steps

1. Install dependencies: `composer install`.

2. Launch the console via its entry point.

   **Expected**: a menu opens listing the available commands with short descriptions.

3. Move the selection with the up/down keys.

   **Expected**: the highlight moves among the commands.

4. Open help on a command.

   **Expected**: its purpose, every argument, and every option is shown.

5. Select and run a command.

   **Expected**: the command runs, its output and exit status are shown, and the menu returns.

6. Run a command that fails.

   **Expected**: the failure and its cause are shown with a non-zero status, and the menu returns without crashing.

7. Launch the console with no commands registered.

   **Expected**: the console still launches and reports that no commands are available.

## Success

All acceptance scenarios in [spec.md](./spec.md) hold in a terminal with no manual command-list configuration.
