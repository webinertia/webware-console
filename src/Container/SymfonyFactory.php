<?php

declare(strict_types=1);

namespace Webware\Console\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Webware\Console\Menu\MenuCommand;

/**
 * @internal
 */
final readonly class SymfonyFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Application
    {
        $loader = $container->get(ContainerCommandLoader::class);

        $menuCommand = $container->get(MenuCommand::class);

        $application = new Application(
            name   : 'webware-console',
            version: '0.1.0',
        );
        $application->setCommandLoader($loader);
        $application->addCommand($menuCommand);

        return $application;
    }
}
