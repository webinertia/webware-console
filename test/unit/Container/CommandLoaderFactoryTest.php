<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Webware\Console\ConsoleInterface;
use Webware\Console\Container\CommandLoaderFactory;
use Webware\Console\Exception\DuplicateCommandException;
use Webware\Console\Test\Unit\Container\Fixture\BarCommand;
use Webware\Console\Test\Unit\Container\Fixture\FooCommand;

#[CoversClass(CommandLoaderFactory::class)]
#[CoversMethod(CommandLoaderFactory::class, '__invoke')]
#[CoversMethod(CommandLoaderFactory::class, 'commandSources')]
final class CommandLoaderFactoryTest extends TestCase
{
    #[Test]
    public function testBuildsLoaderFromOwnAndLaminasSources(): void
    {
        $config = [
            ConsoleInterface::class => [
                'commands' => ['foo' => FooCommand::class],
            ],
            'laminas-cli'           => [
                'commands' => ['bar' => BarCommand::class],
            ],
        ];

        $loader = $this->build($config);

        static::assertSame(['foo', 'bar'], $loader->getNames());
    }

    #[Test]
    public function testEmptyConfigYieldsEmptyLoader(): void
    {
        $loader = $this->build([]);

        static::assertSame([], $loader->getNames());
    }

    #[Test]
    public function testIgnoresSourceWhoseCommandsAreNotAnArray(): void
    {
        $config = [
            ConsoleInterface::class => [
                'commands' => 'not-an-array',
            ],
            'laminas-cli'           => [
                'commands' => ['bar' => BarCommand::class],
            ],
        ];

        $loader = $this->build($config);

        static::assertSame(['bar'], $loader->getNames());
    }

    #[Test]
    public function testLoaderResolvesCommandsFromTheContainer(): void
    {
        $config = [
            ConsoleInterface::class => [
                'commands' => ['foo' => FooCommand::class],
            ],
        ];

        $loader = $this->build($config);

        static::assertInstanceOf(FooCommand::class, $loader->get(name: 'foo'));
    }

    #[Test]
    public function testNonArrayConfigYieldsEmptyLoader(): void
    {
        $container = $this->createStub(ContainerInterface::class);

        $loader = (new CommandLoaderFactory())($container);

        static::assertSame([], $loader->getNames());
    }

    #[Test]
    public function testSkipsNonArraySourceAndStillReadsTheLaminasSource(): void
    {
        $config = [
            ConsoleInterface::class => 'not-an-array',
            'laminas-cli'           => [
                'commands' => ['bar' => BarCommand::class],
            ],
        ];

        $loader = $this->build($config);

        static::assertSame(['bar'], $loader->getNames());
    }

    #[Test]
    public function testThrowsWhenDuplicateNamesAcrossSources(): void
    {
        $config = [
            ConsoleInterface::class => [
                'commands' => ['dup' => FooCommand::class],
            ],
            'laminas-cli'           => [
                'commands' => ['dup' => BarCommand::class],
            ],
        ];

        $this->expectException(DuplicateCommandException::class);

        $this->build($config);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function build(array $config): ContainerCommandLoader
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')
            ->willReturnMap([
                ['config', $config],
                [FooCommand::class, new FooCommand()],
                [BarCommand::class, new BarCommand()],
            ]);

        return (new CommandLoaderFactory())($container);
    }
}
