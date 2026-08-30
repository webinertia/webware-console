<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Container\Fixture;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FailingCommand extends Command
{
    public function __construct()
    {
        parent::__construct(name: 'failing');

        $this->setDescription('Always fails.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Boom.');

        return Command::FAILURE;
    }
}
