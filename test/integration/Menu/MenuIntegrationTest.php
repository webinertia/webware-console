<?php

declare(strict_types=1);

namespace Webware\Console\Test\Integration\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Terminal\Buffer;
use Psl\Terminal\Frame;
use Psl\Terminal\Rect;
use Psr\Container\ContainerInterface;
use Webware\Console\ConsoleInterface;
use Webware\Console\Container\CommandLoaderFactory;
use Webware\Console\Menu\Menu;
use Webware\Console\Menu\MenuRenderer;
use Webware\Console\Test\Unit\Container\Fixture\BarCommand;
use Webware\Console\Test\Unit\Container\Fixture\FooCommand;

use function implode;
use function rtrim;

#[CoversClass(MenuRenderer::class)]
#[CoversMethod(MenuRenderer::class, 'render')]
final class MenuIntegrationTest extends TestCase
{
    #[Test]
    public function testMenuRendersNamesFromTheDiscoveredLoader(): void
    {
        $config = [
            ConsoleInterface::class => [
                'commands' => [
                    'foo' => FooCommand::class,
                    'bar' => BarCommand::class,
                ],
            ],
        ];

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturnMap([
                ['config', $config],
                [FooCommand::class, new FooCommand()],
                [BarCommand::class, new BarCommand()],
            ]);

        $loader = (new CommandLoaderFactory())($container);

        $frame = new Frame(
            Rect::fromSize(
                width : 40,
                height: 10,
            ),
            new Buffer(
                width : 40,
                height: 10,
            ),
        );

        new MenuRenderer()->render(new Menu($loader->getNames()), $frame);

        $text = $this->text($frame);

        static::assertStringContainsString('foo', $text);
        static::assertStringContainsString('bar', $text);
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
