# `dev:mode` — parity with `laminas/laminas-development-mode`

Replacement for `laminas/laminas-development-mode` (referred to below as *the reference*,
`3.16.x`). Developers coming from Mezzio expect the same observable result, so this document
is the contract: every behaviour of the reference is inventoried here and carries a
disposition. **A row without a disposition means the work is not finished.**

Sources read in full to build this table: `src/Command.php`, `src/Help.php`,
`src/Status.php`, `src/Enable.php`, `src/Disable.php`, `src/AutoComposer.php`,
`src/ConfigDiscoveryTrait.php`, `bin/laminas-development-mode`,
`development.config.php.dist`, `development.local.php.dist`.

## The files involved

| Path | Role |
|---|---|
| `config/development.config.php.dist` | Required. Aggregated last; turns `debug` on and config caching off. |
| `config/development.config.php` | The active file. **Created by `enable`, removed by `disable`.** |
| `config/autoload/development.local.php.dist` | Optional. Application-supplied dev overrides. |
| `config/autoload/development.local.php` | Created from the `.dist` by `enable` when the `.dist` exists; removed by `disable`. |
| the aggregated config cache | Removed by **both** `enable` and `disable` whenever they change state. |

## Parity matrix

| # | Reference behaviour | Disposition |
|---|---|---|
| 1 | `Enable` creates `config/autoload/development.local.php` from its `.dist` **when the `.dist` exists** | replicate |
| 2 | `Disable` removes `config/autoload/development.local.php` **when present** | replicate |
| 3 | Enable links rather than copies on `Linux`/`Unix`/`Darwin`: `symlink(basename($source), $destination)` — `copy()` only on other OSes | replicate |
| 4 | Fifth subcommand `auto-composer`: reads `COMPOSER_DEV_MODE`; absent/`''` → no-op exit 0; `'0'` → disable; `'1'` → enable; anything else → message, exit 1 | replicate |
| 5 | No arguments → stderr `No arguments provided.` + help, **exit 1**. Unrecognized argument → stderr `Unrecognized argument.` + help, **exit 1** | **presentation — ours.** No action prints the usage and exits 0, so an empty submit from the menu is not reported as a failure. Unrecognized options are already rejected by Symfony with a non-zero exit. |
| 6 | Cache discovery falls back to MVC `module_listener_options.cache_dir` + `module-config-cache[.<config_cache_key>].php` after `config_cache_path` | **out of scope** — MVC has no equivalent here; a Mezzio application declares `config_cache_path` in `config/config.php` and the console reads it from the aggregated container config |
| 7 | The already-in-state branches return **before** removing the cache, so an already-enabled or already-disabled application keeps it — even though `Help` states the opposite: "both when disabling and enabling development mode, the script will remove the file cache/module-config-cache.php" | **the documented contract is what is replicated, not the code path.** The console removes the cache on every `enable`/`disable`. That is what the reference's own help text promises and what developers expect; the reference's early return contradicts its documentation. |
| 8 | Errors are written to **STDERR**; help on request goes to stdout | **presentation — ours.** Diagnostics go through the command's own output. |
| 9 | The help text documents the whole file contract (required `.dist`, optional `.local.dist`, cache removal) | replicate |
| 10 | The package ships `development.config.php.dist` **and** `development.local.php.dist` for consumers to copy into place | replicate |
| 11 | Messages: `You are now in development mode.` / `Development mode is now disabled.` / `Already in development mode!` / `Development mode was already disabled.` / `Development mode is ENABLED` / `Development mode is DISABLED` | **DIVERGENCE — wording only.** The console keeps its own voice; the information conveyed is identical. |
| 12 | `Status` reads exactly one thing: `file_exists('config/development.config.php')` — never the cache, never `.local` | replicate. The console additionally reports a cache removal **only when a file was actually removed**, so the output describes what happened rather than what was attempted. |

## Where this lives

| Class | Responsibility |
|---|---|
| `Webware\Console\DevelopmentMode` | The filesystem policy — the four paths, link-versus-copy, and the aggregated config cache. No output coupling, so each row above is testable on its own. |
| `Webware\Console\DevelopmentModeCommand` | The CLI surface — flags, wording, exit codes, `--auto-composer`. |

## Row 7 in detail

The reference leaves the cache alone when the requested state is already in force, but its own
`Help` text says the cache is removed "both when disabling and enabling development mode". Those
cannot both be right. The console follows the documentation, because that is the behaviour a
migrating developer has been told to expect — and because `ConfigAggregator` returns an existing
cache without consulting a single provider, which makes a surviving cache authoritative and lets a
stale state outlive the toggle meant to end it.

## Integration points outside the command

Parity in a Mezzio application is not only the command. The reference is normally reached
through Composer scripts, which the console cannot supply from inside this package:

The reference is normally reached through Composer scripts, and a migrating application also needs
`config/autoload/development.local.php.dist` present if it wants rows 1 and 2 to do anything.

**That script set is deliberately not replicated.** The only Composer script a console application
needs is the one that starts the menu:

```json
"scripts": {
    "menu": "webware menu"
}
```

For the record, so that its absence reads as a decision rather than an oversight, this is what the
reference wires and we do not:

```json
"post-install-cmd": ["@development-enable"],
"post-update-cmd": ["@development-enable"],
"development-disable": "laminas-development-mode disable",
"development-enable": "laminas-development-mode enable",
"development-status": "laminas-development-mode status"
```

Development mode is therefore always an explicit action: chosen from the menu, or run as
`dev:mode` on the CLI. `--auto-composer` (row 4) stays available for parity, but nothing in the
stack wires it up.

## Invocation

The console keeps its flag interface (`dev:mode --enable|--disable|--status`) rather than the
reference's positional subcommands, because the interface is documented, tested, exposed in
the menu, and consumed by the prompt machinery. `--auto-composer` is added as a flag for row 4.
