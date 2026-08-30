<?php

declare(strict_types=1);

namespace Webware\Console\Prompt;

use function is_bool;
use function is_string;
use function max;
use function mb_strlen;
use function min;

use const PHP_INT_MAX;

/**
 * @internal
 */
final class PromptField
{
    public string $value;
    public bool $flagValue;

    /**
     * Edit cursor clamped to the value's length, so it never points past the end.
     */
    public int $cursor {
        set {
            $this->cursor = max(0, min($value, mb_strlen($this->value)));
        }
    }

    // @mago-expect lint:excessive-parameter-list — a field descriptor needs all six inputs.
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly FieldKind $kind,
        public readonly bool $required,
        public readonly bool $isArray,
        string|bool $default = '',
    ) {
        $this->value = is_string($default) ? $default : '';
        // Seed the cursor at the end of the value; the property hook clamps it to the value's length.
        $this->cursor    = PHP_INT_MAX;
        $this->flagValue = is_bool($default) ? $default : false;
    }
}
