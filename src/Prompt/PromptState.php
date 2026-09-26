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
     * Whether a submission has already been refused, so the blank required
     * fields keep being reported until they are filled in. Once they are, the
     * active field's description takes the row back.
     */
    public bool $refused = false;

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

    /**
     * Indexes of the fields that are required and still blank, taken from the
     * current values so the report shrinks as the operator fills them in rather
     * than going stale.
     *
     * @return list<int>
     */
    public function missingIndexes(): array
    {
        $missing = [];

        foreach ($this->fields as $index => $field) {
            if (! $field->required || '' !== $field->value) {
                continue;
            }

            $missing[] = $index;
        }

        return $missing;
    }
}
