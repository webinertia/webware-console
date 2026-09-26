# Contract: Console UX

The console's own interface.

## Launch

- Running the console opens the command menu with all discovered commands.

## Keys

- Up/down: move selection.
- Enter: run the selected command.
- Help key: show the focused command's help.
- Type: filter/search commands.
- Quit key: exit the console.

## Output

- Command output and exit status are shown after invocation, then the menu returns.
- A failing command reports a non-zero status and the failure cause; the console does not crash.- A command's output is also visible while it runs, so a command that stops to ask a question does not appear to hang.

## Prompt keys (supplying a command's arguments and options)

- `Tab` / `Down`: next field (wraps). `Up`: previous field (wraps).
- `Space`: toggle the focused flag.
- `Left` / `Right` / `Backspace` / printable characters: edit the focused value.
- `Enter`: submit — refused while any required field is blank.
- `Esc`: cancel and return to the menu without running the command.

The row between the last field and the key hints shows the active field's description, or the
refusal message when a submission was refused.

## Commands that ask their own questions

- A command may ask questions of its own. Those questions are shown to the operator and answerable from the console.
- A command's questions are never answered with defaults on the operator's behalf.
## Exit

- The console itself exits cleanly with status 0 when quit.
