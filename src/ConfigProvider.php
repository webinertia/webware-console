<?php

declare(strict_types=1);

namespace Webware\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Webware\Console\Container\AppFactory;
use Webware\Console\Container\CommandLoaderFactory;
use Webware\Console\Container\MenuCommandFactory;
use Webware\Console\Container\PslTerminalFactoryFactory;
use Webware\Console\Container\SymfonyFactory;
use Webware\Console\Menu\MenuCommand;

/**
 * Registers the console Application, command loader, and menu command.
 *
 * @internal
 */
final readonly class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                'aliases'    => [
                    ConsoleInterface::class => App::class,
                ],
                'invokables' => [
                    PslTerminalFactoryFactory::class => PslTerminalFactoryFactory::class,
                ],
                'factories'  => [
                    App::class                    => AppFactory::class,
                    Application::class            => SymfonyFactory::class,
                    ContainerCommandLoader::class => CommandLoaderFactory::class,
                    MenuCommand::class            => MenuCommandFactory::class,
                ],
            ],
        ];
    }
}
