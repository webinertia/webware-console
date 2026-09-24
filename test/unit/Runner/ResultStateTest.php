<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Runner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Console\Runner\ResultState;

#[CoversClass(ResultState::class)]
final class ResultStateTest extends TestCase
{
    #[Test]
    public function testHoldsTheResultParts(): void
    {
        $state = new ResultState(
            output     : 'output',
            status     : 'Status: command successful',
            statusStyle: [],
            prompt     : 'Press any key to return to the menu.',
        );

        static::assertSame('output', $state->output);
        static::assertSame('Status: command successful', $state->status);
        static::assertSame([], $state->statusStyle);
        static::assertSame('Press any key to return to the menu.', $state->prompt);
    }
}
