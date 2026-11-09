# Feature Specification: Windows Menu Support

**Feature Branch**: `spec/002-windows-menu-support`

**Created**: 2026-10-05

**Status**: Draft

**Input**: User description: "we need to fix the menu on Windows — `composer menu` and `php vendor/bin/webware menu` both die before the menu is drawn"

## Problem Statement

The `menu` command cannot run on Windows. It fails before a single frame is drawn, with an
exception raised inside `php-standard-library/terminal`:

```text
In RawMode.php line 111:

  PowerShell console_mode.ps1 failed: ...\vendor\php-standard-library\terminal\src\Psl\
  Terminal\Internal\console_mode.ps1 : GetConsoleMode failed (error 6)
    + CategoryInfo          : NotSpecified: (:) [Write-Error], WriteErrorException
    + FullyQualifiedErrorId : Microsoft.PowerShell.Commands.WriteErrorException,console_mode.ps1
```

Everything else in the package works on Windows: `list`, `--version`, `dev:mode`, `acl:init-db`,
`user:init-db`, `log:init-db` and `acl:seed` all run, because they are ordinary Symfony Console
commands. Only `menu` fails, because it is the only command that enters the PSL terminal's raw-mode
path.

### Measured cause

Each link below was read from the source or reproduced on Windows 11 with PHP 8.5.11 (ZTS, WAMP),
Xdebug 3.5.3, `php-standard-library/terminal` 6.3.0 and Symfony Console 7.4/8.0.

1. `MenuCommand::execute()` calls `ConsoleInterface::run()` (`src/App.php`), which calls
   `Psl\Terminal\Application::run()`. That enters raw mode.
2. `RawMode::enable()` branches to `enableWindows()` on Windows, which calls
   `RawMode::powershell()`.
3. `RawMode::powershell()` spawns the helper **without configuring stdin**:

   ```php
   $child = Process\Command::create('powershell')
       ->withArguments(['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $script, ...$arguments])
       ->withStdout(Process\Stdio::piped())
       ->withStderr(Process\Stdio::piped())
       ->spawn();
   ```

4. PSL's `Command` factory therefore applies its default, `stdin: Stdio::null()` (`Command.php:61`,
   `Command.php:83`). The PowerShell child's stdin is the **NUL device**.
5. The bundled `console_mode.ps1` reads the *input* handle and calls `GetConsoleMode` on it:

   ```powershell
   $handle = [Kernel32]::GetStdHandle([Kernel32]::STD_INPUT_HANDLE)   # -10
   if (-not [Kernel32]::GetConsoleMode($handle, [ref]$mode)) {
       Write-Error "GetConsoleMode failed (error $([System.Runtime.InteropServices.Marshal]::GetLastWin32Error()))"
       exit 1
   }
   ```

   `GetConsoleMode` requires a **console** handle. The NUL device is not one, so it fails with
   `ERROR_INVALID_HANDLE` — error 6.
6. The script exits non-zero, and `RawMode::powershell()` converts that into
   `throw new Exception\RuntimeException('PowerShell console_mode.ps1 failed: ' . ...)` at
   `RawMode.php:111`. The exception leaves `Application::run()` and the command dies.

The Unix branch of the same class does not have this problem because it passes the terminal
explicitly — `->withStdin(Process\Stdio::tty())`, commented *"Run stty with /dev/tty as stdin so it
can access the real terminal"*. The Windows branch is missing the equivalent.

### Why the obvious one-line fix is not available

`Stdio::tty()` cannot be used on Windows. It is implemented per platform and throws:

```php
// vendor/php-standard-library/terminal/... Psl\Process\Command
if ($stdio->isTty()) {
    if (Os\is_windows()) {
        throw new Exception\RuntimeException('TTY is not supported on Windows.');
    }
    return ['file', '/dev/tty', $mode];
}
```

`Stdio::inherit()` **does** exist and is the correct type for the console case, but it is
insufficient on its own, because the failing invocation is not always attached to a console:

- Started as `php vendor/bin/webware menu` from an interactive PowerShell/Windows Terminal window,
  the PHP process holds a real console, so `inherit()` would let `GetConsoleMode` succeed.
- Started as `composer menu`, the PHP process is a child of Composer. Composer's
  `ProcessExecutor::executeTty()` calls `doExecute($command, $cwd, false)` when
  `Platform::isWindows()`, and `runProcess()` only calls `setTty(true)` under
  `if (! Platform::isWindows() && $tty)`. Composer therefore **pipes** the child's stdio on Windows,
  `inherit()` propagates that pipe, and the helper fails exactly as it does today.

### Ruled out by measurement

- **Missing Windows shim.** `vendor/bin/webware` (3363 bytes) and `vendor/bin/webware.bat`
  (134 bytes) both exist, and eleven `.bat` shims are generated alongside them. `php
  vendor/bin/webware --version` runs and prints `webware-console 0.1.x`. A missing shim would fail
  in the shell with "not recognized as an internal or external command", before PHP runs; this
  failure is a PHP exception thrown from inside a library, after the command has started.
- **A broken PHP or Xdebug install.** The non-menu commands run through the same entry point,
  container and binary.
- **Configuration.** No environment variable or php.ini setting is involved; the failure is
  deterministic in the library's Windows code path.

## Clarifications

### Session 2026-10-05

- **Q**: Is the failure specific to being invoked through Composer? **A**: No — measured with and
  without Composer. Both produce the identical `RawMode.php:111` exception.
- **Q**: Can a Windows-side `.bat` or `.ps1` shim fix it? **A**: No. Shims are present and the
  entry point works; the failure is inside a third-party library's own helper process.
- **Q**: Should the package patch `vendor/`? **A**: No. Vendor edits vanish on the next
  `composer install` and would not help anyone consuming the package.
- **Q**: Is replacing the TUI stack acceptable? **A**: Only where the current one cannot run. Unix
  behaviour must not change, since it is shipped, documented and covered by tests.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Run the menu on Windows from a console (Priority: P1)

A developer on Windows opens Windows Terminal or PowerShell, runs
`php vendor/bin/webware menu`, and gets the command menu: the command list, arrow-key or
number-based selection, `h` for help, `enter` to activate, and `q`/`Ctrl+C` to exit.

**Why this priority**: This is the single capability that does not exist on Windows today. Without
it the package's core purpose — presenting and invoking commands — is unavailable on the platform
the maintainer now develops on, which is the reason the development environment moved.

**Independent Test**: On a Windows host with no vendor patch, run
`php vendor/bin/webware menu` in a real console, select a command, run it, and return. Delivers a
usable menu; needs nothing else in this spec.

**Acceptance Scenarios**:

1. **Given** a Windows console and the package installed, **When** the developer runs
   `php vendor/bin/webware menu`, **Then** the command list is displayed with no exception and the
   prompt waits for input.
2. **Given** the menu is displayed, **When** the developer selects a command and presses enter,
   **Then** the command's input is requested, the command runs, its output and exit status are
   shown, and the menu is shown again.
3. **Given** the menu is displayed, **When** the developer presses `q` or `Ctrl+C`, **Then** the
   command returns exit code 0 and the terminal is left usable (no raw mode left enabled).
4. **Given** the menu is displayed, **When** the developer asks for help on the selected command,
   **Then** the formatted help text is shown and the menu returns.

---

### User Story 2 - Run the menu on Windows through Composer (Priority: P1)

The developer runs `composer menu` (a script in the consuming application) on Windows and reaches
the same menu, even though Composer gives the child process piped stdio.

**Why this priority**: `composer menu` is how the application skeleton documents the entry point,
so it is the path a developer tries first. A fix that only works when the process owns a console
would leave the documented invocation broken.

**Independent Test**: Run `composer menu` in the consuming application on Windows and reach a
working menu. Independently verifiable, and it is the case the "console-less" branch of the design
exists for.

**Acceptance Scenarios**:

1. **Given** a Windows host and the application skeleton installed, **When** `composer menu` is run,
   **Then** the menu is displayed and is fully operable.
2. **Given** stdin is not a console (piped, or a runner that captures output), **When** the menu
   command starts, **Then** it still presents a usable menu rather than raising.

---

### User Story 3 - Unix behaviour is unchanged (Priority: P1)

A developer on Linux or macOS sees exactly the previous experience: the same full-screen,
key-driven menu, the same rendering, the same keys, the same prompts.

**Why this priority**: The package is published and its Unix path works. Regressing it to fix
Windows is not an acceptable trade, and the constitution's quality gates apply to the whole suite.

**Independent Test**: The existing unit and integration suites pass unchanged, and a manual run on
Linux/macOS is indistinguishable from the current release.

**Acceptance Scenarios**:

1. **Given** a POSIX host with a terminal, **When** `bin/webware menu` runs, **Then** the PSL
   terminal path is used and the rendering is identical to the current release.
2. **Given** the change is applied, **When** the full suite runs, **Then** every previously
   passing test still passes and mutation coverage stays at 100%.

---

### User Story 4 - Command input prompting works on Windows (Priority: P2)

Having chosen a command, the developer is prompted for that command's arguments and options on
Windows, with the same semantics as the PSL prompt: required fields, defaults, flags, and a
confirmation for destructive prompts.

**Why this priority**: Selecting a command is useless if its input cannot be collected. This is
lower than P1 only because the menu itself must exist first; it is not optional for the feature to
be complete.

**Independent Test**: On Windows, select a command that takes an argument and an option, supply
them, and observe the command receive the correct `ArrayInput`.

**Acceptance Scenarios**:

1. **Given** a command with a required argument, **When** the developer submits nothing, **Then**
   the prompt re-asks and no input is passed to the command.
2. **Given** a command with an option that has a default, **When** the developer submits nothing,
   **Then** the default is applied to the generated `ArrayInput`.
3. **Given** a command with a flag, **When** the developer confirms it, **Then** the flag is set in
   the generated `ArrayInput`.
4. **Given** a confirmation prompt, **When** the developer answers with the default, **Then** the
   default outcome is returned.

---

### Edge Cases

- **No console attached at all** (fully detached process, service context, or a CI runner): the
  menu must degrade to a line-oriented, prompt-driven flow rather than raising, or exit with a clear
  message if even that is impossible. It MUST NOT raise a third-party exception.
- **Zero commands discovered**: the menu states that no commands are available and does not spin.
- **Very long command list**: the list must remain usable — either scrollable or numbered — with no
  truncation of the selected item.
- **Command output longer than the terminal**: output must be shown without corrupting the
  terminal, and the prompt to return must still be reachable.
- **A command that fails**: its non-zero status and output are shown, and the menu returns.
- **Interrupt during a prompt**: `Ctrl+C` during input collection exits cleanly, leaving the
  terminal usable.
- **Non-Windows host**: the PSL path is selected; nothing about the Windows path executes.
- **Windows host without PowerShell on `PATH`**: irrelevant to the new path — it must not spawn
  PowerShell at all.
- **Broken pipe on the output stream** (e.g. output piped to a closed reader): exit non-zero with a
  message rather than an uncaught library exception.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: `menu` MUST run to completion on Windows, displaying the discovered command list,
  without raising and without any patch applied to `vendor/`.
- **FR-002**: `menu` MUST be operable when it is invoked as `composer menu`, i.e. when its stdin is
  a pipe rather than a console.
- **FR-003**: `menu` MUST NOT spawn PowerShell, or any helper process, in order to configure the
  terminal on Windows.
- **FR-004**: The package MUST select its terminal interaction implementation at runtime from an
  injectable capability decision, so the Windows path is exercisable by tests on Linux CI.
- **FR-005**: Selection MUST leave non-Windows behaviour identical to the current release: the PSL
  terminal path remains the implementation used on POSIX hosts.
- **FR-006**: The interactive behaviour of the menu MUST be preserved on each platform: list the
  discovered commands; select one; show formatted help for it; collect its input; run it in-process;
  show its output and status; return to the menu; exit on the quit key or interrupt.
- **FR-007**: Input collection MUST cover the input definition Symfony exposes for a command —
  arguments (required and optional, with defaults) and options (value and boolean flag) — and MUST
  re-prompt rather than passing an invalid or missing required value through.
- **FR-008**: The menu MUST work when no console is attached, degrading to a line-oriented flow
  rather than raising.
- **FR-009**: Any failure that prevents interactive operation MUST produce a clear message and a
  non-zero exit code; a third-party library exception MUST NOT escape to the user.
- **FR-010**: Commands MUST continue to be run in-process, capturing output and status, with the
  existing runner semantics preserved.
- **FR-011**: No new runtime dependency may be introduced that does not already run on the supported
  PHP versions on Windows; where a new abstraction is added it MUST be satisfied by libraries the
  package already requires.
- **FR-012**: Every new class and method MUST be covered by tests meeting the repository gates:
  PHPUnit 13 strict mode with coverage metadata (`#[CoversClass]`, `#[CoversMethod]`), the
  mock/stub split, and `min_msi`/`min_covered_msi` of **100** as declared in `webware-ci.json`.
- **FR-013**: Documentation MUST state the platform behaviour: the README and the
  `docs/v1/command-input.md` / `docs/v1/development.md` notes must describe how the menu behaves on
  Windows and on a console-less host.
- **FR-014**: The change MUST NOT alter the public constructor contract of `ConsoleInterface` or
  `App`, which are marked `@api`; new seams are additive.

### Key Entities

- **Terminal interaction contract**: the platform-neutral seam the menu depends on. Represents the
  operations the menu needs from a terminal: select a command from a list, show text (help, output,
  status), collect input for a command, and ask for confirmation. Satisfied by two implementations.
- **PSL terminal interaction**: the existing implementation. Wraps `ConsoleInterface`/`App`, the
  PSL `Menu` widget renderer and the PSL-based input prompter. Preserves current behaviour.
- **Prompt-driven terminal interaction**: the new Windows/console-less implementation. Driven by
  Symfony Console questions and output rather than key events and frames.
- **Capability decision**: the injectable value that decides which implementation is used — platform
  family and whether stdin is attached to a console.
- **Menu state**: the existing selection model (names, selection index, activated name, help text).
  Unchanged by this feature.
- **Prompt field**: the existing description of one command input (argument, option or flag) with
  its requiredness and default. Reused by the new implementation rather than duplicated.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On Windows, `php vendor/bin/webware menu` reaches a rendered command list and can run
  at least one command end to end, with zero occurrences of the string `RawMode` or `console_mode.ps1`
  in any terminal output or log.
- **SC-002**: On Windows, `composer menu` reaches the same working menu with the same result as
  SC-001, i.e. the pipe case is not a second defect.
- **SC-003**: The command list is presented and a command can be selected and run on a host with no
  console attached (piped stdin), verified in CI.
- **SC-004**: The full test suite passes on PHP 8.4 and 8.5 with `min_msi` and `min_covered_msi` both
  at 100, i.e. no coverage or mutation regression.
- **SC-005**: A reviewer on Linux or macOS observes no change in menu rendering or key handling
  compared with the current release.

## Assumptions

- The maintainer's Windows development environment remains WAMP-backed PHP (no container), where the
  console application is run from Windows Terminal or PowerShell, and `composer` is the Windows
  launcher (`composer.bat`).
- Symfony Console is already a runtime dependency of the package and provides the question and
  output facilities needed by the new implementation.
- `php-standard-library/terminal` remains a runtime dependency, and its Unix path stays supported;
  this feature does not remove the dependency.
- The PSL defect described in **Problem Statement** may be fixed upstream. If it is, the Windows
  path selected here must still be correct and must not depend on that fix; the capability decision
  can be revisited separately.
- The `menu` command's interactive behaviour is the contract under test; the exact visual style of
  the Windows path (for example numbered list versus arrow keys) is a design decision recorded in
  `plan.md`, not a requirement here.

## Out of Scope

- Fixing or forking `php-standard-library/terminal`. Filing an upstream report is a follow-up, not
  part of this feature.
- The unrelated hardcoded `version: '0.1.x'` in `src/Container/SymfonyFactory.php`, which reports a
  stale version in `--version` and the `list` banner.
- The `-S localhost:0` / `serverReadyAction` mismatch in the application skeleton's
  `.vscode/launch.json`.
- Any change to command discovery, the help formatter, the development-mode command, or the command
  loader.
- A graphical or web-based equivalent of the menu.
