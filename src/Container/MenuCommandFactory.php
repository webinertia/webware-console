<?php

declare(strict_types=1);

namespace Webware\Console\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleExceptionInterface;
use Webware\Console\ConsoleInterface;
use Webware\Console\Help\HelpFormatter;
use Webware\Console\Menu\MenuCommand;
use Webware\Console\Menu\MenuRenderer;
use Webware\Console\Prompt\CommandInputPrompter;
use Webware\Console\Runner\CommandRunner;

/**
 * @internal
 */
final readonly class MenuCommandFactory
{
    /**
     * @throws ConsoleExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): MenuCommand
    {
        $loader  = $container->get(ContainerCommandLoader::class);
        $console = $container->get(ConsoleInterface::class);

        return new MenuCommand(
            $console,
            $loader,
            new MenuRenderer(),
            new HelpFormatter(),
            new CommandInputPrompter($console),
            new CommandRunner(),
        );
    }
}
