# Contract: Command Discovery

How commands are registered and surfaced.

## Contract

- The stable discovery contract is the `Webware\Console\ConsoleInterface` marker — its `::class` is the config key.
- Components register commands under the config key `ConsoleInterface::class`, as a `commands` map of name → command class:

  ```php
  Webware\Console\ConsoleInterface::class => [
      'commands' => [
          'migrate'  => Some\Command::class,
          'status'   => Some\Command::class,
          'rollback' => Some\Command::class,
      ],
  ],
  ```

## Registration

- Components expose their commands through their `ConfigProvider` under the `ConsoleInterface::class` key — no hardcoded list in the console.
- `CommandLoaderFactory` reads `config[ConsoleInterface::class]['commands']` and merges in `config['laminas-cli']['commands']` (mezzio-tooling's key), so mezzio-tooling commands are discovered for free.
- The console builds a Symfony `ContainerCommandLoader` from the merged map; commands are resolved lazily from the container only when invoked.

## Duplicate names

- A duplicate command name MUST be surfaced (`DuplicateCommandException`) rather than silently dropping one.

## Empty command set

- An empty command set is valid; the console MUST still launch and report that no commands are available.
