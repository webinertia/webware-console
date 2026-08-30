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

## CI

`.github/workflows/continuous-integration.yml` delegates to the shared
`webinertia/webware-tools` workflow, running the full matrix (PHP 8.4 and 8.5),
Mago, Codecov, and mutation testing.
