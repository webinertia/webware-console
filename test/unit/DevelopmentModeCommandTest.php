<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Webware\Console\DevelopmentMode;
use Webware\Console\DevelopmentModeCommand;

use function bin2hex;
use function chdir;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function is_dir;
use function is_link;
use function mkdir;
use function putenv;
use function random_bytes;
use function restore_error_handler;
use function rmdir;
use function scandir;
use function set_error_handler;
use function sprintf;
use function symlink;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(DevelopmentModeCommand::class)]
#[CoversClass(DevelopmentMode::class)]
#[CoversMethod(DevelopmentModeCommand::class, '__construct')]
#[CoversMethod(DevelopmentModeCommand::class, 'configure')]
#[CoversMethod(DevelopmentModeCommand::class, 'execute')]
#[CoversMethod(DevelopmentModeCommand::class, 'autoComposer')]
#[CoversMethod(DevelopmentModeCommand::class, 'clearCache')]
#[CoversMethod(DevelopmentModeCommand::class, 'clearConfigCache')]
#[CoversMethod(DevelopmentModeCommand::class, 'disable')]
#[CoversMethod(DevelopmentModeCommand::class, 'enable')]
#[CoversMethod(DevelopmentModeCommand::class, 'error')]
#[CoversMethod(DevelopmentModeCommand::class, 'status')]
#[CoversMethod(DevelopmentModeCommand::class, 'usage')]
#[CoversMethod(DevelopmentMode::class, '__construct')]
#[CoversMethod(DevelopmentMode::class, 'clearConfigCache')]
#[CoversMethod(DevelopmentMode::class, 'disable')]
#[CoversMethod(DevelopmentMode::class, 'enable')]
#[CoversMethod(DevelopmentMode::class, 'enabled')]
#[CoversMethod(DevelopmentMode::class, 'hasConfigCache')]
#[CoversMethod(DevelopmentMode::class, 'place')]
final class DevelopmentModeCommandTest extends TestCase
{
    private const string CACHE_FILE = 'data/cache/config-cache.php';

    private string $projectRoot = '';

    private string $originalDirectory = '';

    #[Test]
    public function testAutoComposerDisablesOnZero(): void
    {
        putenv(assignment: 'COMPOSER_DEV_MODE=0');
        file_put_contents(
            filename: DevelopmentMode::ACTIVE_FILE,
            data    : 'active file',
        );

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--auto-composer' => true]));
        static::assertStringContainsString('Development mode DISABLED.', $tester->getDisplay());
    }

    #[Test]
    public function testAutoComposerDoesNothingWhenTheVariableIsEmpty(): void
    {
        putenv(assignment: 'COMPOSER_DEV_MODE=');

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--auto-composer' => true]));
        static::assertStringContainsString(
            'COMPOSER_DEV_MODE not set. Nothing to do.',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testAutoComposerDoesNothingWhenTheVariableIsUnset(): void
    {
        putenv(assignment: 'COMPOSER_DEV_MODE');

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--auto-composer' => true]));
        static::assertStringContainsString(
            'COMPOSER_DEV_MODE not set. Nothing to do.',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testAutoComposerEnablesOnOne(): void
    {
        putenv(assignment: 'COMPOSER_DEV_MODE=1');
        $this->writeDistFile();

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--auto-composer' => true]));
        static::assertStringContainsString('Development mode ENABLED.', $tester->getDisplay());
    }

    #[Test]
    public function testAutoComposerFailsOnAnUnexpectedValue(): void
    {
        putenv(assignment: 'COMPOSER_DEV_MODE=maybe');

        $tester = $this->tester();

        static::assertSame(Command::FAILURE, $tester->execute(['--auto-composer' => true]));
        static::assertStringContainsString(
            "COMPOSER_DEV_MODE set to unexpected value ('maybe'). Nothing to do.",
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testClearCacheFailsWhenTheCacheCannotBeRemoved(): void
    {
        // A directory satisfies file_exists() but unlink() refuses it.
        mkdir(
            directory  : self::CACHE_FILE,
            permissions: 0o777,
            recursive  : true,
        );

        $tester = $this->tester(configCachePath: self::CACHE_FILE);

        $status = $this->withoutWarnings(
            static fn(): int => $tester->execute(['--clear-cache' => true]),
        );

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString(
            'Unable to remove the config cache.',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testClearCacheLeavesTheModeAlone(): void
    {
        file_put_contents(
            filename: DevelopmentMode::ACTIVE_FILE,
            data    : 'active file',
        );
        $this->writeConfigCache();

        $tester = $this->tester(configCachePath: self::CACHE_FILE);

        static::assertSame(Command::SUCCESS, $tester->execute(['--clear-cache' => true]));
        static::assertStringContainsString(
            'Removed the config cache at "data/cache/config-cache.php".',
            $tester->getDisplay(),
        );
        // Falling through to the "nothing to remove" branch would exit 0 as well, so
        // the absence of its message is part of what is being asserted.
        static::assertStringNotContainsString(
            'There is no config cache to remove.',
            $tester->getDisplay(),
        );
        static::assertFileDoesNotExist(self::CACHE_FILE);
        // The point of the flag: the mode itself is untouched.
        static::assertFileExists(DevelopmentMode::ACTIVE_FILE);
    }

    #[Test]
    public function testClearCacheReportsWhenThereIsNothingToRemove(): void
    {
        $tester = $this->tester(configCachePath: self::CACHE_FILE);

        static::assertSame(Command::SUCCESS, $tester->execute(['--clear-cache' => true]));
        static::assertStringContainsString(
            'There is no config cache to remove.',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testDisableFailsWhenTheActiveFileCannotBeRemoved(): void
    {
        // A directory satisfies file_exists() but unlink() refuses it.
        mkdir(directory: DevelopmentMode::ACTIVE_FILE);

        $tester = $this->tester();

        $status = $this->withoutWarnings(
            static fn(): int => $tester->execute(['--disable' => true]),
        );

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString(
            'Unable to remove "config/development.config.php".',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testDisableFailsWhenTheLocalOverridesFileCannotBeRemoved(): void
    {
        file_put_contents(
            filename: DevelopmentMode::ACTIVE_FILE,
            data    : 'active file',
        );

        // A directory satisfies file_exists() but unlink() refuses it.
        mkdir(directory: DevelopmentMode::LOCAL_FILE);

        $tester = $this->tester();

        $status = $this->withoutWarnings(
            static fn(): int => $tester->execute(['--disable' => true]),
        );

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString(
            'Unable to remove "config/autoload/development.local.php".',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testDisableRemovesTheActiveFile(): void
    {
        file_put_contents(
            filename: DevelopmentMode::ACTIVE_FILE,
            data    : 'active file',
        );

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--disable' => true]));
        static::assertStringContainsString('Development mode DISABLED.', $tester->getDisplay());
        static::assertFileDoesNotExist(DevelopmentMode::ACTIVE_FILE);
    }

    #[Test]
    public function testDisableRemovesTheAggregatedConfigCache(): void
    {
        file_put_contents(
            filename: DevelopmentMode::ACTIVE_FILE,
            data    : 'active file',
        );
        $this->writeConfigCache();

        $tester = $this->tester(configCachePath: self::CACHE_FILE);

        static::assertSame(Command::SUCCESS, $tester->execute(['--disable' => true]));
        static::assertStringContainsString(
            'Removed the config cache at "data/cache/config-cache.php".',
            $tester->getDisplay(),
        );
        static::assertFileDoesNotExist(self::CACHE_FILE);
    }

    #[Test]
    public function testDisableRemovesTheConfigCacheWhenAlreadyDisabled(): void
    {
        // The reference documents that both actions drop the cache, so the
        // already-disabled path drops it too.
        $this->writeConfigCache();

        $tester = $this->tester(configCachePath: self::CACHE_FILE);

        static::assertSame(Command::SUCCESS, $tester->execute(['--disable' => true]));
        static::assertStringContainsString(
            'Development mode is already disabled.',
            $tester->getDisplay(),
        );
        static::assertStringContainsString(
            'Removed the config cache at "data/cache/config-cache.php".',
            $tester->getDisplay(),
        );
        static::assertFileDoesNotExist(self::CACHE_FILE);
    }

    #[Test]
    public function testDisableRemovesTheLocalOverridesFile(): void
    {
        file_put_contents(
            filename: DevelopmentMode::ACTIVE_FILE,
            data    : 'active file',
        );
        file_put_contents(
            filename: DevelopmentMode::LOCAL_FILE,
            data    : 'local file',
        );

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--disable' => true]));
        static::assertFileDoesNotExist(DevelopmentMode::LOCAL_FILE);
    }

    #[Test]
    public function testEnableCopiesTheDistFileWhereSymlinksAreNotTrusted(): void
    {
        $this->writeDistFile(contents: 'dist contents');

        $tester = $this->tester(platform: 'Windows');

        static::assertSame(Command::SUCCESS, $tester->execute(['--enable' => true]));
        static::assertFalse(is_link(filename: DevelopmentMode::ACTIVE_FILE));
        static::assertSame(
            'dist contents',
            file_get_contents(filename: DevelopmentMode::ACTIVE_FILE),
        );
    }

    #[Test]
    public function testEnableFailsWhenTheActiveFileCannotBeCreated(): void
    {
        $this->writeDistFile();

        // A dangling symlink defeats file_exists() without defeating symlink(),
        // which refuses a path that already exists. A permissions-based failure
        // would not hold when the suite runs as root.
        symlink(
            target: sprintf('%s/nonexistent/%s', $this->projectRoot, bin2hex(random_bytes(4))),
            link  : DevelopmentMode::ACTIVE_FILE,
        );

        $tester = $this->tester();

        $status = $this->withoutWarnings(
            static fn(): int => $tester->execute(['--enable' => true]),
        );

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString(
            'Unable to create "config/development.config.php".',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testEnableFailsWhenTheDistFileIsMissing(): void
    {
        $tester = $this->tester();

        static::assertSame(Command::FAILURE, $tester->execute(['--enable' => true]));
        static::assertStringContainsString(
            'MISSING "config/development.config.php.dist".',
            $tester->getDisplay(),
        );
        static::assertFileDoesNotExist(DevelopmentMode::ACTIVE_FILE);
    }

    #[Test]
    public function testEnableFailsWhenTheLocalOverridesFileCannotBePlaced(): void
    {
        $this->writeDistFile();
        file_put_contents(
            filename: DevelopmentMode::LOCAL_DIST,
            data    : 'local contents',
        );

        // A directory at the destination defeats symlink(), which refuses a path
        // that already exists.
        mkdir(directory: DevelopmentMode::LOCAL_FILE);

        $tester = $this->tester();

        $status = $this->withoutWarnings(
            static fn(): int => $tester->execute(['--enable' => true]),
        );

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString(
            'Unable to create "config/autoload/development.local.php".',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testEnableIgnoresAConfigCacheThatIsNotThere(): void
    {
        $this->writeDistFile();

        $tester = $this->tester(configCachePath: self::CACHE_FILE);

        static::assertSame(Command::SUCCESS, $tester->execute(['--enable' => true]));
        static::assertStringNotContainsString('Removed the config cache', $tester->getDisplay());
    }

    #[Test]
    public function testEnableIgnoresTheConfigCacheWhenNoPathIsConfigured(): void
    {
        $this->writeDistFile();
        $this->writeConfigCache();

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--enable' => true]));
        static::assertStringNotContainsString('Removed the config cache', $tester->getDisplay());
        static::assertFileExists(self::CACHE_FILE);
    }

    #[Test]
    public function testEnableLinksTheDistFileWhereSymlinksAreTrusted(): void
    {
        $this->writeDistFile(contents: 'dist contents');

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--enable' => true]));
        static::assertStringContainsString('Development mode ENABLED.', $tester->getDisplay());
        static::assertTrue(is_link(filename: DevelopmentMode::ACTIVE_FILE));
        static::assertSame(
            'dist contents',
            file_get_contents(filename: DevelopmentMode::ACTIVE_FILE),
        );
    }

    #[Test]
    public function testEnablePlacesTheLocalOverridesFile(): void
    {
        $this->writeDistFile();
        file_put_contents(
            filename: DevelopmentMode::LOCAL_DIST,
            data    : 'local contents',
        );

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--enable' => true]));
        static::assertSame(
            'local contents',
            file_get_contents(filename: DevelopmentMode::LOCAL_FILE),
        );
    }

    #[Test]
    public function testEnableRemovesTheAggregatedConfigCache(): void
    {
        $this->writeDistFile();
        $this->writeConfigCache();

        $tester = $this->tester(configCachePath: self::CACHE_FILE);

        static::assertSame(Command::SUCCESS, $tester->execute(['--enable' => true]));
        static::assertStringContainsString(
            'Removed the config cache at "data/cache/config-cache.php".',
            $tester->getDisplay(),
        );
        static::assertFileDoesNotExist(self::CACHE_FILE);
    }

    #[Test]
    public function testEnableRemovesTheConfigCacheWhenAlreadyEnabled(): void
    {
        file_put_contents(
            filename: DevelopmentMode::ACTIVE_FILE,
            data    : 'existing active file',
        );
        $this->writeConfigCache();

        $tester = $this->tester(configCachePath: self::CACHE_FILE);

        static::assertSame(Command::SUCCESS, $tester->execute(['--enable' => true]));
        static::assertStringContainsString(
            'Development mode is already enabled.',
            $tester->getDisplay(),
        );
        static::assertStringContainsString(
            'Removed the config cache at "data/cache/config-cache.php".',
            $tester->getDisplay(),
        );
        static::assertFileDoesNotExist(self::CACHE_FILE);
        static::assertSame(
            'existing active file',
            file_get_contents(filename: DevelopmentMode::ACTIVE_FILE),
        );
    }

    #[Test]
    public function testStatusReportsADisabledApplication(): void
    {
        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--status' => true]));
        static::assertStringContainsString(
            'Development mode is DISABLED.',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testStatusReportsAnEnabledApplication(): void
    {
        file_put_contents(
            filename: DevelopmentMode::ACTIVE_FILE,
            data    : 'active file',
        );

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--status' => true]));
        static::assertStringContainsString(
            'Development mode is ENABLED.',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testUsageIsPrintedWhenNoActionIsRequested(): void
    {
        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();

        static::assertStringContainsString('No action requested.', $display);
        static::assertStringContainsString('Usage:', $display);
        static::assertStringContainsString(
            '  dev:mode --enable        Create the active file to enable development mode',
            $display,
        );
        static::assertStringContainsString(
            '  dev:mode --disable       Remove the active file to disable development mode',
            $display,
        );
        static::assertStringContainsString(
            '  dev:mode --status        Report whether development mode is currently enabled',
            $display,
        );
        static::assertStringContainsString(
            '  dev:mode --clear-cache   Remove the aggregated config cache without changing the mode',
            $display,
        );
        // The contract block is separated from the flag list by a blank line.
        static::assertStringContainsString("\n\nEnabling creates", $display);
        static::assertStringContainsString(
            'Enabling creates "config/development.config.php" from "config/development.config.php.dist",',
            $display,
        );
        static::assertStringContainsString(
            'and "config/autoload/development.local.php" from "config/autoload/development.local.php.dist" when that file exists.',
            $display,
        );
        static::assertStringContainsString(
            'Disabling removes both, and either action drops the aggregated config cache.',
            $display,
        );
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $origin = getcwd();

        if (false === $origin) {
            static::fail(message: 'Unable to determine the working directory.');
        }

        $this->originalDirectory = $origin;
        $this->projectRoot       = sprintf(
            '%s/webware-console-%s',
            sys_get_temp_dir(),
            bin2hex(random_bytes(8)),
        );

        mkdir(
            directory  : "{$this->projectRoot}/config",
            permissions: 0o777,
            recursive  : true,
        );

        // The optional local overrides file lives one level deeper.
        mkdir(
            directory  : "{$this->projectRoot}/config/autoload",
            permissions: 0o777,
            recursive  : true,
        );

        // The command resolves every path against the working directory, which
        // the console binary sets to the project root.
        chdir(directory: $this->projectRoot);
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv(assignment: 'COMPOSER_DEV_MODE');
        chdir(directory: $this->originalDirectory);
        $this->removeDirectory($this->projectRoot);

        parent::tearDown();
    }

    private function removeDirectory(string $path): void
    {
        if (false === is_dir(filename: $path)) {
            return;
        }

        $entries = scandir(directory: $path);

        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $full = "{$path}/{$entry}";

            if (is_dir(filename: $full)) {
                $this->removeDirectory($full);

                continue;
            }

            unlink(filename: $full);
        }

        rmdir(directory: $path);
    }

    private function tester(?string $configCachePath = null, string $platform = 'Linux'): CommandTester
    {
        return new CommandTester(new DevelopmentModeCommand(
            configCachePath: $configCachePath,
            platform       : $platform,
        ));
    }

    /**
     * Failure branches rely on the filesystem refusing an operation, which PHP
     * reports as a warning. PHPUnit is configured to fail on warnings, so the
     * expected warning is swallowed for the duration of the call.
     */
    private function withoutWarnings(callable $operation): int
    {
        set_error_handler(static fn(): bool => true);

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    private function writeConfigCache(string $path = self::CACHE_FILE): void
    {
        mkdir(
            directory  : dirname(path: $path),
            permissions: 0o777,
            recursive  : true,
        );
        file_put_contents(
            filename: $path,
            data    : 'cached configuration',
        );
    }

    private function writeDistFile(string $contents = 'dist contents'): void
    {
        file_put_contents(
            filename: DevelopmentMode::DIST_FILE,
            data    : $contents,
        );
    }
}
