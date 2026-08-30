<?php

declare(strict_types=1);

namespace Webware\Console\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Webware\Console\ConsoleInterface;
use Webware\Console\Exception\DuplicateCommandException;

use function array_key_exists;
use function is_array;
use function sprintf;

/**
 * Builds a {@see ContainerCommandLoader} from commands registered under the
 * {@see ConsoleInterface} config key and the `laminas-cli` config key.
 *
 * Commands are resolved lazily by Symfony only when invoked; no command is
 * instantiated here.
 *
 * @internal
 */
final class CommandLoaderFactory
{
    /**
     * @param array<array-key, mixed> $config
     * @return list<array<string, class-string>>
     */
    private function commandSources(array $config): array
    {
        $sources = [];

        foreach ([ConsoleInterface::class, 'laminas-cli'] as $key) {
            /** @var mixed $source */
            $source = $config[$key] ?? null;

            if (! is_array($source)) {
                continue;
            }

            /** @var mixed $commands */
            $commands = $source['commands'] ?? [];

            if (! is_array($commands)) {
                continue;
            }

            /** @var array<string, class-string> $commands */
            $sources[] = $commands;
        }

        return $sources;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws DuplicateCommandException
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ContainerCommandLoader
    {
        /** @var mixed $config */
        $config = $container->get('config');

        if (! is_array($config)) {
            return new ContainerCommandLoader($container, []);
        }

        $map = [];

        foreach ($this->commandSources($config) as $commands) {
            foreach ($commands as $name => $class) {
                if (array_key_exists($name, $map)) {
                    throw new DuplicateCommandException(
                        message: sprintf('Duplicate command name "%s".', $name),
                    );
                }

                $map[$name] = $class;
            }
        }

        return new ContainerCommandLoader($container, $map);
    }
}
