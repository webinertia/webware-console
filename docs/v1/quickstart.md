# Quickstart

## Launch

1. Install dependencies:

   ```bash
   composer install
   ```

2. Open the menu:

   ```bash
   composer console-menu    # or: composer menu
   ```

   A menu opens listing the discovered commands with their descriptions.

3. Alternatively, launch the raw Application:

   ```bash
   php bin/console
   ```

   or the menu directly:

   ```bash
   php bin/console menu
   ```

## Menu keys

| Key          | Action                          |
| ------------ | ------------------------------- |
| `↑` / `↓`    | Move the selection              |
| `h`          | Show help for the focused command |
| `Enter`      | Run the selected command        |
| `q` / `Ctrl+C` | Quit the console              |

While help is shown, any key dismisses it.

## Prompt keys

When running a command, the prompt collects its inputs:

| Key           | Action                           |
| ------------- | -------------------------------- |
| `Tab` / `↓`   | Next field                       |
| `↑`           | Previous field                   |
| `Space`       | Toggle a checkbox (flag)         |
| `Enter`       | Confirm / advance                |
| `Esc`         | Cancel and return to the menu    |
| `←` / `→`     | Move the cursor within a value   |
| `Backspace`   | Delete before the cursor         |

## Behavior

- A successful command shows its output and a success status, then returns to
  the menu.
- A failing command shows its output and a non-zero status; the console returns
  to the menu without crashing.
- With no commands registered, the console still launches and reports that no
  commands are available.
