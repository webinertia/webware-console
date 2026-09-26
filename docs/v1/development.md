# Development

## Test suites

```bash
composer test               # unit tests
composer test-integration   # integration tests
composer test-all           # unit + integration
composer test-coverage      # full suite with Clover coverage (clover.xml)
composer mutation-test      # Infection mutation testing
```

## Quality gates

The package passes the shared Webware gates:

```bash
mago format     # formatting
mago lint       # style / correctness
mago analyze    # static analysis
mago guard      # architectural guards
```

- `mago format --check`, `mago lint`, `mago analyze`, and `mago guard` must all
  pass with no suppression.
- PHPUnit runs in strict mode: `requireCoverageMetadata`, with
  `failOnNotice` / `failOnDeprecation` / `failOnRisky` / `failOnWarning`.
- Every class declares `#[CoversClass]` / `#[CoversMethod]`; value doubles use
  `createStub()`, interaction doubles use `createMock()` with `expects()`.
- Mutation coverage is enforced at 100% MSI by the CI workflow.

## Development mode

Development mode is the presence of `config/development.config.php`, linked from the committed
`config/development.config.php.dist`. The config aggregator loads it last, so it turns the `debug`
flag on and configuration caching off.

The console ships the command that toggles it, so a consumer needs no script of its own:

```bash
php bin/webware dev:mode --status    # report whether development mode is enabled
php bin/webware dev:mode --enable    # link the dist file into place
php bin/webware dev:mode --disable   # remove the active file
php bin/webware dev:mode --auto-composer   # follow COMPOSER_DEV_MODE
```

The full behaviour contract, including every deliberate difference from
`laminas/laminas-development-mode`, is in [`dev-mode.md`](dev-mode.md).

Enabling and disabling both drop the aggregated config cache: a cache written while one state was in
force is stale for the other, and development mode only means anything if config changes take effect
immediately.

The cache is dropped on every invocation, including when the requested state is already in force. The
aggregator serves an existing cache without consulting a single provider, so a surviving cache is
what lets a stale state outlive the toggle that was meant to end it. The cache path comes from the
application's own `config_cache_path` rather than a hardcoded location.

Paths resolve against the working directory, which `bin/webware` sets to the project root. A failure
— a missing dist file, a target that cannot be written, an active file that cannot be removed —
returns a non-zero exit code.

## CI

`.github/workflows/continuous-integration.yml` delegates to the shared
`webinertia/webware-tools` workflow, running the full matrix (PHP 8.4 and 8.5),
Mago, Codecov, and mutation testing.
