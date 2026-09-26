# Feature Specification: Console TUI

**Feature Branch**: `001-console-tui`

**Created**: 2026-08-28

**Status**: Draft

**Input**: User description: "Build the Webware console TUI: present a menu of CLI commands from Webware components and Mezzio, show better help for each command, and invoke them."

## Clarifications

### Session 2026-09-25

- Q: Should Enter submit the form from any field, moving field navigation entirely to Tab/Up/Down? → A: Enter submits from any field, but submission is refused while any required field is blank; Tab, Up and Down move between fields and remain the only way to navigate.
- Q: When the form is submitted with a required field left blank, what should happen? → A: Refuse to run, name the blank required field(s), and keep the operator in the form with every value already entered left intact. Passing an empty value through so the command fails somewhere inside is not acceptable.
- Q: Where should the active field's description be shown so it survives a field that already has a value? → A: On the spare row directly below the field list, showing the active field's description.
- Q: How should the operator be able to tell which fields a command requires, before submitting? → A: Every field the command declares as required is marked with an asterisk in the form, so the requirements are visible while it is being filled in — the operator should not have to consult the command's code or docs.
- Q: How should a value that a command genuinely requires, but that is declared as an option, be handled? → A: Symfony cannot express a required option — requiredness exists only on arguments. A command needing a mandatory value MUST declare it as a required argument. `user:init-db`'s four mandatory values are to be declared that way by its own component, with its prompting moved into `interact()`, which runs before the definition is validated and so keeps prompting on direct CLI use.
- Q: Should the console surface a wrapped command's own interactive questions? → A: No — out of scope. The console wraps commands whose input the command declares. A command that asks its own questions is left unchanged and is not this work's concern.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Browse commands via a menu (Priority: P1)

An operator launches the console and sees a navigable list of the available commands, without needing to know the exact names or syntax in advance.

**Why this priority**: Discoverability is the console's core value — the menu is the entry point every other capability (help, running commands) hangs off.

**Independent Test**: Can be fully tested by launching the console against a known set of available commands and confirming the menu lists them all and is navigable by keyboard.

**Acceptance Scenarios**:

1. **Given** several commands are available, **When** the console launches, **Then** the menu displays all of them and the operator can move the selection among them.
2. **Given** the operator does not know command names, **When** the console launches, **Then** command names and a short purpose are visible without any prior documentation.

---

### User Story 2 - View help for a command (Priority: P2)

When the operator focuses or selects a command, the console shows its purpose, arguments, and options clearly, so the operator can understand what it does and what it needs before running it.

**Why this priority**: Better help is a stated goal of the console and is the difference between a raw command list and a usable interface; it is independently valuable and low-risk.

**Independent Test**: Can be fully tested by opening help on a command with known arguments and options and confirming each is shown with its description.

**Acceptance Scenarios**:

1. **Given** a command with arguments and options, **When** the operator views its help, **Then** the purpose, every argument, and every option is displayed with a description.
2. **Given** a command with no arguments or options, **When** the operator views its help, **Then** the help still renders sensibly and states there are none.

---

### User Story 3 - Run a command (Priority: P2)

The operator selects a command, supplies the required inputs, runs it, and sees its output and success or failure status, then returns to the menu.

**Why this priority**: Surfacing commands is useful only if they can actually be run; this closes the loop from discovery to execution.

**Independent Test**: Can be fully tested by selecting a command, entering its inputs, and confirming it runs and reports the same outcome as invoking it directly.

**Acceptance Scenarios**:

1. **Given** a selected command that succeeds, **When** it runs, **Then** its output is shown with a success status and the console returns to the menu.
2. **Given** a selected command that fails, **When** it runs, **Then** the failure and its cause are shown and the console returns to the menu without crashing.
3. **Given** a command with several arguments and options, **When** the operator fills them in and submits from whichever field they are standing on, **Then** the command runs without the operator first having to move to the last field.
4. **Given** a command with a required field, **When** the operator submits while that field is blank, **Then** the command does not run, the blank field is named, and the operator's other entries are still there to correct.
5. **Given** a field pre-filled from a declared default, **When** the operator looks at the form, **Then** that field's description is visible even though the field is not blank.
6. **Given** a command with a required argument, **When** the operator looks at its form before submitting, **Then** that field is marked with an asterisk.

---

### User Story 4 - Discover commands from installed components (Priority: P3)

The menu automatically reflects the commands provided by installed Webware components and Mezzio, so commands from newly installed components appear without any manual reconfiguration.

**Why this priority**: Automatic discovery keeps the console honest over time as the component ecosystem grows, and removes the maintenance burden of a hardcoded command list.

**Independent Test**: Can be fully tested by changing the set of installed components and confirming the menu reflects the added or removed commands on the next launch.

**Acceptance Scenarios**:

1. **Given** a new component providing commands is installed, **When** the console launches, **Then** the new commands appear in the menu with no manual step.
2. **Given** a component is removed, **When** the console launches, **Then** its commands no longer appear.

---

### Edge Cases

- Two components provide commands with the same name — the console MUST disambiguate or clearly surface the conflict rather than hiding one.
- No commands are available — the console MUST still launch and report an empty state gracefully.
- A command has a very large number of arguments or options — help MUST remain readable (e.g. grouped or scrollable).
- A command takes a long time to run — the console MUST remain responsive and show that it is still working.
- An operator aborts input for a command midway — the console MUST return to the menu without a partial or broken state.
- A required field is left blank at submission — the console MUST refuse to run, name the field, and keep the operator in the form with their other entries intact, rather than passing an empty value to the command.
- A field's value is seeded from a declared default — that field's description MUST stay visible, since a pre-filled value is exactly where the operator most needs to know what is allowed.
- A command needs a value but declares it as an option — the console cannot see that requirement, because requiredness can only be declared on an argument; such a command MUST declare the value as a required argument.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST present a navigable menu of the available commands.
- **FR-002**: System MUST display a command's purpose, arguments, and options as help.
- **FR-003**: System MUST let an operator select and run a command with its inputs.
- **FR-004**: System MUST display a command's output and success or failure status.
- **FR-005**: System MUST discover commands from installed components at runtime with no manual configuration.
- **FR-006**: System MUST handle duplicate command names without hiding either command or misrunning them.
- **FR-007**: System MUST remain operable when no commands are available.
- **FR-008**: System MUST return to the menu after a command completes or fails.
- **FR-009**: System MUST accept submission of a command's input form from any field, without the operator first having to reach a particular field.
- **FR-010**: System MUST refuse to run a command while any of its required fields is blank, MUST identify each blank required field by name, and MUST keep the operator in the form with all previously entered values preserved.
- **FR-011**: System MUST keep the active field's description visible whenever that field holds a value, including a value seeded from a declared default.
- **FR-012**: System MUST mark each field the command declares as required, so the operator can see what is mandatory before submitting.

### Key Entities *(include if feature involves data)*

- **Command**: an invocable operation with a name, a short purpose, arguments, and options.
- **Command map**: the discovered name => command-class registrations presented in the menu (loaded lazily via a Symfony command loader).
- **Wrapped command shape**: the arguments and options a command declares, which is what the console's input form is built from. The shapes already reachable from the menu range from a single field (`servicemanager:generate-factory-for-class`, one required argument; `acl:init-db`, one option) up to seven (`mezzio:handler:create`: one required argument, two flags, and up to four template options that appear only when a template renderer is registered), and include a command whose middle field is a flag rather than a value (`dev:mode`: `enable`, `disable`, `status`). Requiredness is declared on arguments only — Symfony has no required option — so a command that must have a value has to declare it as a required argument for the console to be able to see it.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An operator can find and run any available command without prior knowledge of its exact name or syntax.
- **SC-002**: 100% of commands provided by installed components appear in the menu with no manual configuration.
- **SC-003**: The help view accurately reflects every argument and option of a command.
- **SC-004**: Running a command from the menu produces the same output and status as invoking it directly.
- **SC-005**: An operator can complete the locate → view-help → run flow for a typical command in under 1 minute.
- **SC-006**: An operator can complete any command reachable from the menu using only Tab/Up/Down to move between fields, Space to toggle a flag, and Enter to submit — no command can only be completed by first reaching a particular field.
- **SC-007**: Every field a command declares as required is visibly marked before submission, so an operator can see what is mandatory without consulting the command's code or documentation.

## Assumptions

- Commands are provided by other components (Webware components and Mezzio); the console presents and invokes them and does not reimplement their logic.
- webware-console is a generic CLI host with zero migration knowledge; webware-migration depends on it (one-way: migration → console), and components register commands through their `ConfigProvider`.
- The console is operated from a terminal with keyboard input as the primary interaction.
- The rendering stack for the text interface is decided during planning, not in this specification.
- In v1 the console focuses on the menu, help, and invocation. mezzio-tooling commands are discovered via the merged `laminas-cli` config key; fully wrapping mezzio-tooling's TUI remains a longer-term direction.
- Discovered commands are component-registered commands, not arbitrary shell commands.
- The submission, required-field validation and active-field help behaviours belong to the shared input machinery, so they change for every command driven through the menu — not only the command that first exposed the defects.
- Required input is declared in the command's own definition. The console takes requiredness from there and infers it from nothing else, so a command that needs a mandatory value must declare it as a required argument.
- The console does not display a command's own interactive questions; a command whose input is not declared for the console is out of scope for this work.
