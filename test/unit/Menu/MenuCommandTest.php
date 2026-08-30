<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Terminal\Event;
use Psl\Terminal\Frame;
use Psr\Container\ContainerInterface;
use RuntimeException;
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
use Webware\Console\Test\Unit\Container\Fixture\FailingCommand;
use Webware\Console\Test\Unit\Container\Fixture\FooCommand;

use function implode;
use function rtrim;

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
    public function testExecuteQuitsOnCtrlC(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('ctrl+c')],
        ]);

        $status = $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertSame(Command::SUCCESS, $status);
        static::assertSame(1, $console->stopCount);
    }

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
    public function testExecuteShowsTheFailureResult(): void
    {
        $fetched = [];
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('down'), Event\Key::named('down'), Event\Key::named('enter')],
            [Event\Key::named('ctrl+c')],
        ]);

        $this->buildCommand($console, $fetched)->run(new ArrayInput([]), new NullOutput());

        static::assertContains(FailingCommand::class, $fetched);
        static::assertStringContainsString('Status: failure', $this->text($console->frames()[1]));
    }

    #[Test]
    public function testExecuteShowsTheSuccessResult(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('down'), Event\Key::named('enter')],
            [Event\Key::named('ctrl+c')],
        ]);

        $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertStringContainsString('Status: success', $this->text($console->frames()[1]));
    }

    #[Test]
    public function testRunMenuClearsHelpOnTheNextKey(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('h'), Event\Key::named('h')],
        ]);

        $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertStringNotContainsString('Foo command.', $this->text($console->lastFrame()));
    }

    #[Test]
    public function testRunMenuMovesDownToTheNextCommand(): void
    {
        $fetched = [];
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('down'), Event\Key::named('enter')],
            [Event\Key::named('enter')],
            [Event\Key::named('q')],
        ]);

        $this->buildCommand($console, $fetched)->run(new ArrayInput([]), new NullOutput());

        static::assertContains(BarCommand::class, $fetched);
        static::assertNotContains(FooCommand::class, $fetched);
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
    public function testRunMenuMovesUpWrappingToTheLastCommand(): void
    {
        $fetched = [];
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('up'), Event\Key::named('enter')],
            [Event\Key::named('enter')],
            [Event\Key::named('q')],
        ]);

        $this->buildCommand($console, $fetched)->run(new ArrayInput([]), new NullOutput());

        static::assertContains(FailingCommand::class, $fetched);
        static::assertNotContains(FooCommand::class, $fetched);
    }

    #[Test]
    public function testRunMenuRendersHelpForTheFocusedCommand(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('h')],
        ]);

        $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertStringContainsString('Foo command.', $this->text($console->lastFrame()));
    }

    #[Test]
    public function testRunMenuRendersMenuItems(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('q')],
        ]);

        $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertStringContainsString('foo', $this->text($console->lastFrame()));
        static::assertStringContainsString('bar', $this->text($console->lastFrame()));
    }

    /**
     * @param list<string> $fetchedClassNames Records every class the container resolved.
     */
    private function buildCommand(FakeConsole $console, array &$fetchedClassNames = []): MenuCommand
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')
            ->willReturnCallback(
                static function (string $id) use (&$fetchedClassNames): Command {
                    $fetchedClassNames[] = $id;

                    return match ($id) {
                        FooCommand::class     => new FooCommand(),
                        BarCommand::class     => new BarCommand(),
                        FailingCommand::class => new FailingCommand(),
                        default               => throw new RuntimeException("Unexpected command: {$id}"),
                    };
                },
            );

        $loader = new ContainerCommandLoader($container, [
            'foo'     => FooCommand::class,
            'bar'     => BarCommand::class,
            'failing' => FailingCommand::class,
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

    private function text(Frame $frame): string
    {
        $lines = [];

        for ($y = 0; $y < $frame->buffer()->getHeight(); $y++) {
            $line = '';

            for ($x = 0; $x < $frame->buffer()->getWidth(); $x++) {
                $cell = $frame->buffer()->get($x, $y);
                $line .= null === $cell ? ' ' : $cell->grapheme;
            }

            $lines[] = rtrim($line);
        }

        return implode("\n", $lines);
    }
}
