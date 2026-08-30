<?php

declare(strict_types=1);

namespace Webware\Console\Container;

use Psl\Terminal\Application as TerminalApplication;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Console\App;

/**
 * Builds the console application around the runtime terminal factory.
 *
 * @internal
 */
final readonly class AppFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): App
    {
        $factoryFactory = $container->get(PslTerminalFactoryFactory::class);

        /** @var callable(object, string): TerminalApplication $factory */
        $factory = $factoryFactory();

        return new App($factory);
    }
}
