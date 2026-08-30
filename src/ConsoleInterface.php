<?php

declare(strict_types=1);

namespace Webware\Console;

use Closure;
use Psl\Terminal\Event;
use Psl\Terminal\Frame;

/**
 * The console application contract.
 *
 * Components register their commands under the config key
 * `ConsoleInterface::class` as a `commands` map of name => command class.
 *
 * @api
 */
interface ConsoleInterface
{
    /**
     * Runs a key-driven terminal loop.
     *
     * @template T of object
     *
     * @param T $state Loop state passed to both callbacks.
     * @param Closure(Event\Key, T): void $onKey Called for each key event.
     * @param Closure(Frame, T): void $render Called to render a frame.
     */
    public function run(string $title, object $state, Closure $onKey, Closure $render): void;

    /**
     * Stops the currently running loop.
     */
    public function stop(): void;
}
