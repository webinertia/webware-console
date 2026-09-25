<?php

declare(strict_types=1);

namespace Webware\Console\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Exception\LogicException;
use Webware\Console\DevelopmentModeCommand;

use function is_array;
use function is_string;

/**
 * @internal
 */
final readonly class DevelopmentModeCommandFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function configCachePath(ContainerInterface $container): ?string
    {
        /** @var mixed $config */
        $config = $container->get('config');

        if (! is_array($config)) {
            return null;
        }

        /** @var mixed $cachePath */
        $cachePath = $config['config_cache_path'] ?? null;

        return is_string($cachePath) ? $cachePath : null;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): DevelopmentModeCommand
    {
        return new DevelopmentModeCommand(
            configCachePath: $this->configCachePath($container),
        );
    }
}
