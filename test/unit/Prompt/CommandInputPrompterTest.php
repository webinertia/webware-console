<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Terminal\Event;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Webware\Console\Prompt\CommandInputPrompter;
use Webware\Console\Prompt\FieldKind;
use Webware\Console\Prompt\PromptField;
use Webware\Console\Prompt\PromptState;
use Webware\Console\Test\Unit\Console\Fixture\FakeConsole;

#[CoversClass(CommandInputPrompter::class)]
#[CoversMethod(CommandInputPrompter::class, '__construct')]
#[CoversMethod(CommandInputPrompter::class, 'toParameters')]
#[CoversMethod(CommandInputPrompter::class, 'prompt')]
#[CoversMethod(CommandInputPrompter::class, 'confirm')]
#[CoversMethod(CommandInputPrompter::class, 'onKey')]
#[CoversMethod(CommandInputPrompter::class, 'render')]
final class CommandInputPrompterTest extends TestCase
{
    #[Test]
    public function testConfirmReturnsDefaultOnEnter(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
        ]);

        static::assertTrue(new CommandInputPrompter($console)->confirm('Proceed?', default: true));
        static::assertFalse(new CommandInputPrompter($console)->confirm('Abort?', default: false));
    }

    #[Test]
    public function testConfirmReturnsFalseOnEscape(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('escape')],
        ]);

        static::assertFalse(new CommandInputPrompter($console)->confirm('Proceed?', default: true));
    }

    #[Test]
    public function testConfirmReturnsFalseOnN(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::char('n')],
        ]);

        static::assertFalse(new CommandInputPrompter($console)->confirm('Proceed?', default: true));
    }

    #[Test]
    public function testConfirmReturnsTrueOnY(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::char('y')],
        ]);

        static::assertTrue(new CommandInputPrompter($console)->confirm('Proceed?', default: true));
    }

    #[Test]
    public function testOnKeyIgnoresWhenThereAreNoFields(): void
    {
        $state = new PromptState([]);

        CommandInputPrompter::onKey(Event\Key::named('tab'), $state);

        static::assertSame(0, $state->activeIndex);
        static::assertFalse($state->submitted);
        static::assertFalse($state->cancelled);
    }

    #[Test]
    public function testPromptCollectsValuesThroughTheKeyLoop(): void
    {
        $console = new FakeConsole()->withScripts([
            [
                Event\Key::char('x'),
                Event\Key::char('y'),
                Event\Key::named('left'),
                Event\Key::char('z'),
                Event\Key::named('right'),
                Event\Key::named('backspace'),
                Event\Key::named('tab'),
                Event\Key::char('w'),
                Event\Key::named('enter'),
                Event\Key::named('tab'),
                Event\Key::named('up'),
                Event\Key::char(' '),
                Event\Key::char('x'),
                Event\Key::named('enter'),
            ],
        ]);

        $input = new CommandInputPrompter($console)->prompt($this->command());

        static::assertInstanceOf(ArrayInput::class, $input);
        static::assertSame('xz', $input->getArgument('version'));
        static::assertSame('w', $input->getArgument('label'));
        static::assertTrue($input->getOption('dry-run'));
    }

    #[Test]
    public function testPromptReturnsNullWhenCancelled(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('escape')],
        ]);

        $input = new CommandInputPrompter($console)->prompt($this->command());

        static::assertNull($input);
    }

    #[Test]
    public function testPromptSkipsInteractionWhenNoFieldsRemain(): void
    {
        $command = new SymfonyCommand(name: 'list');

        $input = new CommandInputPrompter(new FakeConsole())->prompt($command);

        static::assertInstanceOf(ArrayInput::class, $input);
    }

    #[Test]
    public function testPromptSkipsPresetValues(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
        ]);

        $input = new CommandInputPrompter($console)->prompt(
            $this->command(),
            presetValues: [
                'version'   => 'preset',
                '--dry-run' => true,
            ],
        );

        static::assertInstanceOf(ArrayInput::class, $input);
        static::assertSame('preset', $input->getArgument('version'));
        static::assertNull($input->getArgument('label'));
        static::assertTrue($input->getOption('dry-run'));
    }

    #[Test]
    public function testToParametersMapsArgumentOptionAndFlagFields(): void
    {
        $fields = [
            new PromptField(
                name       : 'table',
                description: '',
                kind       : FieldKind::Argument,
                required   : true,
                isArray    : false,
                default    : 'users',
            ),
            new PromptField(
                name       : 'columns',
                description: '',
                kind       : FieldKind::Option,
                required   : false,
                isArray    : true,
                default    : 'a,b',
            ),
            new PromptField(
                name       : 'dry-run',
                description: '',
                kind       : FieldKind::Flag,
                required   : false,
                isArray    : false,
                default    : true,
            ),
            new PromptField(
                name       : 'force',
                description: '',
                kind       : FieldKind::Flag,
                required   : false,
                isArray    : false,
            ),
        ];

        static::assertSame(
            [
                'table'     => 'users',
                '--columns' => ['a', 'b'],
                '--dry-run' => true,
            ],
            CommandInputPrompter::toParameters($fields),
        );
    }

    #[Test]
    public function testToParametersSkipsEmptyOptionalValues(): void
    {
        $fields = [
            new PromptField(
                name       : 'path',
                description: '',
                kind       : FieldKind::Argument,
                required   : false,
                isArray    : false,
            ),
        ];

        static::assertSame([], CommandInputPrompter::toParameters($fields));
    }

    private function command(): SymfonyCommand
    {
        $command = new SymfonyCommand(name: 'migrate');
        $command->addArgument(
            name       : 'version',
            mode       : InputArgument::REQUIRED,
            description: 'Version.',
        );
        $command->addArgument(
            name       : 'label',
            mode       : InputArgument::OPTIONAL,
            description: 'Label.',
        );
        $command->addOption(
            name       : 'dry-run',
            mode       : InputOption::VALUE_NONE,
            description: 'Dry run.',
        );

        return $command;
    }
}
