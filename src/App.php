<?php

declare(strict_types=1);

namespace Webware\Console;

use Closure;
use Override;
use Psl\Terminal\Application as TerminalApplication;
use Psl\Terminal\Event;
use Psl\Terminal\Exception\ExceptionInterface as TerminalExceptionInterface;
use Psl\Terminal\Frame;

/**
 * Terminal-backed console application.
 *
 * @api
 */
final class App implements ConsoleInterface
{
    /**
     * @var callable(object, string): TerminalApplication
     */
    private mixed $factory;

    private ?TerminalApplication $active = null;

    /**
     * @param callable(object, string): TerminalApplication $factory
     */
    public function __construct(callable $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @template T of object
     *
     * @param T $state
     * @param Closure(Event\Key, T): void $onKey
     * @param Closure(Frame, T): void $render
     *
     * @throws TerminalExceptionInterface
     */
    #[Override]
    public function run(string $title, object $state, Closure $onKey, Closure $render): void
    {
        $terminal     = ($this->factory)($state, $title);
        $this->active = $terminal;

        try {
            $terminal->on(
                Event\Key::class,
                static function (Event\Key $event, object $state) use ($onKey): void {
                    /** @var T $state */
                    $onKey($event, $state);
                },
            );

            $terminal->run(
                static function (Frame $frame, object $state) use ($render): void {
                    /** @var T $state */
                    $render($frame, $state);
                },
            );
        } finally {
            $this->active = null;
        }
    }

    #[Override]
    public function stop(): void
    {
        $this->active?->stop();
    }
}
