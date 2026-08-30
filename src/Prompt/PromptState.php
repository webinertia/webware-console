<?php

declare(strict_types=1);

namespace Webware\Console\Prompt;

use function count;

/**
 * @internal
 */
final class PromptState
{
    public bool $submitted = false;
    public bool $cancelled = false;

    /**
     * Index of the active field, wrapping around the field list.
     */
    public int $activeIndex = 0 {
        set {
            $count             = count($this->fields);
            $this->activeIndex = 0 === $count ? 0 : (($value % $count) + $count) % $count;
        }
    }

    /**
     * @param list<PromptField> $fields
     */
    public function __construct(
        public readonly array $fields,
    ) {}
}
