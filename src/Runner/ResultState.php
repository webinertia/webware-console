<?php

declare(strict_types=1);

namespace Webware\Console\Runner;

/**
 * Read-only view state for rendering a command result.
 *
 * @internal
 */
final readonly class ResultState
{
    public function __construct(
        public string $text,
    ) {}
}
