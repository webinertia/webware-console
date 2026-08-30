<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Container\Fixture;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class BarCommand extends Command
{
    public function __construct()
    {
        parent::__construct(name: 'bar');

        $this->setDescription('Bar command.');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}
