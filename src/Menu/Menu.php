<?php

declare(strict_types=1);

namespace Webware\Console\Menu;

use function count;

/**
 * Keyboard navigation state over the discovered command names.
 *
 * @internal
 */
final class Menu
{
    /**
     * Highlighted index, wrapping around the name list.
     */
    private int $selection = 0 {
        set {
            $total           = count($this->names);
            $this->selection = 0 === $total ? 0 : (($value % $total) + $total) % $total;
        }
    }

    public ?string $activated = null;

    public ?string $help = null;

    /**
     * @param list<string> $names
     */
    public function __construct(
        private readonly array $names,
    ) {}

    public function isEmpty(): bool
    {
        return [] === $this->names;
    }

    public function moveDown(): void
    {
        $this->selection++;
    }

    public function moveUp(): void
    {
        $this->selection--;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->names;
    }

    public function selected(): ?string
    {
        return $this->names[$this->selection] ?? null;
    }

    public function selectionIndex(): int
    {
        return $this->selection;
    }
}
