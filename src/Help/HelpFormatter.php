<?php

declare(strict_types=1);

namespace Webware\Console\Help;

use Symfony\Component\Console\Command\Command as SymfonyCommand;

use function implode;
use function sprintf;

/**
 * Derives a plain-text help view from a command's native definition.
 *
 * @internal
 */
final readonly class HelpFormatter
{
    public function format(SymfonyCommand $command): string
    {
        $lines = [
            $command->getName(),
            '',
            $command->getDescription(),
        ];

        $arguments = $command->getNativeDefinition()->getArguments();
        $options   = $command->getNativeDefinition()->getOptions();

        if ([] !== $arguments) {
            $lines[] = '';
            $lines[] = 'Arguments:';

            foreach ($arguments as $argument) {
                $required = $argument->isRequired() ? ' (required)' : '';
                $lines[]  = sprintf('  %s  %s%s', $argument->getName(), $argument->getDescription(), $required);
            }
        }

        if ([] !== $options) {
            $lines[] = '';
            $lines[] = 'Options:';

            foreach ($options as $option) {
                $lines[] = sprintf('  --%s  %s', $option->getName(), $option->getDescription());
            }
        }

        return implode("\n", $lines);
    }
}
