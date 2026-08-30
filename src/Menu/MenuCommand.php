<?php

declare(strict_types=1);

namespace Webware\Console\Menu;

use Override;
use Psl\Terminal\Event;
use Psl\Terminal\Exception\ExceptionInterface as TerminalExceptionInterface;
use Psl\Terminal\Frame;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webware\Console\ConsoleInterface;
use Webware\Console\Help\HelpFormatter;
use Webware\Console\Prompt\CommandInputPrompter;
use Webware\Console\Runner\CommandRunner;
use Webware\Console\Runner\ResultState;

use function sprintf;

/**
 * Displays the interactive command menu.
 *
 * @internal
 */
#[AsCommand(name: 'menu', description: 'Display the interactive command menu')]
final class MenuCommand extends Command
{
    /**
     * @throws ConsoleExceptionInterface
     */
    // @mago-expect lint:excessive-parameter-list — the menu command aggregates its view and run collaborators.
    public function __construct(
        private readonly ConsoleInterface $console,
        private readonly ContainerCommandLoader $loader,
        private readonly MenuRenderer $renderer,
        private readonly HelpFormatter $formatter,
        private readonly CommandInputPrompter $prompter,
        private readonly CommandRunner $runner,
    ) {
        parent::__construct();
    }

    /**
     * @throws ConsoleExceptionInterface
     * @throws TerminalExceptionInterface
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $names */
        $names = $this->loader->getNames();

        while (true) {
            $menu = new Menu($names);

            $this->runMenu($menu);

            if (null === $menu->activated) {
                return Command::SUCCESS;
            }

            $command = $this->loader->get($menu->activated);

            $commandInput = $this->prompter->prompt($command);

            if (null === $commandInput) {
                continue;
            }

            $result = $this->runner->run($command, $commandInput);

            $this->showResult($result);
        }
    }

    /**
     * @throws ConsoleExceptionInterface
     */
    private function formatHelp(string $name): string
    {
        return $this->formatter->format($this->loader->get($name));
    }

    /**
     * @param array{status: int, output: string} $result
     */
    private function formatResult(array $result): string
    {
        $status = 0 === $result['status'] ? 'success' : 'failure';

        return sprintf(
            "%s\n\nStatus: %s (%d)\n\nPress any key to return to the menu.",
            $result['output'],
            $status,
            $result['status'],
        );
    }

    /**
     * @throws ConsoleExceptionInterface
     * @throws TerminalExceptionInterface
     */
    private function runMenu(Menu $menu): void
    {
        $this->console->run(
            title : 'Webware Console',
            state : $menu,
            onKey : /** @throws ConsoleExceptionInterface */ function (Event\Key $event, Menu $menu): void {
                if (null !== $menu->help) {
                    $menu->help = null;

                    return;
                }

                if ($event->is(key: 'ctrl+c') || $event->is(key: 'q')) {
                    $this->console->stop();

                    return;
                }

                if ($event->is(key: 'up')) {
                    $menu->moveUp();

                    return;
                }

                if ($event->is(key: 'down')) {
                    $menu->moveDown();

                    return;
                }

                if ($event->is(key: 'h')) {
                    $name = $menu->selected();

                    if (null !== $name) {
                        $menu->help = $this->formatHelp($name);
                    }

                    return;
                }

                if ($event->is(key: 'enter')) {
                    $menu->activated = $menu->selected();
                    $this->console->stop();
                }
            },
            render: function (Frame $frame, Menu $menu): void {
                $help = $menu->help;

                if (null !== $help) {
                    $this->renderer->renderText($frame, $help);

                    return;
                }

                $this->renderer->render($menu, $frame);
            },
        );
    }

    /**
     * @param array{status: int, output: string} $result
     *
     * @throws TerminalExceptionInterface
     */
    private function showResult(array $result): void
    {
        $state = new ResultState($this->formatResult($result));

        $this->console->run(
            title : 'Command output',
            state : $state,
            onKey : function (Event\Key $_event, ResultState $_state): void {
                $this->console->stop();
            },
            render: function (Frame $frame, ResultState $state): void {
                $this->renderer->renderText($frame, $state->text);
            },
        );
    }
}
