<?php

declare(strict_types=1);

namespace Webware\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function copy;
use function file_exists;
use function sprintf;
use function unlink;

/**
 * Enables, disables and reports the application's development mode.
 *
 * Development mode is the presence of `config/development.config.php`, a copy of
 * the committed `config/development.config.php.dist`. The aggregator loads it
 * last, which turns the debug flag on and configuration caching off.
 *
 * Toggling it invalidates the aggregated configuration cache: a cache written
 * while one state was in force is stale for the other, and development mode only
 * means anything if config changes take effect immediately.
 *
 * The cache is dropped on every enable and disable, including when the requested
 * state is already in force. A surviving cache is authoritative - the aggregator
 * returns it without consulting a single provider - so leaving one behind is what
 * lets a stale state outlive the toggle that was meant to end it.
 *
 * Paths are relative to the working directory, which the console binary sets to
 * the project root before it builds the container.
 *
 * @api
 */
#[AsCommand(
    name       : 'dev:mode',
    description: 'Enable, disable or report the application development mode',
)]
final class DevelopmentModeCommand extends Command
{
    public const string ACTIVE_FILE = 'config/development.config.php';

    public const string DIST_FILE = 'config/development.config.php.dist';

    /**
     * Single source of truth for the flags: both the option definition and the
     * usage text are built from it, so they cannot drift apart.
     *
     * @var array<string, string>
     */
    private const array ACTIONS = [
        'enable'  => 'Copy the dist file to enable development mode',
        'disable' => 'Remove the active file to disable development mode',
        'status'  => 'Report whether development mode is currently enabled',
    ];

    /**
     * @param string|null $configCachePath The aggregated configuration's `config_cache_path`, if it declares one.
     *
     * @throws LogicException
     */
    public function __construct(
        private readonly ?string $configCachePath = null,
    ) {
        parent::__construct();
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    protected function configure(): void
    {
        foreach (self::ACTIONS as $name => $description) {
            $this->addOption(
                name       : $name,
                mode       : InputOption::VALUE_NONE,
                description: $description,
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return match (true) {
            true === $input->getOption('enable') => $this->enable($output),
            true === $input->getOption('disable') => $this->disable($output),
            true === $input->getOption('status') => $this->status($output),
            default => $this->usage($output),
        };
    }

    /**
     * Drops the aggregated configuration cache, which describes whichever state
     * was in force when it was written.
     */
    private function clearConfigCache(OutputInterface $output): void
    {
        if (null === $this->configCachePath || ! file_exists($this->configCachePath)) {
            return;
        }

        unlink($this->configCachePath);

        $output->writeln(sprintf('Removed the config cache at "%s".', $this->configCachePath));
    }

    /**
     * Copies the committed dist file over the active file.
     *
     * Reports its own failure, so the caller only decides the exit code.
     */
    private function copyDistFile(OutputInterface $output): bool
    {
        if (! file_exists(self::DIST_FILE)) {
            $output->writeln(sprintf(
                '<error>Dist file "%s" not found.</error>',
                self::DIST_FILE,
            ));

            return false;
        }

        if (! copy(self::DIST_FILE, self::ACTIVE_FILE)) {
            $output->writeln(sprintf(
                '<error>Unable to copy "%s" to "%s".</error>',
                self::DIST_FILE,
                self::ACTIVE_FILE,
            ));

            return false;
        }

        return true;
    }

    private function developmentModeEnabled(): bool
    {
        return file_exists(self::ACTIVE_FILE);
    }

    private function disable(OutputInterface $output): int
    {
        $enabled = $this->developmentModeEnabled();

        if ($enabled && ! unlink(self::ACTIVE_FILE)) {
            $output->writeln(sprintf(
                '<error>Unable to remove "%s".</error>',
                self::ACTIVE_FILE,
            ));

            return Command::FAILURE;
        }

        $this->clearConfigCache($output);

        $output->writeln(
            $enabled
                ? '<info>Development mode DISABLED.</info>'
                : '<comment>Development mode is already disabled.</comment>',
        );

        return Command::SUCCESS;
    }

    private function enable(OutputInterface $output): int
    {
        $enabled = $this->developmentModeEnabled();

        if (! $enabled && ! $this->copyDistFile($output)) {
            return Command::FAILURE;
        }

        $this->clearConfigCache($output);

        $output->writeln(
            $enabled
                ? '<comment>Development mode is already enabled.</comment>'
                : '<info>Development mode ENABLED.</info>',
        );

        return Command::SUCCESS;
    }

    private function status(OutputInterface $output): int
    {
        $output->writeln(
            $this->developmentModeEnabled()
                ? '<info>Development mode is ENABLED.</info>'
                : '<comment>Development mode is DISABLED.</comment>',
        );

        return Command::SUCCESS;
    }

    private function usage(OutputInterface $output): int
    {
        $output->writeln('<comment>No action requested.</comment>');
        $output->writeln('Usage:');

        foreach (self::ACTIONS as $name => $description) {
            $output->writeln(sprintf('  dev:mode --%-8s %s', $name, $description));
        }

        return Command::SUCCESS;
    }
}
