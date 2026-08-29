# Feature Specification: Console TUI

**Feature Branch**: `001-console-tui`

**Created**: 2026-08-28

**Status**: Draft

**Input**: User description: "Build the Webware console TUI: present a menu of CLI commands from Webware components and Mezzio, show better help for each command, and invoke them."

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

### Key Entities *(include if feature involves data)*

- **Command**: an invocable operation with a name, a short purpose, arguments, and options.
- **Command catalog**: the discovered collection of commands presented in the menu.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An operator can find and run any available command without prior knowledge of its exact name or syntax.
- **SC-002**: 100% of commands provided by installed components appear in the menu with no manual configuration.
- **SC-003**: The help view accurately reflects every argument and option of a command.
- **SC-004**: Running a command from the menu produces the same output and status as invoking it directly.
- **SC-005**: An operator can complete the locate → view-help → run flow for a typical command in under 1 minute.

## Assumptions

- Commands are provided by other components (Webware components and Mezzio); the console presents and invokes them and does not reimplement their logic.
- The console is operated from a terminal with keyboard input as the primary interaction.
- The rendering stack for the text interface is decided during planning, not in this specification.
- In v1 the console focuses on the menu, help, and invocation; wrapping mezzio-tooling is a longer-term direction.
- Discovered commands are component-registered commands, not arbitrary shell commands.
