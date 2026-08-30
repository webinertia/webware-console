<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Help;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Webware\Console\Help\HelpFormatter;

#[CoversClass(HelpFormatter::class)]
#[CoversMethod(HelpFormatter::class, 'format')]
final class HelpFormatterTest extends TestCase
{
    #[Test]
    public function testFormatsPurposeArgumentsAndOptions(): void
    {
        $command = new SymfonyCommand(name: 'migrate');
        $command->setDescription('Run migrations.');
        $command->addArgument(
            name       : 'version',
            mode       : InputArgument::REQUIRED,
            description: 'Target version.',
        );
        $command->addArgument(
            name       : 'step',
            mode       : InputArgument::OPTIONAL,
            description: 'Number of steps.',
        );
        $command->addOption(
            name       : 'dry-run',
            shortcut   : 'd',
            mode       : InputOption::VALUE_NONE,
            description: 'Do not execute.',
        );

        $output = new HelpFormatter()->format($command);

        static::assertStringContainsString('migrate', $output);
        static::assertStringContainsString('Run migrations.', $output);
        static::assertStringContainsString('version  Target version. (required)', $output);
        static::assertStringContainsString('step  Number of steps.', $output);
        static::assertStringContainsString('--dry-run  Do not execute.', $output);
    }

    #[Test]
    public function testOmitsSectionsWithNoArgumentsOrOptions(): void
    {
        $command = new SymfonyCommand(name: 'list');
        $command->setDescription('List things.');

        $output = new HelpFormatter()->format($command);

        static::assertStringContainsString('List things.', $output);
        static::assertStringNotContainsString('Arguments:', $output);
        static::assertStringNotContainsString('Options:', $output);
    }
}
