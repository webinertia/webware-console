<?php

declare(strict_types=1);

namespace Webware\Console\Runner;

use Psl\Ansi\ControlSequenceIntroducer;

/**
 * Read-only view state for rendering a command result.
 *
 * @internal
 */
final readonly class ResultState
{
    /**
     * @param list<ControlSequenceIntroducer> $statusStyle
     */
    public function __construct(
        public string $output,
        public string $status,
        public array $statusStyle,
        public string $prompt,
    ) {}
}
