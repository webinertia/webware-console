# Command discovery

Commands are discovered at runtime — there is no hardcoded list. Components
register their commands under the `ConsoleInterface::class` config key.

## The contract

`Webware\Console\ConsoleInterface` is the stable discovery contract: a marker
interface whose `::class` is the config key. A component's `ConfigProvider`
contributes a `commands` map of command name to command class:

```php
use Webware\Console\ConsoleInterface;

return [
    ConsoleInterface::class => [
        'commands' => [
            'migrate'  => MigrateCommand::class,
            'status'   => StatusCommand::class,
            'rollback' => RollbackCommand::class,
        ],
    ],
];
```

Each entry is a Symfony Console `Command` — with a name, description, and
declared arguments and options. The console presents and invokes the command; it
does not reimplement its logic.

## Lazy loading

Commands are loaded through a Symfony `ContainerCommandLoader`: the container
resolves a command class only when it is invoked, never when the menu is built.

## Mezzio tooling

In addition to the `ConsoleInterface::class` key, the loader merges in
`config['laminas-cli']['commands']` — mezzio-tooling's own key — so mezzio-tooling
commands are discovered for free.

## Duplicate names

Two components registering the same command name throw a
`Webware\Console\Exception\DuplicateCommandException` rather than silently hiding
or misrunning one of them.
