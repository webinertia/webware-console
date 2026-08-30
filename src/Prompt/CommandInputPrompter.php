<?php

declare(strict_types=1);

namespace Webware\Console\Prompt;

use Psl\Ansi;
use Psl\Ansi\Color;
use Psl\Str;
use Psl\Terminal\Event;
use Psl\Terminal\Exception\ExceptionInterface as TerminalExceptionInterface;
use Psl\Terminal\Frame;
use Psl\Terminal\Rect;
use Psl\Terminal\Widget;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Webware\Console\ConsoleInterface;

use function array_key_exists;
use function count;
use function explode;
use function mb_substr;
use function sprintf;

/**
 * Collects values for a command's arguments/options via the terminal, then
 * builds the ArrayInput to run it with.
 *
 * @internal
 */
// @mago-expect lint:cyclomatic-complexity,kan-defect — a TUI key dispatcher is inherently branchy.
final readonly class CommandInputPrompter
{
    public function __construct(
        private ConsoleInterface $console,
    ) {}

    public static function onKey(Event\Key $event, PromptState $state): void
    {
        $count = count($state->fields);
        $field = $state->fields[$state->activeIndex] ?? null;

        if (null === $field) {
            return;
        }

        if ($event->is(key: 'escape')) {
            $state->cancelled = true;

            return;
        }

        if ($event->is(key: 'tab') || $event->is(key: 'down')) {
            $state->activeIndex++;

            return;
        }

        if ($event->is(key: 'up')) {
            $state->activeIndex--;

            return;
        }

        if (FieldKind::Flag === $field->kind && ' ' === $event->char) {
            $field->flagValue = ! $field->flagValue;

            return;
        }

        if ($event->is(key: 'enter')) {
            if ($state->activeIndex === ($count - 1)) {
                $state->submitted = true;

                return;
            }

            $state->activeIndex++;

            return;
        }

        if (FieldKind::Flag === $field->kind) {
            return;
        }

        if ($event->is(key: 'left')) {
            $field->cursor--;

            return;
        }

        if ($event->is(key: 'right')) {
            $field->cursor++;

            return;
        }

        if ($event->is(key: 'backspace')) {
            if (0 < $field->cursor) {
                $field->value =
                    mb_substr($field->value, start: 0, length: $field->cursor - 1)
                    . mb_substr($field->value, start: $field->cursor);
                $field->cursor--;
            }

            return;
        }

        if (null !== $event->char) {
            $field->value =
                mb_substr($field->value, start: 0, length: $field->cursor)
                . $event->char
                . mb_substr($field->value, start: $field->cursor);
            $field->cursor++;
        }
    }

    public static function render(Frame $frame, PromptState $state): void
    {
        $area   = $frame->rect();
        $buffer = $frame->buffer();

        foreach ($state->fields as $index => $field) {
            $y      = $area->y + $index;
            $active = $index === $state->activeIndex;

            $marker = $active ? '> ' : '  ';
            $prefix = "{$marker}{$field->name}: ";
            $style  = $active ? [Ansi\foreground(Color\bright_white())] : [];

            $buffer->setString(
                x    : $area->x,
                y    : $y,
                text : $prefix,
                style: $style,
            );

            $inputArea = new Rect($area->x + Str\length($prefix), $y, $area->width - Str\length($prefix), 1);

            if (FieldKind::Flag === $field->kind) {
                $buffer->setString(
                    x    : $inputArea->x,
                    y    : $y,
                    text : $field->flagValue ? '[x]' : '[ ]',
                    style: $style,
                );

                continue;
            }

            Widget\TextInput::new()
                ->value($field->value)
                ->cursor($field->cursor)
                ->placeholder($field->description)
                ->render($inputArea, $buffer);
        }

        $footer = 'Tab/Down: next field   Up: previous field   Space: toggle checkbox   Enter: next/confirm   Esc: cancel';

        $buffer->setString(
            x   : $area->x,
            y   : $area->y + count($state->fields) + 1,
            text: $footer,
        );
    }

    /**
     * @param list<PromptField> $fields
     * @return array<string, string|bool|list<string>>
     */
    public static function toParameters(array $fields): array
    {
        $parameters = [];

        foreach ($fields as $field) {
            $key = FieldKind::Argument === $field->kind ? $field->name : "--{$field->name}";

            if (FieldKind::Flag === $field->kind) {
                if ($field->flagValue) {
                    $parameters[$key] = true;
                }

                continue;
            }

            if ('' === $field->value && ! $field->required) {
                continue;
            }

            $parameters[$key] = $field->isArray ? explode(',', $field->value) : $field->value;
        }

        return $parameters;
    }

    /**
     * @throws TerminalExceptionInterface
     */
    public function confirm(string $message, bool $default = true): bool
    {
        $state = new ConfirmState($message, $default);

        $this->console->run(
            title : $message,
            state : $state,
            onKey : function (Event\Key $event, ConfirmState $state): void {
                if ($event->is(key: 'escape')) {
                    $state->cancelled = true;
                    $this->console->stop();

                    return;
                }

                if (null !== $event->char && 'y' === Str\lowercase($event->char)) {
                    $state->confirmed = true;
                    $this->console->stop();

                    return;
                }

                if (null !== $event->char && 'n' === Str\lowercase($event->char)) {
                    $state->confirmed = false;
                    $this->console->stop();

                    return;
                }

                if ($event->is(key: 'enter')) {
                    $this->console->stop();
                }
            },
            render: static function (Frame $frame, ConfirmState $state): void {
                $area = $frame->rect();
                $hint = $state->default ? '[Y/n]' : '[y/N]';

                $frame->buffer()->setString(
                    x   : $area->x,
                    y   : $area->y,
                    text: sprintf('%s %s ', $state->message, $hint),
                );
            },
        );

        if ($state->cancelled) {
            return false;
        }

        return $state->confirmed ?? $state->default;
    }

    /**
     * @param array<string, string|bool> $presetValues
     *
     * @throws TerminalExceptionInterface
     */
    public function prompt(SymfonyCommand $command, array $presetValues = []): ?ArrayInput
    {
        $definition = $command->getNativeDefinition();

        $fields = [];

        foreach ($definition->getArguments() as $argument) {
            if (array_key_exists($argument->getName(), $presetValues)) {
                continue;
            }

            $fields[] = new PromptField(
                name       : $argument->getName(),
                description: $argument->getDescription(),
                kind       : FieldKind::Argument,
                required   : $argument->isRequired(),
                isArray    : $argument->isArray(),
                default    : (string) ($argument->getDefault() ?? ''),
            );
        }

        foreach ($definition->getOptions() as $option) {
            if (array_key_exists("--{$option->getName()}", $presetValues)) {
                continue;
            }

            $isFlag = ! $option->acceptValue();

            $fields[] = new PromptField(
                name       : $option->getName(),
                description: $option->getDescription(),
                kind       : $isFlag ? FieldKind::Flag : FieldKind::Option,
                required   : false,
                isArray    : $option->isArray(),
                default    : $isFlag ? true === $option->getDefault() : (string) ($option->getDefault() ?? ''),
            );
        }

        if ([] === $fields) {
            return new ArrayInput($presetValues, $definition);
        }

        $state = new PromptState($fields);

        $this->console->run(
            title : sprintf('%s — input', (string) $command->getName()),
            state : $state,
            onKey : function (Event\Key $event, PromptState $state): void {
                self::onKey($event, $state);

                if ($state->submitted || $state->cancelled) {
                    $this->console->stop();
                }
            },
            render: static function (Frame $frame, PromptState $state): void {
                self::render($frame, $state);
            },
        );

        if ($state->cancelled) {
            return null;
        }

        return new ArrayInput([...$presetValues, ...self::toParameters($state->fields)], $definition);
    }
}
