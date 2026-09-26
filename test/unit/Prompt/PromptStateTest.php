<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Console\Prompt\FieldKind;
use Webware\Console\Prompt\PromptField;
use Webware\Console\Prompt\PromptState;

#[CoversClass(PromptState::class)]
#[CoversMethod(PromptState::class, 'missingIndexes')]
final class PromptStateTest extends TestCase
{
    #[Test]
    public function testActiveIndexStaysZeroWhenThereAreNoFields(): void
    {
        $state = new PromptState([]);

        $state->activeIndex = 4;
        static::assertSame(0, $state->activeIndex);
    }

    #[Test]
    public function testActiveIndexWrapsBackward(): void
    {
        $state = new PromptState($this->fields(3));

        $state->activeIndex = -1;
        static::assertSame(2, $state->activeIndex);
    }

    #[Test]
    public function testActiveIndexWrapsForward(): void
    {
        $state = new PromptState($this->fields(3));

        $state->activeIndex = 3;
        static::assertSame(0, $state->activeIndex);

        $state->activeIndex = 5;
        static::assertSame(2, $state->activeIndex);
    }

    #[Test]
    public function testMissingIndexesIgnoresBlankOptionalFields(): void
    {
        $state = new PromptState([
            $this->requiredField('required-filled', 'value'),
            $this->requiredField('required-blank', ''),
            $this->optionalField('optional-blank'),
        ]);

        static::assertSame([1], $state->missingIndexes());
    }

    #[Test]
    public function testMissingIndexesIsEmptyWhenNoFieldIsRequired(): void
    {
        $state = new PromptState([$this->optionalField('optional')]);

        static::assertSame([], $state->missingIndexes());
    }

    #[Test]
    public function testMissingIndexesIsEmptyWhenRequiredFieldsAreFilled(): void
    {
        $state = new PromptState([
            $this->requiredField('first', 'value'),
            $this->requiredField('second', 'value'),
        ]);

        static::assertSame([], $state->missingIndexes());
    }

    #[Test]
    public function testMissingIndexesReportsEveryBlankRequiredField(): void
    {
        $state = new PromptState([
            $this->requiredField('blank-first', ''),
            $this->requiredField('blank-second', ''),
        ]);

        static::assertSame([0, 1], $state->missingIndexes());
    }

    #[Test]
    public function testRefusedStartsFalse(): void
    {
        static::assertFalse(new PromptState([])->refused);
    }

    /**
     * @return list<PromptField>
     */
    private function fields(int $count): array
    {
        $fields = [];

        for ($index = 0; $index < $count; $index++) {
            $fields[] = new PromptField(
                name       : "field-{$index}",
                description: '',
                kind       : FieldKind::Argument,
                required   : false,
                isArray    : false,
            );
        }

        return $fields;
    }

    private function optionalField(string $name): PromptField
    {
        return new PromptField(
            name       : $name,
            description: '',
            kind       : FieldKind::Option,
            required   : false,
            isArray    : false,
        );
    }

    private function requiredField(string $name, string $value): PromptField
    {
        return new PromptField(
            name       : $name,
            description: '',
            kind       : FieldKind::Argument,
            required   : true,
            isArray    : false,
            default    : $value,
        );
    }
}
