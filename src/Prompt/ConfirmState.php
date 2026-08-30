<?php

declare(strict_types=1);

namespace Webware\Console\Prompt;

/**
 * @internal
 */
final class ConfirmState
{
    public ?bool $confirmed = null;
    public bool  $cancelled = false;

    public function __construct(
        public readonly string $message,
        public readonly bool $default,
    ) {}
}
