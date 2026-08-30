<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Terminal\Event;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Webware\Console\Help\HelpFormatter;
use Webware\Console\Menu\MenuCommand;
use Webware\Console\Menu\MenuRenderer;
use Webware\Console\Prompt\CommandInputPrompter;
use Webware\Console\Runner\CommandRunner;
use Webware\Console\Test\Unit\Console\Fixture\FakeConsole;
use Webware\Console\Test\Unit\Container\Fixture\BarCommand;
use Webware\Console\Test\Unit\Container\Fixture\FooCommand;

#[CoversClass(MenuCommand::class)]
#[CoversMethod(MenuCommand::class, '__construct')]
#[CoversMethod(MenuCommand::class, 'execute')]
#[CoversMethod(MenuCommand::class, 'formatHelp')]
#[CoversMethod(MenuCommand::class, 'formatResult')]
#[CoversMethod(MenuCommand::class, 'runMenu')]
#[CoversMethod(MenuCommand::class, 'showResult')]
final class MenuCommandTest extends TestCase
{
    #[Test]
    public function testExecuteReturnsSuccessWhenQuit(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('q')],
        ]);

        $status = $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertSame(Command::SUCCESS, $status);
        static::assertSame(1, $console->stopCount);
    }

    #[Test]
    public function testExecuteReturnsToTheMenuWhenPromptIsCancelled(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
            [Event\Key::named('escape')],
            [Event\Key::named('q')],
        ]);

        $status = $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertSame(Command::SUCCESS, $status);
    }

    #[Test]
    public function testExecuteRunsTheSelectedCommandAndReturnsToTheMenu(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('h'), Event\Key::named('down'), Event\Key::named('enter')],
            [Event\Key::named('enter')],
            [Event\Key::named('ctrl+c')],
        ]);

        $status = $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertSame(Command::SUCCESS, $status);
        static::assertSame(3, $console->stopCount);
    }

    #[Test]
    public function testRunMenuMovesDownUpAndQuits(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('down'), Event\Key::named('up'), Event\Key::named('q')],
        ]);

        $status = $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertSame(Command::SUCCESS, $status);
    }

    #[Test]
    public function testRunMenuRendersHelpForTheFocusedCommand(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('h')],
        ]);

        $status = $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertSame(Command::SUCCESS, $status);
    }

    private function buildCommand(FakeConsole $console): MenuCommand
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')
            ->willReturnMap([
                [FooCommand::class, new FooCommand()],
                [BarCommand::class, new BarCommand()],
            ]);

        $loader = new ContainerCommandLoader($container, [
            'foo' => FooCommand::class,
            'bar' => BarCommand::class,
        ]);

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
