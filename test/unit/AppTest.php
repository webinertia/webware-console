<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Async;
use Psl\IO;
use Psl\Terminal;
use Psl\Terminal\Event;
use Psl\Terminal\Exception\RuntimeException as TerminalRuntimeException;
use Psl\Terminal\Frame;
use ReflectionProperty;
use stdClass;
use Webware\Console\App;

#[CoversClass(App::class)]
#[CoversMethod(App::class, '__construct')]
#[CoversMethod(App::class, 'run')]
#[CoversMethod(App::class, 'stop')]
final class AppTest extends TestCase
{
    #[Test]
    public function testRunForwardsKeysAndStops(): void
    {
        [$reader, $writer] = IO\pipe();
        $output = new IO\MemoryHandle();

        $app = new App(
            static fn(object $state, string $title): Terminal\Application => Terminal\Application::custom(
                $state,
                $reader,
                $output,
                width: 40,
                height: 10,
                title: $title,
            ),
        );

        $keys    = [];
        $renders = 0;

        Async\Scheduler::defer(static function () use ($writer): void {
            $writer->writeAll('q');
        });

        $app->run(
            title : 'test',
            state : new stdClass(),
            onKey : static function (Event\Key $event, stdClass $state) use ($app, &$keys): void {
                $keys[] = $event->name;

                if ($event->is(key: 'q')) {
                    $app->stop();
                }
            },
            render: static function (Frame $frame, stdClass $state) use (&$renders, $reader): void {
                $renders++;

                if ($renders >= 2) {
                    $reader->close();
                }
            },
        );

        static::assertSame(['q'], $keys);
        static::assertSame(1, $renders);

        $reader->close();
        $writer->close();
    }

    #[Test]
    public function testRunResetsActiveWhenTerminalThrows(): void
    {
        [$reader, $writer] = IO\pipe();
        $reader->close();
        $output = new IO\MemoryHandle();

        $app = new App(
            static fn(object $state, string $title): Terminal\Application => Terminal\Application::custom(
                $state,
                $reader,
                $output,
                width: 40,
                height: 10,
                title: $title,
            ),
        );

        try {
            $app->run(
                title : 'test',
                state : new stdClass(),
                onKey : static function (Event\Key $event, stdClass $state): void {},
                render: static function (Frame $frame, stdClass $state): void {},
            );

            static::fail('Expected the terminal to reject a closed input stream.');
        } catch (TerminalRuntimeException $exception) {
            static::assertStringContainsString('stream resource', $exception->getMessage());
        }

        $active = new ReflectionProperty(App::class, 'active');
        static::assertNull($active->getValue($app));

        $writer->close();
    }
}
