<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Console\Prompt\FieldKind;
use Webware\Console\Prompt\PromptField;
use Webware\Console\Prompt\PromptState;

#[CoversClass(PromptState::class)]
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
}
