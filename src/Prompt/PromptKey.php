<?php

declare(strict_types=1);

namespace Webware\Console\Prompt;

/**
 * Control keys the prompt's key loop dispatches on.
 *
 * @internal
 */
enum PromptKey: string
{
    case Escape    = 'escape';
    case Tab       = 'tab';
    case Down      = 'down';
    case Up        = 'up';
    case Enter     = 'enter';
    case Left      = 'left';
    case Right     = 'right';
    case Backspace = 'backspace';
    case Space     = 'space';
}
