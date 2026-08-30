<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Terminal\Buffer;
use Psl\Terminal\Event;
use Psl\Terminal\Frame;
use Psl\Terminal\Rect;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Webware\Console\Prompt\CommandInputPrompter;
use Webware\Console\Prompt\FieldKind;
use Webware\Console\Prompt\PromptField;
use Webware\Console\Prompt\PromptState;
use Webware\Console\Test\Unit\Console\Fixture\FakeConsole;

use function implode;
use function rtrim;

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
    public function testConfirmDefaultsToTrue(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
        ]);

        static::assertTrue(new CommandInputPrompter($console)->confirm('Proceed?'));
        static::assertSame(1, $console->stopCount);
    }

    #[Test]
    public function testConfirmRendersTheDefaultHint(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
        ]);

        new CommandInputPrompter($console)->confirm('Proceed?', default: true);

        static::assertStringContainsString('[Y/n]', $this->text($console->lastFrame()));
    }

    #[Test]
    public function testConfirmRendersTheNegativeHint(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
        ]);

        new CommandInputPrompter($console)->confirm('Abort?', default: false);

        static::assertStringContainsString('[y/N]', $this->text($console->lastFrame()));
    }

    #[Test]
    public function testConfirmReturnsDefaultOnEnter(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
            [Event\Key::named('enter')],
        ]);

        static::assertTrue(new CommandInputPrompter($console)->confirm('Proceed?', default: true));
        static::assertFalse(new CommandInputPrompter($console)->confirm('Abort?', default: false));
        static::assertSame(2, $console->stopCount);
    }

    #[Test]
    public function testConfirmReturnsFalseOnEscape(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('escape')],
        ]);

        static::assertFalse(new CommandInputPrompter($console)->confirm('Proceed?', default: true));
        static::assertSame(1, $console->stopCount);
    }

    #[Test]
    public function testConfirmReturnsFalseOnN(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::char('n')],
        ]);

        static::assertFalse(new CommandInputPrompter($console)->confirm('Proceed?', default: true));
        static::assertSame(1, $console->stopCount);
    }

    #[Test]
    public function testConfirmReturnsTrueOnY(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::char('y')],
        ]);

        static::assertTrue(new CommandInputPrompter($console)->confirm('Proceed?', default: true));
        static::assertSame(1, $console->stopCount);
    }

    #[Test]
    public function testOnKeyDownAdvances(): void
    {
        $state = new PromptState([
            $this->promptField(FieldKind::Argument),
            $this->promptField(FieldKind::Argument),
        ]);

        CommandInputPrompter::onKey(Event\Key::named('down'), $state);

        static::assertSame(1, $state->activeIndex);
    }

    #[Test]
    public function testOnKeyEscapeCancels(): void
    {
        $state = new PromptState([$this->promptField(FieldKind::Argument)]);

        CommandInputPrompter::onKey(Event\Key::named('escape'), $state);

        static::assertTrue($state->cancelled);
    }

    #[Test]
    public function testOnKeyIgnoresUnhandledNamedKeys(): void
    {
        $state = new PromptState([$this->promptField(FieldKind::Argument)]);

        CommandInputPrompter::onKey(Event\Key::named('delete'), $state);

        static::assertSame(0, $state->activeIndex);
        static::assertSame('', $state->fields[0]->value);
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
    public function testOnKeyNamedSpaceTogglesAFlag(): void
    {
        $state = new PromptState([$this->promptField(FieldKind::Flag)]);

        CommandInputPrompter::onKey(Event\Key::named('space'), $state);

        static::assertTrue($state->fields[0]->flagValue);
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
        static::assertSame(1, $console->stopCount);
        static::assertStringContainsString('version:', $this->text($console->lastFrame()));
    }

    #[Test]
    public function testPromptContinuesPastPresetArguments(): void
    {
        $command = new SymfonyCommand(name: 'migrate');
        $command->addArgument(
            name       : 'first',
            mode       : InputArgument::REQUIRED,
            description: 'First.',
        );
        $command->addArgument(
            name       : 'second',
            mode       : InputArgument::REQUIRED,
            description: 'Second.',
        );

        $console = new FakeConsole()->withScripts([
            [Event\Key::char('v'), Event\Key::named('enter')],
        ]);

        $input = new CommandInputPrompter($console)->prompt($command, presetValues: ['first' => 'preset']);

        static::assertSame('preset', $input->getArgument('first'));
        static::assertSame('v', $input->getArgument('second'));
    }

    #[Test]
    public function testPromptContinuesPastPresetOptions(): void
    {
        $command = new SymfonyCommand(name: 'migrate');
        $command->addOption(
            name       : 'first',
            mode       : InputOption::VALUE_REQUIRED,
            description: 'First.',
        );
        $command->addOption(
            name       : 'second',
            mode       : InputOption::VALUE_REQUIRED,
            description: 'Second.',
        );

        $console = new FakeConsole()->withScripts([
            [Event\Key::char('v'), Event\Key::named('enter')],
        ]);

        $input = new CommandInputPrompter($console)->prompt($command, presetValues: ['--first' => 'preset']);

        static::assertSame('preset', $input->getOption('first'));
        static::assertSame('v', $input->getOption('second'));
    }

    #[Test]
    public function testPromptRendersAnArgumentDefaultAsTheSeed(): void
    {
        $command = new SymfonyCommand(name: 'migrate');
        $command->addArgument(
            name       : 'version',
            mode       : InputArgument::OPTIONAL,
            description: 'Version.',
            default    : 'abc',
        );

        $console = new FakeConsole()->withScripts([[]]);

        new CommandInputPrompter($console)->prompt($command);

        static::assertStringContainsString('abc', $this->text($console->lastFrame()));
    }

    #[Test]
    public function testPromptRendersAnOptionDefaultAsTheSeed(): void
    {
        $command = new SymfonyCommand(name: 'migrate');
        $command->addOption(
            name       : 'filter',
            mode       : InputOption::VALUE_REQUIRED,
            description: 'Filter.',
            default    : 'abc',
        );

        $console = new FakeConsole()->withScripts([[]]);

        new CommandInputPrompter($console)->prompt($command);

        static::assertStringContainsString('abc', $this->text($console->lastFrame()));
    }

    #[Test]
    public function testPromptReturnsNullWhenCancelled(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('escape')],
        ]);

        $input = new CommandInputPrompter($console)->prompt($this->command());

        static::assertNull($input);
        static::assertSame(1, $console->stopCount);
    }

    #[Test]
    public function testPromptSeedsAnArgumentDefault(): void
    {
        $command = new SymfonyCommand(name: 'migrate');
        $command->addArgument(
            name       : 'version',
            mode       : InputArgument::OPTIONAL,
            description: 'Version.',
            default    : 'abc',
        );

        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
        ]);

        $input = new CommandInputPrompter($console)->prompt($command);

        static::assertSame('abc', $input->getArgument('version'));
    }

    #[Test]
    public function testPromptSeedsAnOptionDefault(): void
    {
        $command = new SymfonyCommand(name: 'migrate');
        $command->addOption(
            name       : 'filter',
            mode       : InputOption::VALUE_REQUIRED,
            description: 'Filter.',
            default    : 'abc',
        );

        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
        ]);

        $input = new CommandInputPrompter($console)->prompt($command);

        static::assertSame('abc', $input->getOption('filter'));
    }

    #[Test]
    public function testPromptSkipsAnEmptyOptionalOption(): void
    {
        $command = new SymfonyCommand(name: 'migrate');
        $command->addOption(
            name       : 'filter',
            mode       : InputOption::VALUE_REQUIRED,
            description: 'Filter.',
        );

        $console = new FakeConsole()->withScripts([
            [Event\Key::named('enter')],
        ]);

        $input = new CommandInputPrompter($console)->prompt($command);

        static::assertNull($input->getOption('filter'));
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
    public function testPromptSkipsTheLoopWhenAllValuesArePreset(): void
    {
        $console = new FakeConsole()->withScripts([
            [Event\Key::named('escape')],
        ]);

        $input = new CommandInputPrompter($console)->prompt(
            $this->command(),
            presetValues: [
                'version'   => '1',
                'label'     => 'x',
                '--dry-run' => true,
            ],
        );

        static::assertInstanceOf(ArrayInput::class, $input);
        static::assertSame(0, $console->stopCount);
        static::assertSame(0, $console->runCount);
    }

    #[Test]
    public function testRenderContinuesPastFlagFields(): void
    {
        $state = new PromptState([
            $this->promptField(FieldKind::Flag, false),
            $this->promptField(FieldKind::Argument, 'hello'),
        ]);

        $frame = $this->frame();
        CommandInputPrompter::render($frame, $state);
        $text = $this->text($frame);

        static::assertStringContainsString('hello', $text);
    }

    #[Test]
    public function testRenderDrawsTheFooter(): void
    {
        $frame = $this->frame();

        CommandInputPrompter::render($frame, new PromptState([
            $this->promptField(FieldKind::Argument),
        ]));

        static::assertStringContainsString('Tab/Down: next field', $this->text($frame));
    }

    #[Test]
    public function testRenderMarksInactiveFieldsWithoutTheMarker(): void
    {
        $state = new PromptState([
            $this->promptField(FieldKind::Argument, 'a'),
            $this->promptField(FieldKind::Argument, 'b'),
        ]);
        $state->activeIndex = 1;

        $frame = $this->frame();
        CommandInputPrompter::render($frame, $state);
        $text = $this->text($frame);

        static::assertStringContainsString('  field:', $text);
        static::assertStringContainsString('> field:', $text);
    }

    #[Test]
    public function testRenderMarksTheActiveFieldAndDrawsItsValue(): void
    {
        $frame = $this->frame();

        CommandInputPrompter::render($frame, new PromptState([
            $this->promptField(FieldKind::Argument, 'hello'),
        ]));

        $text = $this->text($frame);

        static::assertStringContainsString('> field:', $text);
        static::assertStringContainsString('hello', $text);
    }

    #[Test]
    public function testRenderPlacesTheFooterBelowTheFields(): void
    {
        $frame = $this->frame();

        CommandInputPrompter::render($frame, new PromptState([
            $this->promptField(FieldKind::Argument, 'x'),
        ]));

        static::assertStringContainsString('Tab/Down: next field', $this->row($frame, 2));
        static::assertStringNotContainsString('Tab/Down: next field', $this->row($frame, 1));
    }

    #[Test]
    public function testRenderShowsACheckedFlag(): void
    {
        $state = new PromptState([
            $this->promptField(FieldKind::Flag, true),
        ]);

        $frame = $this->frame();
        CommandInputPrompter::render($frame, $state);
        $text = $this->text($frame);

        static::assertStringContainsString('[x]', $text);
        static::assertStringNotContainsString('[ ]', $text);
    }

    #[Test]
    public function testRenderShowsAnUncheckedFlag(): void
    {
        $state = new PromptState([
            $this->promptField(FieldKind::Flag, false),
        ]);

        $frame = $this->frame();
        CommandInputPrompter::render($frame, $state);
        $text = $this->text($frame);

        static::assertStringContainsString('[ ]', $text);
        static::assertStringNotContainsString('[x]', $text);
    }

    #[Test]
    public function testRenderStylesTheActiveField(): void
    {
        $state = new PromptState([
            $this->promptField(FieldKind::Argument, 'a'),
            $this->promptField(FieldKind::Argument, 'b'),
        ]);
        $state->activeIndex = 1;

        $frame = $this->frame();
        CommandInputPrompter::render($frame, $state);

        static::assertSame([], $this->styleAt($frame, 0, 0));
        static::assertNotSame([], $this->styleAt($frame, 0, 1));
    }

    #[Test]
    public function testToParametersIncludesARequiredEmptyValue(): void
    {
        $fields = [
            new PromptField(
                name       : 'table',
                description: '',
                kind       : FieldKind::Argument,
                required   : true,
                isArray    : false,
                default    : '',
            ),
        ];

        static::assertSame(['table' => ''], CommandInputPrompter::toParameters($fields));
    }

    #[Test]
    public function testToParametersIncludesFieldsAfterAFlag(): void
    {
        $fields = [
            $this->promptField(FieldKind::Flag, true),
            $this->promptField(FieldKind::Argument, 'users'),
        ];

        static::assertSame(
            ['--field' => true, 'field' => 'users'],
            CommandInputPrompter::toParameters($fields),
        );
    }

    #[Test]
    public function testToParametersIncludesFieldsAfterAnEmptyOptional(): void
    {
        $fields = [
            $this->promptField(FieldKind::Argument, ''),
            $this->promptField(FieldKind::Argument, 'users'),
        ];

        static::assertSame(['field' => 'users'], CommandInputPrompter::toParameters($fields));
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

    private function frame(): Frame
    {
        return new Frame(
            Rect::fromSize(
                width : 40,
                height: 10,
            ),
            new Buffer(
                width : 40,
                height: 10,
            ),
        );
    }

    private function promptField(FieldKind $kind, string|bool $default = ''): PromptField
    {
        return new PromptField(
            name       : 'field',
            description: '',
            kind       : $kind,
            required   : false,
            isArray    : false,
            default    : $default,
        );
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

    private function styleAt(Frame $frame, int $x, int $y): array
    {
        return $frame->buffer()->get($x, $y)->style ?? [];
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
