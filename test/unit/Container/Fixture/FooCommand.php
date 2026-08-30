<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Container\Fixture;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FooCommand extends Command
{
    public function __construct()
    {
        parent::__construct(name: 'foo');

        $this->setDescription('Foo command.');
        $this->addArgument(
            name       : 'name',
            mode       : InputArgument::REQUIRED,
            description: 'Name.',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}
