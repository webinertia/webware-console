<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Terminal\Event;
use Webware\Console\Prompt\FieldKind;
use Webware\Console\Prompt\PromptField;
use Webware\Console\Prompt\PromptKeyAction;
use Webware\Console\Prompt\PromptState;

#[CoversClass(PromptKeyAction::class)]
#[CoversMethod(PromptKeyAction::class, 'backspace')]
#[CoversMethod(PromptKeyAction::class, 'character')]
#[CoversMethod(PromptKeyAction::class, 'enter')]
#[CoversMethod(PromptKeyAction::class, 'left')]
#[CoversMethod(PromptKeyAction::class, 'right')]
#[CoversMethod(PromptKeyAction::class, 'toggleFlag')]
final class PromptKeyActionTest extends TestCase
{
    #[Test]
    public function testBackspaceAtCursorZeroDoesNothing(): void
    {
        $field         = $this->field(FieldKind::Argument, 'ab');
        $field->cursor = 0;

        PromptKeyAction::backspace($field);

        static::assertSame('ab', $field->value);
        static::assertSame(0, $field->cursor);
    }

    #[Test]
    public function testBackspaceDeletesTheCharacterBeforeTheCursor(): void
    {
        $field         = $this->field(FieldKind::Argument, 'abc');
        $field->cursor = 2;

        PromptKeyAction::backspace($field);

        static::assertSame('ac', $field->value);
        static::assertSame(1, $field->cursor);
    }

    #[Test]
    public function testBackspaceIgnoresFlagFields(): void
    {
        $field = $this->field(FieldKind::Flag);

        PromptKeyAction::backspace($field);

        static::assertSame('', $field->value);
    }

    #[Test]
    public function testCharacterIgnoresANullCharacter(): void
    {
        $field         = $this->field(FieldKind::Argument, 'ab');
        $field->cursor = 1;

        PromptKeyAction::character(Event\Key::named('delete'), $field);

        static::assertSame('ab', $field->value);
        static::assertSame(1, $field->cursor);
    }

    #[Test]
    public function testCharacterIgnoresNonSpaceOnAFlag(): void
    {
        $field = $this->field(FieldKind::Flag, false);

        PromptKeyAction::character(Event\Key::char('x'), $field);

        static::assertFalse($field->flagValue);
        static::assertSame('', $field->value);
    }

    #[Test]
    public function testCharacterInsertsAtTheCursor(): void
    {
        $field         = $this->field(FieldKind::Argument, 'ac');
        $field->cursor = 1;

        PromptKeyAction::character(Event\Key::char('b'), $field);

        static::assertSame('abc', $field->value);
        static::assertSame(2, $field->cursor);
    }

    #[Test]
    public function testCharacterSpaceTogglesAFlag(): void
    {
        $field = $this->field(FieldKind::Flag, false);

        PromptKeyAction::character(Event\Key::char(' '), $field);

        static::assertTrue($field->flagValue);
        static::assertSame('', $field->value);
    }

    #[Test]
    public function testEnterIgnoresBlankOptionalFields(): void
    {
        $state = new PromptState([$this->field(FieldKind::Argument, '')]);

        PromptKeyAction::enter($state);

        static::assertTrue($state->submitted);
        static::assertFalse($state->refused);
    }

    #[Test]
    public function testEnterRefusesAndFocusesTheFirstBlankRequiredField(): void
    {
        $state = new PromptState([
            $this->field(FieldKind::Argument, 'filled', required: true),
            $this->field(FieldKind::Argument, '', required: true),
            $this->field(FieldKind::Option, '', required: true),
        ]);

        PromptKeyAction::enter($state);

        static::assertFalse($state->submitted);
        static::assertTrue($state->refused);
        static::assertSame(1, $state->activeIndex);
    }

    #[Test]
    public function testEnterSubmitsWhenEveryRequiredFieldIsFilled(): void
    {
        $state = new PromptState([
            $this->field(FieldKind::Argument, 'first', required: true),
            $this->field(FieldKind::Argument, 'second', required: true),
        ]);
        $state->activeIndex = 1;

        PromptKeyAction::enter($state);

        static::assertTrue($state->submitted);
        static::assertFalse($state->refused);
        static::assertSame(1, $state->activeIndex);
    }

    #[Test]
    public function testLeftIgnoresFlagFields(): void
    {
        $field = $this->field(FieldKind::Flag);

        PromptKeyAction::left($field);

        static::assertSame(0, $field->cursor);
    }

    #[Test]
    public function testLeftMovesTheCursorBack(): void
    {
        $field = $this->field(FieldKind::Argument, 'abc');

        PromptKeyAction::left($field);

        static::assertSame(2, $field->cursor);
    }

    #[Test]
    public function testRightIgnoresFlagFields(): void
    {
        $field = $this->field(FieldKind::Flag);

        PromptKeyAction::right($field);

        static::assertSame(0, $field->cursor);
    }

    #[Test]
    public function testRightMovesTheCursorForward(): void
    {
        $field         = $this->field(FieldKind::Argument, 'abc');
        $field->cursor = 0;

        PromptKeyAction::right($field);

        static::assertSame(1, $field->cursor);
    }

    #[Test]
    public function testToggleFlagFlipsAFlagField(): void
    {
        $field = $this->field(FieldKind::Flag, false);

        PromptKeyAction::toggleFlag($field);

        static::assertTrue($field->flagValue);
    }

    #[Test]
    public function testToggleFlagIgnoresNonFlagFields(): void
    {
        $field = $this->field(FieldKind::Argument, 'x');

        PromptKeyAction::toggleFlag($field);

        static::assertFalse($field->flagValue);
    }

    private function field(FieldKind $kind, string|bool $default = '', bool $required = false): PromptField
    {
        return new PromptField(
            name       : 'field',
            description: '',
            kind       : $kind,
            required   : $required,
            isArray    : false,
            default    : $default,
        );
    }
}
