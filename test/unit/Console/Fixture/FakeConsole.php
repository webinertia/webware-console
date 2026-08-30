<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Console\Fixture;

use Closure;
use Psl\Terminal\Buffer;
use Psl\Terminal\Event;
use Psl\Terminal\Frame;
use Psl\Terminal\Rect;
use Webware\Console\ConsoleInterface;

use function array_shift;

/**
 * Test double that records a run loop and replays scripted key events.
 */
final class FakeConsole implements ConsoleInterface
{
    /** @var list<list<Event\Key>> */
    private array $scripts = [];

    public int $stopCount = 0;

    public int $runCount = 0;

    private ?Frame $lastFrame = null;

    /** @var list<Frame> */
    private array $frames = [];

    /**
     * @return list<Frame> Every frame rendered, in order.
     */
    public function frames(): array
    {
        return $this->frames;
    }

    public function lastFrame(): Frame
    {
        return $this->lastFrame ?? $this->frame();
    }

    public function run(string $title, object $state, Closure $onKey, Closure $render): void
    {
        $this->runCount++;

        foreach (array_shift($this->scripts) ?? [] as $event) {
            $onKey($event, $state);
        }

        $this->lastFrame = $this->frame();

        $this->frames[] = $this->lastFrame;

        $render($this->lastFrame, $state);
    }

    public function stop(): void
    {
        $this->stopCount++;
    }

    /**
     * @param list<list<Event\Key>> $scripts One key-event list per run() call.
     */
    public function withScripts(array $scripts): self
    {
        $this->scripts = $scripts;

        return $this;
    }

    private function frame(): Frame
    {
        return new Frame(
            Rect::fromSize(
                width : 40,
                height: 10,
            ),
            new Buffer(
                width : 40,
                height: 10,
            ),
        );
    }
}
