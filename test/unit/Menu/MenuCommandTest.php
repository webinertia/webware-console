<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Menu;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Ansi\ControlSequenceIntroducer;
use Psl\Terminal\Event;
use Psl\Terminal\Frame;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Webware\Console\DevelopmentMode;
use Webware\Console\DevelopmentModeCommand;
use Webware\Console\Help\HelpFormatter;
use Webware\Console\Menu\MenuCommand;
use Webware\Console\Menu\MenuRenderer;
use Webware\Console\Prompt\CommandInputPrompter;
use Webware\Console\Runner\CommandRunner;
use Webware\Console\Test\Unit\Console\Fixture\FakeConsole;
use Webware\Console\Test\Unit\Container\Fixture\BarCommand;
use Webware\Console\Test\Unit\Container\Fixture\FailingCommand;
use Webware\Console\Test\Unit\Container\Fixture\FooCommand;

use function array_map;
use function bin2hex;
use function chdir;
use function dirname;
use function file_put_contents;
use function getcwd;
use function implode;
use function is_dir;
use function mkdir;
use function Psl\Ansi\Color\green;
use function Psl\Ansi\Color\red;
use function Psl\Ansi\foreground;
use function random_bytes;
use function rmdir;
use function rtrim;
use function scandir;
use function sprintf;
use function str_starts_with;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(MenuCommand::class)]
#[CoversClass(DevelopmentMode::class)]
#[CoversClass(DevelopmentModeCommand::class)]
#[CoversMethod(MenuCommand::class, '__construct')]
#[CoversMethod(MenuCommand::class, 'execute')]
#[CoversMethod(MenuCommand::class, 'formatHelp')]
#[CoversMethod(MenuCommand::class, 'formatResult')]
#[CoversMethod(MenuCommand::class, 'runMenu')]
#[CoversMethod(MenuCommand::class, 'showResult')]
final class MenuCommandTest extends TestCase
{
    private const string CACHE_FILE = 'data/cache/config-cache.php';

    private string $projectRoot = '';

    private string $originalDirectory = '';

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
    public function testExecuteRefusesABlankRequiredArgumentInsteadOfRunning(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
            [Event\Key::named('enter')],
            [Event\Key::char('f'), Event\Key::named('enter')],
            [Event\Key::named('ctrl+c')],
        ]);

        $status = $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('Required: name', $this->text($console->frames()[1]));
        // menu, refused prompt, submitted prompt, result — the first Enter ran nothing.
        static::assertSame(4, $console->runCount);
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
            [Event\Key::char('f'), Event\Key::named('enter')],
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
        static::assertStringContainsString('Status: command failed (1)', $this->text($console->frames()[1]));
        static::assertSame(
            $this->sequenceStrings([foreground(red())]),
            $this->sequenceStrings($this->statusStyle($console->frames()[1])),
        );
    }

    #[Test]
    public function testExecuteShowsTheSuccessResult(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('down'), Event\Key::named('enter')],
            [Event\Key::named('ctrl+c')],
        ]);

        $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        static::assertStringContainsString('Status: command successful', $this->text($console->frames()[1]));
        static::assertSame(
            $this->sequenceStrings([foreground(green())]),
            $this->sequenceStrings($this->statusStyle($console->frames()[1])),
        );
    }

    #[Test]
    public function testLaysOutTheResultBlocks(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('down'), Event\Key::named('down'), Event\Key::named('enter')],
            [Event\Key::named('ctrl+c')],
        ]);

        $this->buildCommand($console)->run(new ArrayInput([]), new NullOutput());

        $frame = $console->frames()[1];

        static::assertSame('Boom.', $this->row($frame, 0));
        static::assertSame(2, $this->rowIndex($frame, 'Status: command failed (1)'));
        static::assertSame(4, $this->rowIndex($frame, 'Press any key to return to the menu.'));
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
     * The menu drives the prompt from a command's own definition, so a wrapped
     * command has to be exercised through it, not only on its own.
     */
    #[Test]
    public function testRunsDevModeThroughThePrompt(): void
    {
        file_put_contents(
            filename: DevelopmentMode::ACTIVE_FILE,
            data    : 'active file',
        );
        $this->writeConfigCache();

        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
            [Event\Key::named('down'), Event\Key::char(' '), Event\Key::named('enter')],
            [Event\Key::named('enter')],
            [Event\Key::named('ctrl+c')],
        ]);

        $this->buildDevModeMenu($console)->run(new ArrayInput([]), new NullOutput());

        $text = $this->allText($console);

        static::assertStringContainsString('Status: command successful', $text);
        static::assertStringContainsString('Development mode DISABLED.', $text);
        // The frame is 40 columns wide, so the path itself is clipped here; the
        // removal is asserted against the filesystem below.
        static::assertStringContainsString('Removed the config cache at "data/cache/', $text);
        static::assertFileDoesNotExist(DevelopmentMode::ACTIVE_FILE);
        static::assertFileDoesNotExist(self::CACHE_FILE);
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $origin = getcwd();

        if (false === $origin) {
            static::fail(message: 'Unable to determine the working directory.');
        }

        $this->originalDirectory = $origin;
        $this->projectRoot       = sprintf(
            '%s/webware-menu-%s',
            sys_get_temp_dir(),
            bin2hex(random_bytes(8)),
        );

        mkdir(
            directory  : "{$this->projectRoot}/config",
            permissions: 0o777,
            recursive  : true,
        );

        // The command resolves every path against the working directory, which
        // the console binary sets to the project root.
        chdir(directory: $this->projectRoot);
    }

    #[Override]
    protected function tearDown(): void
    {
        chdir(directory: $this->originalDirectory);
        $this->removeDirectory($this->projectRoot);

        parent::tearDown();
    }

    private function allText(FakeConsole $console): string
    {
        $lines = [];

        foreach ($console->frames() as $frame) {
            $lines[] = $this->text($frame);
        }

        return implode("\n", $lines);
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

    /**
     * A menu over the real command, so the prompt is built from the definition
     * the application actually registers.
     */
    private function buildDevModeMenu(FakeConsole $console): MenuCommand
    {
        $command = new DevelopmentModeCommand(configCachePath: self::CACHE_FILE);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($command);

        return new MenuCommand(
            $console,
            new ContainerCommandLoader($container, ['dev:mode' => DevelopmentModeCommand::class]),
            new MenuRenderer(),
            new HelpFormatter(),
            new CommandInputPrompter($console),
            new CommandRunner(),
        );
    }

    private function removeDirectory(string $path): void
    {
        if (false === is_dir(filename: $path)) {
            return;
        }

        $entries = scandir(directory: $path);

        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $full = "{$path}/{$entry}";

            if (is_dir(filename: $full)) {
                $this->removeDirectory($full);

                continue;
            }

            unlink(filename: $full);
        }

        rmdir(directory: $path);
    }

    private function row(Frame $frame, int $y): string
    {
        $line = '';

        for ($x = 0; $x < $frame->buffer()->getWidth(); $x++) {
            $cell = $frame->buffer()->get($x, $y);
            $line .= null === $cell ? ' ' : $cell->grapheme;
        }

        return rtrim($line);
    }

    private function rowIndex(Frame $frame, string $prefix): int
    {
        for ($y = 0; $y < $frame->buffer()->getHeight(); $y++) {
            if (str_starts_with($this->row($frame, $y), $prefix)) {
                return $y;
            }
        }

        return -1;
    }

    /**
     * @param list<ControlSequenceIntroducer> $style
     *
     * @return list<string>
     */
    private function sequenceStrings(array $style): array
    {
        return array_map(static fn(ControlSequenceIntroducer $sequence): string => (string) $sequence, $style);
    }

    /** @return list<ControlSequenceIntroducer> */
    private function statusStyle(Frame $frame): array
    {
        $y = $this->rowIndex($frame, 'Status: ');

        return $y < 0 ? [] : $frame->buffer()->get(0, $y)->style ?? [];
    }

    private function text(Frame $frame): string
    {
        $lines = [];

        for ($y = 0; $y < $frame->buffer()->getHeight(); $y++) {
            $lines[] = $this->row($frame, $y);
        }

        return implode("\n", $lines);
    }

    private function writeConfigCache(): void
    {
        mkdir(
            directory  : dirname(path: self::CACHE_FILE),
            permissions: 0o777,
            recursive  : true,
        );
        file_put_contents(
            filename: self::CACHE_FILE,
            data    : 'cached configuration',
        );
    }
}
