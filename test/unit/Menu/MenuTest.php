<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Console\Menu\Menu;

#[CoversClass(Menu::class)]
#[CoversMethod(Menu::class, 'moveUp')]
#[CoversMethod(Menu::class, 'moveDown')]
#[CoversMethod(Menu::class, 'selected')]
#[CoversMethod(Menu::class, 'selectionIndex')]
#[CoversMethod(Menu::class, 'isEmpty')]
#[CoversMethod(Menu::class, 'names')]
final class MenuTest extends TestCase
{
    #[Test]
    public function testEmptyMenuHasNoSelection(): void
    {
        $menu = new Menu([]);

        static::assertTrue($menu->isEmpty());
        static::assertNull($menu->selected());
        static::assertSame([], $menu->names());

        $menu->moveDown();
        $menu->moveUp();

        static::assertSame(0, $menu->selectionIndex());
    }

    #[Test]
    public function testMoveDownAdvancesAndWraps(): void
    {
        $menu = new Menu(['first', 'second', 'third']);

        $menu->moveDown();
        static::assertSame('second', $menu->selected());

        $menu->moveDown();
        static::assertSame('third', $menu->selected());

        $menu->moveDown();
        static::assertSame('first', $menu->selected());
    }

    #[Test]
    public function testMoveUpWrapsToTheLastName(): void
    {
        $menu = new Menu(['first', 'second', 'third']);

        $menu->moveUp();

        static::assertSame('third', $menu->selected());
    }

    #[Test]
    public function testSelectionStartsAtTheFirstName(): void
    {
        $menu = new Menu(['first', 'second', 'third']);

        static::assertSame('first', $menu->selected());
        static::assertSame(0, $menu->selectionIndex());
    }
}
