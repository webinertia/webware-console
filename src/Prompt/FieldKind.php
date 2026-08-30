<?php

declare(strict_types=1);

namespace Webware\Console\Prompt;

/**
 * @internal
 */
enum FieldKind
{
    case Argument;
    case Option;
    case Flag;
}
