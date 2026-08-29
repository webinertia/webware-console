# Contract: Command Catalog

How commands are discovered and surfaced.

## Registration

- Components expose their commands through their configuration (config provider / container), registering each command by name.
- The console builds a `CommandCatalog` from the registered commands at launch — no hardcoded list.

## Duplicate names

- A duplicate command name MUST be surfaced (disambiguated or flagged) rather than silently dropping one.

## Empty catalog

- An empty catalog is valid; the console MUST still launch and report that no commands are available.
