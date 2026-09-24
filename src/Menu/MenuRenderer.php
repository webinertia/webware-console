<?php

declare(strict_types=1);

namespace Webware\Console\Menu;

use Psl\Ansi\ControlSequenceIntroducer;
use Psl\Terminal\Frame;
use Psl\Terminal\Widget\Menu as MenuWidget;
use Psl\Terminal\Widget\MenuItem;

use function array_slice;
use function explode;
use function max;
use function Psl\Ansi\background;
use function Psl\Ansi\Color\blue;
use function Psl\Ansi\Color\bright_white;
use function Psl\Ansi\foreground;

/**
 * Renders the command menu using the PSL terminal Menu widget.
 *
 * @internal
 */
final readonly class MenuRenderer
{
    public function render(Menu $menu, Frame $frame): void
    {
        $area   = $frame->rect();
        $buffer = $frame->buffer();

        $items = [];

        foreach ($menu->names() as $name) {
            $items[] = MenuItem::raw($name);
        }

        if ([] === $items) {
            $buffer->setString(
                x   : $area->x,
                y   : $area->y,
                text: 'No commands available.',
            );
        }

        MenuWidget::new($items)
            ->highlight($menu->selectionIndex())
            ->highlightStyle(foreground(bright_white()), background(blue()))
            ->render($area, $buffer);
    }

    /**
     * @param list<ControlSequenceIntroducer> $style
     */
    public function renderText(Frame $frame, string $text, array $style = [], int $offset = 0): void
    {
        $area   = $frame->rect();
        $buffer = $frame->buffer();

        $lines = array_slice(explode("\n", $text), offset: 0, length: max(0, $area->height - $offset));

        foreach ($lines as $index => $line) {
            $buffer->setString(
                x    : $area->x,
                y    : $area->y + $offset + $index,
                text : $line,
                style: $style,
            );
        }
    }
}
