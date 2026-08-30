<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Console\Prompt\FieldKind;
use Webware\Console\Prompt\PromptField;

#[CoversClass(PromptField::class)]
#[CoversMethod(PromptField::class, '__construct')]
final class PromptFieldTest extends TestCase
{
    #[Test]
    public function testBooleanDefaultSeedsFlagValue(): void
    {
        $field = new PromptField(
            name       : 'dry-run',
            description: '',
            kind       : FieldKind::Flag,
            required   : false,
            isArray    : false,
            default    : true,
        );

        static::assertSame('', $field->value);
        static::assertSame(0, $field->cursor);
        static::assertTrue($field->flagValue);
    }

    #[Test]
    public function testCursorClampsToCharacterLengthNotBytes(): void
    {
        $field = new PromptField(
            name       : 'table',
            description: '',
            kind       : FieldKind::Argument,
            required   : false,
            isArray    : false,
            default    : 'héllo',
        );

        $field->cursor = 100;
        static::assertSame(5, $field->cursor);
    }

    #[Test]
    public function testCursorClampsToTheValueLength(): void
    {
        $field = new PromptField(
            name       : 'table',
            description: '',
            kind       : FieldKind::Argument,
            required   : false,
            isArray    : false,
            default    : 'ab',
        );

        $field->cursor = 10;
        static::assertSame(2, $field->cursor);

        $field->cursor = -3;
        static::assertSame(0, $field->cursor);
    }

    #[Test]
    public function testMultibyteDefaultSeedsCursorByCharacters(): void
    {
        $field = new PromptField(
            name       : 'label',
            description: '',
            kind       : FieldKind::Argument,
            required   : false,
            isArray    : false,
            default    : 'héllo',
        );

        static::assertSame('héllo', $field->value);
        static::assertSame(5, $field->cursor);
    }

    #[Test]
    public function testStringDefaultSeedsValueAndCursor(): void
    {
        $field = new PromptField(
            name       : 'table',
            description: '',
            kind       : FieldKind::Argument,
            required   : false,
            isArray    : false,
            default    : 'abc',
        );

        static::assertSame('abc', $field->value);
        static::assertSame(3, $field->cursor);
        static::assertFalse($field->flagValue);
    }
}
