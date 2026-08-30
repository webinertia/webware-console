<?php

declare(strict_types=1);

namespace Webware\Console\Exception;

use RuntimeException;

/**
 * Thrown when two commands register the same name.
 *
 * @internal
 */
final class DuplicateCommandException extends RuntimeException {}
