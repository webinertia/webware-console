<?php

declare(strict_types=1);

namespace Webware\Console;

use function basename;
use function copy;
use function file_exists;
use function in_array;
use function sprintf;
use function symlink;
use function unlink;

use const PHP_OS;

/**
 * The development-mode filesystem policy: which files constitute development
 * mode, how the dist files are placed and removed, and where the aggregated
 * configuration cache lives.
 *
 * The side effects mirror `laminas/laminas-development-mode`, including its
 * documented contract that both enabling and disabling drop the config cache.
 * `docs/v1/dev-mode.md` inventories every behaviour of the reference together
 * with its disposition.
 *
 * Every path is relative to the working directory, which the console binary sets
 * to the project root before it builds the container.
 *
 * @internal
 */
final readonly class DevelopmentMode
{
    public const string ACTIVE_FILE = 'config/development.config.php';

    public const string DIST_FILE = 'config/development.config.php.dist';

    public const string LOCAL_FILE = 'config/autoload/development.local.php';

    public const string LOCAL_DIST = 'config/autoload/development.local.php.dist';

    /**
     * Platforms whose symlink support the reference trusts. Elsewhere the dist is
     * copied, because a dangling link is worse than a duplicate.
     *
     * @var list<string>
     */
    private const array SYMLINK_PLATFORMS = ['Linux', 'Unix', 'Darwin'];

    /**
     * @param string|null $configCachePath The aggregated configuration's `config_cache_path`, if it declares one.
     * @param string $platform The platform that decides link-versus-copy, injectable so both paths stay reachable.
     */
    public function __construct(
        private ?string $configCachePath = null,
        private string $platform = PHP_OS,
    ) {}

    /**
     * Drops the aggregated configuration cache, which describes whichever state
     * was in force when it was written.
     *
     * @return string|null The path removed, or null when there was nothing to remove.
     */
    public function clearConfigCache(): ?string
    {
        $path = $this->configCachePath;

        if (null === $path || ! file_exists($path)) {
            return null;
        }

        if (! unlink($path)) {
            // Never report a removal that did not happen.
            return null;
        }

        return $path;
    }

    /**
     * Removes the active file and the local overrides file.
     *
     * @return string|null The failure to report, or null on success.
     */
    public function disable(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        if (! unlink(self::ACTIVE_FILE)) {
            return sprintf('Unable to remove "%s".', self::ACTIVE_FILE);
        }

        if (file_exists(self::LOCAL_FILE) && ! unlink(self::LOCAL_FILE)) {
            return sprintf('Unable to remove "%s".', self::LOCAL_FILE);
        }

        return null;
    }

    /**
     * Places the dist files, putting development mode in force. The local
     * overrides file is skipped when the application ships no dist for it.
     *
     * @return string|null The failure to report, or null on success.
     */
    public function enable(): ?string
    {
        if ($this->enabled()) {
            return null;
        }

        if (! file_exists(self::DIST_FILE)) {
            return sprintf('MISSING "%s".', self::DIST_FILE);
        }

        if (! $this->place(self::DIST_FILE, self::ACTIVE_FILE)) {
            return sprintf('Unable to create "%s".', self::ACTIVE_FILE);
        }

        if (file_exists(self::LOCAL_DIST) && ! $this->place(self::LOCAL_DIST, self::LOCAL_FILE)) {
            return sprintf('Unable to create "%s".', self::LOCAL_FILE);
        }

        return null;
    }

    public function enabled(): bool
    {
        return file_exists(self::ACTIVE_FILE);
    }

    /**
     * Whether the aggregated configuration cache is currently on disk.
     */
    public function hasConfigCache(): bool
    {
        $path = $this->configCachePath;

        return null !== $path && file_exists($path);
    }

    /**
     * Links the dist file rather than copying it wherever the reference trusts
     * symlinks, so the committed file stays the single source of truth.
     */
    private function place(string $source, string $destination): bool
    {
        if (! in_array($this->platform, self::SYMLINK_PLATFORMS, strict: true)) {
            return copy($source, $destination);
        }

        return symlink(basename($source), $destination);
    }
}
