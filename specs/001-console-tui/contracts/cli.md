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

## Required input

- A field the command declares as required is marked with an asterisk in the form.
- Requiredness is taken only from the command's own definition. A value declared as an option is never treated as required, because Symfony cannot declare an option required.
## Exit

- The console itself exits cleanly with status 0 when quit.
