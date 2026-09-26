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

use function getenv;
use function sprintf;
use function var_export;

use const PHP_OS;

/**
 * Enables, disables and reports the application's development mode.
 *
 * The filesystem policy lives in {@see DevelopmentMode}; this class is only the CLI surface over
 * it. Side effects therefore match `laminas/laminas-development-mode` — the dist files are linked
 * rather than copied wherever symlinks are trusted, the optional
 * `config/autoload/development.local.php` is placed by enable and removed by disable,
 * COMPOSER_DEV_MODE is honoured, and both actions drop the aggregated config cache. Wording, flag
 * interface and exit codes stay the console's own. `docs/v1/dev-mode.md` inventories every
 * behaviour of the reference beside its disposition.
 *
 * Paths are relative to the working directory, which the console binary sets to the project root
 * before it builds the container.
 *
 * @api
 */
#[AsCommand(
    name       : 'dev:mode',
    description: 'Enable, disable or report the application development mode',
)]
final class DevelopmentModeCommand extends Command
{
    /**
     * Single source of truth for the flags: both the option definition and the
     * usage text are built from it, so they cannot drift apart.
     *
     * @var array<string, string>
     */
    private const array ACTIONS = [
        'enable'        => 'Create the active file to enable development mode',
        'disable'       => 'Remove the active file to disable development mode',
        'status'        => 'Report whether development mode is currently enabled',
        'auto-composer' => 'Follow COMPOSER_DEV_MODE: enable on 1, disable on 0, do nothing otherwise',
    ];

    private readonly DevelopmentMode $mode;

    /**
     * @param string|null $configCachePath The aggregated configuration's `config_cache_path`, if it declares one.
     * @param string $platform The platform that decides link-versus-copy, injectable so both paths stay reachable.
     *
     * @throws LogicException
     */
    public function __construct(?string $configCachePath = null, string $platform = PHP_OS)
    {
        $this->mode = new DevelopmentMode(
            configCachePath: $configCachePath,
            platform       : $platform,
        );

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
            true === $input->getOption('auto-composer') => $this->autoComposer($output),
            default => $this->usage($output),
        };
    }

    /**
     * Follows `COMPOSER_DEV_MODE`, which Composer sets for the duration of an
     * install or update, so development mode tracks the type of install.
     */
    private function autoComposer(OutputInterface $output): int
    {
        $mode = getenv('COMPOSER_DEV_MODE');

        if ('1' === $mode || '0' === $mode) {
            return '1' === $mode ? $this->enable($output) : $this->disable($output);
        }

        if ('' === $mode || false === $mode) {
            $output->writeln('<comment>COMPOSER_DEV_MODE not set. Nothing to do.</comment>');

            return Command::SUCCESS;
        }

        $this->error($output, sprintf(
            'COMPOSER_DEV_MODE set to unexpected value (%s). Nothing to do.',
            var_export(
                value : $mode,
                return: true,
            ),
        ));

        return Command::FAILURE;
    }

    /**
     * Reports the cache removal only when a cache was really removed, so the
     * output describes what happened rather than what was attempted.
     */
    private function clearConfigCache(OutputInterface $output): void
    {
        $removed = $this->mode->clearConfigCache();

        if (null === $removed) {
            return;
        }

        $output->writeln(sprintf('Removed the config cache at "%s".', $removed));
    }

    private function disable(OutputInterface $output): int
    {
        $enabled = $this->mode->enabled();

        if (null !== ($failure = $this->mode->disable())) {
            $this->error($output, $failure);

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
        $enabled = $this->mode->enabled();

        if (null !== ($failure = $this->mode->enable())) {
            $this->error($output, $failure);

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

    private function error(OutputInterface $output, string $message): void
    {
        $output->writeln(sprintf('<error>%s</error>', $message));
    }

    private function status(OutputInterface $output): int
    {
        $output->writeln(
            $this->mode->enabled()
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
            $output->writeln(sprintf('  dev:mode --%-13s %s', $name, $description));
        }

        $output->writeln('');
        $output->writeln(sprintf(
            'Enabling creates "%s" from "%s",',
            DevelopmentMode::ACTIVE_FILE,
            DevelopmentMode::DIST_FILE,
        ));
        $output->writeln(sprintf(
            'and "%s" from "%s" when that file exists.',
            DevelopmentMode::LOCAL_FILE,
            DevelopmentMode::LOCAL_DIST,
        ));
        $output->writeln('Disabling removes both, and either action drops the aggregated config cache.');

        return Command::SUCCESS;
    }
}
