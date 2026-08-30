<?php

declare(strict_types=1);

namespace Webware\Console\Runner;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Runs a command in-process, capturing its output and exit status.
 *
 * @internal
 */
final readonly class CommandRunner
{
    /**
     * @return array{status: int, output: string}
     *
     * @throws ConsoleExceptionInterface
     */
    public function run(SymfonyCommand $command, InputInterface $input): array
    {
        $output = new BufferedOutput();
        $status = $command->run($input, $output);

        return [
            'status' => $status,
            'output' => $output->fetch(),
        ];
    }
}
