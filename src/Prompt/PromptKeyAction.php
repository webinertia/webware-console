<?php

declare(strict_types=1);

namespace Webware\Console\Prompt;

use Psl\Terminal\Event;

use function count;
use function mb_substr;

/**
 * Applies a prompt key to the loop state.
 *
 * @internal
 */
final readonly class PromptKeyAction
{
    public static function backspace(PromptField $field): void
    {
        if (0 < $field->cursor) {
            $field->value =
                mb_substr($field->value, start: 0, length: $field->cursor - 1)
                . mb_substr($field->value, start: $field->cursor);
            $field->cursor--;
        }
    }

    public static function character(Event\Key $event, PromptField $field): void
    {
        if (null === $event->char) {
            return;
        }

        if (FieldKind::Flag === $field->kind) {
            if (' ' === $event->char) {
                $field->flagValue = ! $field->flagValue;
            }

            return;
        }

        $field->value =
            mb_substr($field->value, start: 0, length: $field->cursor)
            . $event->char
            . mb_substr($field->value, start: $field->cursor);
        $field->cursor++;
    }

    public static function enter(PromptState $state): void
    {
        $count = count($state->fields);

        if ($state->activeIndex === ($count - 1)) {
            $state->submitted = true;

            return;
        }

        $state->activeIndex++;
    }

    public static function left(PromptField $field): void
    {
        if (FieldKind::Flag !== $field->kind) {
            $field->cursor--;
        }
    }

    public static function right(PromptField $field): void
    {
        if (FieldKind::Flag !== $field->kind) {
            $field->cursor++;
        }
    }

    public static function toggleFlag(PromptField $field): void
    {
        if (FieldKind::Flag === $field->kind) {
            $field->flagValue = ! $field->flagValue;
        }
    }
}
