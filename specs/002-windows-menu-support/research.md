# Research: Windows Menu Support

**Feature**: `specs/002-windows-menu-support`

**Created**: 2026-10-05

This document records what was **measured**, what is **documented** by an upstream project, and what
is **inferred**. The distinction is load-bearing: the feature's design depends on facts that were
observed directly, and on a small number of assumptions that still need verification.

## 1. Environment used for the measurements

| Item | Value |
|---|---|
| Host | Windows 11, build `26300.9457` (26H2), Professional |
| PHP (CLI and Apache module) | 8.5.11 (ZTS, Visual C++ 2022 x64), WAMP at `C:\wamp64` |
| Web server | Apache 2.4.62.1, PHP as a module (`php8apache2_4.dll`), service `wampapache64` |
| Xdebug | 3.5.3, `xdebug.mode=develop` (not relevant to this defect) |
| `php-standard-library/terminal` | 6.3.0 |
| `symfony/console` | ^7.4 \|\| ^8.0 |
| Composer | 2.9.2, launcher `C:\ProgramData\ComposerSetup\bin\composer.bat` |

## 2. Reproduction (measured)

Two invocations, both failing identically:

```powershell
# a) through Composer, from the application skeleton
composer menu

# b) directly, bypassing Composer and every shim
php vendor/bin/webware menu
```

Both produce:

```text
In RawMode.php line 111:
  PowerShell console_mode.ps1 failed: <vendor>\php-standard-library\terminal\src\Psl\Terminal\
  Internal\console_mode.ps1 : GetConsoleMode failed (error 6)
```

Control measurements taken at the same time:

```text
php vendor/bin/webware --version   →  webware-console 0.1.x      (works)
php vendor/bin/webware list        →  renders the command list    (works; fails later only on MySQL)
Get-ChildItem vendor\bin -Filter webware*  →  webware (3363 b), webware.bat (134 b)
(vendor\bin contains 11 .bat shims in total)
Get-NetTCPConnection -State Listen port 9003  →  nothing          (no debugger involved)
```

So the entry point, the Composer bin proxy, the Windows `.bat` shim, the container boot and the
Symfony Console application are all functional. Only the `menu` command fails, and only where it
enters PSL's raw mode.

## 3. Source trace (measured)

Each step is quoted from the installed version, with its file and line.

### 3.1 The entry into raw mode

`src/Menu/MenuCommand.php` → `ConsoleInterface::run()` → `src/App.php` →
`Psl\Terminal\Application::run()` → raw mode.

`ConsoleInterface` is aliased to `App` in `src/ConfigProvider.php`:

```php
'aliases' => [
    ConsoleInterface::class => App::class,
],
```

`App::run()` builds the PSL application from an injected callable
(`Container\PslTerminalFactoryFactory` returns `Terminal\Application::create(...)`) and calls
`$terminal->run(...)`. That is the only place in the package that reaches PSL's raw mode.

### 3.2 The Windows branch, and the missing stdin

`vendor/php-standard-library/terminal/src/Psl/Terminal/Internal/RawMode.php`:

```php
private static function powershell(array $arguments): string
{
    $script = __DIR__ . '/console_mode.ps1';

    $child = Process\Command::create('powershell')
        ->withArguments(['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $script, ...$arguments])
        ->withStdout(Process\Stdio::piped())
        ->withStderr(Process\Stdio::piped())
        ->spawn();

    $output = $child->waitWithOutput();

    if (!$output->status->isSuccessful()) {
        throw new Exception\RuntimeException('PowerShell console_mode.ps1 failed: ' . Str\trim($output->stderr));
    }

    return $output->stdout;
}
```

There is no `withStdin()` call. Compare the Unix sibling in the same class, which is explicit about
why it sets one:

```php
/**
 * Run stty with /dev/tty as stdin so it can access the real terminal.
 */
private static function stty(array $arguments): string
{
    $child = Process\Command::create('stty')
        ->withArguments($arguments)
        ->withStdin(Process\Stdio::tty())      // <-- deliberate
        ->withStdout(Process\Stdio::piped())
        ->withStderr(Process\Stdio::piped())
        ->spawn();
```

### 3.3 Where the NUL device comes from

`vendor/php-standard-library/process/src/Psl/Process/Command.php` — the factory defaults:

```php
stdin: Stdio::null(),      // line 61
stdout: Stdio::piped(),
stderr: Stdio::piped(),
```

(and again at lines 83–85 in the second factory). So the PowerShell child inherits **NUL** as
stdin because the caller did not override it.

### 3.4 The helper script

`vendor/php-standard-library/terminal/src/Psl/Terminal/Internal/console_mode.ps1` uses the *input*
handle, unconditionally:

```powershell
$handle = [Kernel32]::GetStdHandle([Kernel32]::STD_INPUT_HANDLE)
if ($handle -eq [IntPtr]::Zero -or $handle -eq [IntPtr]::new(-1)) {
    Write-Error "Failed to get console input handle"
    exit 1
}

[uint32]$mode = 0
if (-not [Kernel32]::GetConsoleMode($handle, [ref]$mode)) {
    Write-Error "GetConsoleMode failed (error $([System.Runtime.InteropServices.Marshal]::GetLastWin32Error()))"
    exit 1
}

# ... then ENABLE_VIRTUAL_TERMINAL_INPUT is set, line/echo input are cleared, and the
# original mode is printed to stdout so it can be restored with -Restore <mode>
```

`GetConsoleMode` documents that it fails with `ERROR_INVALID_HANDLE` when the handle is not a
console handle. `ERROR_INVALID_HANDLE` is **6** — the number in the error message, and the
confirmation that the NUL handle is what reached the call. *(Documented by Microsoft; the numeric
mapping is confirmed by the observed error text.)*

### 3.5 Why `Stdio::tty()` is not the answer

`Command.php`, in the same process package:

```php
if ($stdio->isTty()) {
    if (Os\is_windows()) {
        throw new Exception\RuntimeException('TTY is not supported on Windows.');
    }
    return ['file', '/dev/tty', $mode];
}
```

`Stdio::tty()` is Unix-only **by construction**. Any fix that mirrors the `stty` branch with
`Stdio::tty()` will throw on Windows rather than repair anything. `Stdio` does provide the type that
is appropriate:

```php
private const int TYPE_PIPED = 0;
private const int TYPE_INHERIT = 1;
private const int TYPE_NULL = 2;
private const int TYPE_HANDLE = 3;
private const int TYPE_TTY = 4;
```

`Stdio::inherit()` (TYPE_INHERIT) hands the child the parent's own handle, which is a console handle
when the parent has one. It is **necessary but not sufficient** — see 3.6.

### 3.6 Why Composer is a second case, not the same case

`composer menu` runs the script `php vendor/bin/webware menu` as a child process. Composer's
`src/Composer/Util/ProcessExecutor.php` (read from the installed phar):

```php
public function executeTty($command, ?string $cwd = null): int
{
    if (Platform::isTty()) {
        return $this->doExecute($command, $cwd, true);
    }
    return $this->doExecute($command, $cwd, false);
}
```

and inside `runProcess()`:

```php
if (! Platform::isWindows() && $tty) {
    try {
        $process->setTty(true);
    } catch (RuntimeException $e) {
        ...
    }
}
```

The `! Platform::isWindows()` guard means TTY mode is **never** requested on Windows, so the child
gets piped stdio. Under `Stdio::inherit()` that pipe would be inherited, and the helper would fail
exactly as it does now. This is why the fix must not depend on the parent's stdin being a console.
*(Composer's behaviour is measured from its source; the consequence for PSL's helper is inferred
from `inherit` semantics and was not separately executed.)*

### 3.7 What would fix it inside PSL

For the record, since an upstream report is the right home for it (out of scope here):

- **A.** Pass `->withStdin(Process\Stdio::inherit())` in `RawMode::powershell()`. Fixes the console
  case; leaves 3.6 broken.
- **B.** Have `console_mode.ps1` open the console device directly instead of using the inherited
  handle — `CreateFile('CONIN$', GENERIC_READ | GENERIC_WRITE, FILE_SHARE_READ | FILE_SHARE_WRITE,
  null, OPEN_EXISTING, 0, IntPtr.Zero)` — and exit **0** as a no-op when no console is attached.
  This reaches the console even when stdio is redirected, which is the Composer case, and turns a
  hard failure into a graceful degradation elsewhere. *(The `CONIN$` device name is documented by
  Microsoft; the behaviour under redirected stdio was **not** measured here.)*
- A and B together would make `RawMode` correct on Windows.

Neither is available to this package without forking or patching `vendor/`, which is why the design
in `plan.md` does not rely on them.

## 4. Options considered

| Option | Fixes console case | Fixes `composer menu` | Survives `composer install` | In this package's control | Verdict |
|---|---|---|---|---|---|
| **A.** Patch `vendor/php-standard-library/terminal` in place | yes | no | no | no (fork) | Rejected — discarded on install, incomplete |
| **B.** Carry a patched PSL (fork / `composer-patches`) | yes | yes (with 3.7 B) | only while the patch applies | partially | Rejected — a permanent maintenance burden for a dependency defect |
| **C.** Add a Symfony Console driven interaction path, selected at runtime | yes | yes | yes | yes | **Chosen** |
| **D.** Fail with a clear message on Windows | n/a | n/a | yes | yes | Rejected — removes the package's core capability on the platform |

Option C is also the only one that satisfies FR-011 and FR-014 without touching `@api` surface, and
it makes the console-less case (CI, piped runs) a first-class supported path rather than an
accident.

## 5. What the new implementation can rely on

- `symfony/console` is already a runtime requirement (`^7.4 || ^8.0`) and supplies
  `QuestionHelper`, `Question`, `ChoiceQuestion`, `ConfirmationQuestion`, `SymfonyStyle`,
  `StreamInput` and `ArrayInput` — everything the prompt-driven path needs, with no new dependency.
- The command's input shape is already expressed by Symfony: the menu can derive fields from
  `Command::getDefinition()` (`InputArgument`, `InputOption`) rather than maintaining a second
  description of it. The existing `Prompt\FieldKind` (`Argument`, `Option`, `Flag`) and
  `Prompt\PromptField` already model exactly this and can be reused.
- `Runner\CommandRunner` runs a command in-process with a `BufferedOutput` and returns
  `array{status: int, output: string}`. It is PSL-free and needs no change.
- `Help\HelpFormatter::format(SymfonyCommand): string` is PSL-free (it imports only Symfony
  Console) and can be reused verbatim.
- `Container\PslTerminalFactoryFactory` already returns a callable, i.e. the PSL application
  construction is injectable, which gives tests a seam for the PSL side.

## 6. Testability constraints discovered

- **Mutation floors are 100/100** for this repository (`webware-ci.json`: `min_msi: 100`,
  `min_covered_msi: 100`). Any new branch, default value, or conditional that tests do not exercise
  will fail CI, so the new code must be written to be fully covered — including the "not Windows"
  path, which on Linux CI is the path taken.
- **A Windows-only branch cannot be exercised on the Linux CI runner by platform detection alone.**
  The decision therefore has to be injectable (FR-004): the platform/tty predicate must be a
  collaborator, not a direct call to `PHP_OS_FAMILY` or `stream_isatty()` inside the factory.
- **The existing test seam is `ConsoleInterface`** — `test/unit/Console/Fixture/FakeConsole.php`
  implements it and replays scripted `Psl\Terminal\Event\Key` events while capturing frames. The
  PSL implementation of the new contract must keep working with that double, so the double's
  contract must not be widened in a way that breaks it silently.
- `phpunit.xml.dist` runs with `requireCoverageMetadata="true"`, so every new test class needs
  `#[CoversClass]` and `#[CoversMethod]` attributes for the methods it exercises.

## 7. Open items (to resolve before or during implementation)

1. **Confirm the `CONIN$` claim** if the upstream report is pursued (3.7 B). Not needed for this
   feature's design, which avoids PowerShell entirely.
2. **Confirm the interactive style for Windows** — numbered selection on one line versus a
   re-rendered list. The repository's own `docs/v1/command-input.md` should stay truthful about
   which is used, and the choice must be recorded in `plan.md`.
3. **Confirm whether `menu` should refuse to run, or degrade, when stdin is not readable at all**
   (for example a closed stdin). `FR-009` allows a clear message; degraded mode is preferred if it
   is genuinely usable.
