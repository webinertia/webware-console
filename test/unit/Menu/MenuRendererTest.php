<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Terminal\Buffer;
use Psl\Terminal\Frame;
use Psl\Terminal\Rect;
use Webware\Console\Menu\Menu;
use Webware\Console\Menu\MenuRenderer;

use function implode;
use function rtrim;

#[CoversClass(MenuRenderer::class)]
#[CoversMethod(MenuRenderer::class, 'render')]
#[CoversMethod(MenuRenderer::class, 'renderText')]
final class MenuRendererTest extends TestCase
{
    #[Test]
    public function testRendersCommandNames(): void
    {
        $frame = $this->frame();

        new MenuRenderer()->render(new Menu(['foo', 'bar']), $frame);

        $text = $this->text($frame);

        static::assertStringContainsString('foo', $text);
        static::assertStringContainsString('bar', $text);
    }

    #[Test]
    public function testRendersEmptyState(): void
    {
        $frame = $this->frame();

        new MenuRenderer()->render(new Menu([]), $frame);

        static::assertStringContainsString('No commands available.', $this->text($frame));
    }

    #[Test]
    public function testRendersTextAcrossLines(): void
    {
        $frame = $this->frame();

        new MenuRenderer()->renderText($frame, "first line\nsecond line");

        $text = $this->text($frame);

        static::assertStringContainsString('first line', $text);
        static::assertStringContainsString('second line', $text);
    }

    #[Test]
    public function testRendersTextStopsAtTheFrameHeight(): void
    {
        $frame = new Frame(
            Rect::fromSize(
                width : 20,
                height: 2,
            ),
            new Buffer(
                width : 20,
                height: 3,
            ),
        );

        new MenuRenderer()->renderText($frame, "line-0\nline-1\nline-2");

        $text = $this->text($frame);

        static::assertStringContainsString('line-0', $text);
        static::assertStringContainsString('line-1', $text);
        static::assertStringNotContainsString('line-2', $text);
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
