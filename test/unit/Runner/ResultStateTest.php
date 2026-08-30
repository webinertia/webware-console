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
    public function testText(): void
    {
        $state = new ResultState('output');

        static::assertSame('output', $state->text);
    }
}
