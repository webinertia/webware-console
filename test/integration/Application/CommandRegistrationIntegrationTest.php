<?php

declare(strict_types=1);

namespace Webware\Console\Test\Integration\Application;

use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ServiceManager\ConfigProvider as ServiceManagerConfigProvider;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Webware\Console\ConfigProvider;
use Webware\Console\ConsoleInterface;
use Webware\Console\Container\AppFactory;
use Webware\Console\Container\MenuCommandFactory;
use Webware\Console\Container\PslTerminalFactoryFactory;
use Webware\Console\Container\SymfonyFactory;
use Webware\Console\Menu\MenuCommand;
use Webware\Console\Test\Unit\Container\Fixture\BarCommand;
use Webware\Console\Test\Unit\Container\Fixture\FooCommand;

#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, '__invoke')]
#[CoversClass(SymfonyFactory::class)]
#[CoversMethod(SymfonyFactory::class, '__invoke')]
#[CoversClass(MenuCommandFactory::class)]
#[CoversMethod(MenuCommandFactory::class, '__invoke')]
#[CoversClass(AppFactory::class)]
#[CoversMethod(AppFactory::class, '__invoke')]
#[CoversClass(PslTerminalFactoryFactory::class)]
#[CoversMethod(PslTerminalFactoryFactory::class, '__invoke')]
final class CommandRegistrationIntegrationTest extends TestCase
{
    #[Test]
    public function testApplicationListsHostAndComponentCommands(): void
    {
        $config = new ConfigAggregator([
            ServiceManagerConfigProvider::class,
            ConfigProvider::class,
            new ArrayProvider([
                ConsoleInterface::class => [
                    'commands' => [
                        'foo' => FooCommand::class,
                        'bar' => BarCommand::class,
                    ],
                ],
            ]),
        ])->getMergedConfig();

        $dependencies                                  = $config['dependencies'];
        $dependencies['invokables']                    ??= [];
        $dependencies['invokables'][FooCommand::class] = FooCommand::class;
        $dependencies['invokables'][BarCommand::class] = BarCommand::class;
        $dependencies['services']                      ??= [];
        $dependencies['services']['config']            = $config;

        $container = new ServiceManager($dependencies);

        /** @var Application $application */
        $application = $container->get(Application::class);

        static::assertInstanceOf(MenuCommand::class, $application->find('menu'));
        static::assertInstanceOf(FooCommand::class, $application->find('foo'));
        static::assertInstanceOf(BarCommand::class, $application->find('bar'));
    }
}
