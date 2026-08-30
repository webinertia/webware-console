<?php

declare(strict_types=1);

namespace Webware\Console\Container;

use Psl\Terminal;

/**
 * Produces the callable that creates a {@see Application}.
 *
 * The returned callable is a static-method reference, so it stays serializable
 * for config caching and can be substituted with any static function in tests.
 *
 * @internal
 */
final readonly class PslTerminalFactoryFactory
{
    public function __invoke(): callable
    {
        return Terminal\Application::create(...);
    }
}
