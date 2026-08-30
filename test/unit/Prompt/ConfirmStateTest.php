<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Console\Prompt\ConfirmState;

#[CoversClass(ConfirmState::class)]
final class ConfirmStateTest extends TestCase
{
    #[Test]
    public function testDefaults(): void
    {
        $state = new ConfirmState('Proceed?', default: true);

        static::assertSame('Proceed?', $state->message);
        static::assertTrue($state->default);
        static::assertNull($state->confirmed);
        static::assertFalse($state->cancelled);
    }
}
