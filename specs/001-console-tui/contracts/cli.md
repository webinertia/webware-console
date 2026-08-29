# Contract: Console UX

The console's own interface.

## Launch

- Running the console opens the command menu with all discovered commands.

## Keys

- Up/down: move selection.
- Enter: run the selected command (or open its help).
- Type: filter/search commands.
- Quit key: exit the console.

## Output

- Command output and exit status are shown after invocation, then the menu returns.
- A failing command reports a non-zero status and the failure cause; the console does not crash.

## Exit

- The console itself exits cleanly with status 0 when quit.
