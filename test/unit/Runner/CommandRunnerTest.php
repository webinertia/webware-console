<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Runner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webware\Console\Runner\CommandRunner;

#[CoversClass(CommandRunner::class)]
#[CoversMethod(CommandRunner::class, 'run')]
final class CommandRunnerTest extends TestCase
{
    #[Test]
    public function testRunsCommandAndCapturesOutputAndStatus(): void
    {
        $command = new SymfonyCommand(name: 'echo');
        $command->setCode(static function (InputInterface $input, OutputInterface $output): int {
            $output->writeln('hello');

            return 3;
        });

        $result = new CommandRunner()->run($command, new ArrayInput([]));

        static::assertSame(3, $result['status']);
        static::assertStringContainsString('hello', $result['output']);
    }
}
