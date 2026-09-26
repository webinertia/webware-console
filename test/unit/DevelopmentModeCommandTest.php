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
use Webware\Console\DevelopmentModeCommand;

use function bin2hex;
use function chdir;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function is_dir;
use function mkdir;
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
#[CoversMethod(DevelopmentModeCommand::class, '__construct')]
#[CoversMethod(DevelopmentModeCommand::class, 'configure')]
#[CoversMethod(DevelopmentModeCommand::class, 'execute')]
#[CoversMethod(DevelopmentModeCommand::class, 'enable')]
#[CoversMethod(DevelopmentModeCommand::class, 'disable')]
#[CoversMethod(DevelopmentModeCommand::class, 'status')]
#[CoversMethod(DevelopmentModeCommand::class, 'usage')]
#[CoversMethod(DevelopmentModeCommand::class, 'clearConfigCache')]
#[CoversMethod(DevelopmentModeCommand::class, 'copyDistFile')]
#[CoversMethod(DevelopmentModeCommand::class, 'developmentModeEnabled')]
final class DevelopmentModeCommandTest extends TestCase
{
    private const string CACHE_FILE = 'data/cache/config-cache.php';

    private string $projectRoot = '';

    private string $originalDirectory = '';

    #[Test]
    public function testDisableFailsWhenTheActiveFileCannotBeRemoved(): void
    {
        // A directory satisfies file_exists() but unlink() refuses it.
        mkdir(directory: DevelopmentModeCommand::ACTIVE_FILE);

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
    public function testDisableRemovesTheActiveFile(): void
    {
        file_put_contents(
            filename: DevelopmentModeCommand::ACTIVE_FILE,
            data    : 'active file',
        );

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--disable' => true]));
        static::assertStringContainsString('Development mode DISABLED.', $tester->getDisplay());
        static::assertFileDoesNotExist(DevelopmentModeCommand::ACTIVE_FILE);
    }

    #[Test]
    public function testDisableRemovesTheAggregatedConfigCache(): void
    {
        file_put_contents(
            filename: DevelopmentModeCommand::ACTIVE_FILE,
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
        // A cache outlives the toggle that produced it, so the already-disabled
        // path has to drop it too.
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
    public function testEnableCopiesTheDistFileToTheActiveFile(): void
    {
        $this->writeDistFile(contents: 'dist contents');

        $tester = $this->tester();

        static::assertSame(Command::SUCCESS, $tester->execute(['--enable' => true]));
        static::assertStringContainsString('Development mode ENABLED.', $tester->getDisplay());
        static::assertSame(
            'dist contents',
            file_get_contents(filename: DevelopmentModeCommand::ACTIVE_FILE),
        );
    }

    #[Test]
    public function testEnableFailsWhenTheDistFileCannotBeCopied(): void
    {
        $this->writeDistFile();

        // A dangling symlink defeats file_exists() without defeating copy(),
        // which fails writing through it. A permissions-based failure would not
        // hold when the suite runs as root.
        symlink(
            target: sprintf('%s/nonexistent/%s', $this->projectRoot, bin2hex(random_bytes(4))),
            link  : DevelopmentModeCommand::ACTIVE_FILE,
        );

        $tester = $this->tester();

        $status = $this->withoutWarnings(
            static fn(): int => $tester->execute(['--enable' => true]),
        );

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString(
            'Unable to copy "config/development.config.php.dist" to "config/development.config.php".',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function testEnableFailsWhenTheDistFileIsMissing(): void
    {
        $tester = $this->tester();

        static::assertSame(Command::FAILURE, $tester->execute(['--enable' => true]));
        static::assertStringContainsString(
            'Dist file "config/development.config.php.dist" not found.',
            $tester->getDisplay(),
        );
        static::assertFileDoesNotExist(DevelopmentModeCommand::ACTIVE_FILE);
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
            filename: DevelopmentModeCommand::ACTIVE_FILE,
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
            file_get_contents(filename: DevelopmentModeCommand::ACTIVE_FILE),
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
            filename: DevelopmentModeCommand::ACTIVE_FILE,
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
            '  dev:mode --enable   Copy the dist file to enable development mode',
            $display,
        );
        static::assertStringContainsString(
            '  dev:mode --disable  Remove the active file to disable development mode',
            $display,
        );
        static::assertStringContainsString(
            '  dev:mode --status   Report whether development mode is currently enabled',
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

        // The command resolves every path against the working directory, which
        // the console binary sets to the project root.
        chdir(directory: $this->projectRoot);
    }

    #[Override]
    protected function tearDown(): void
    {
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

    private function tester(?string $configCachePath = null): CommandTester
    {
        return new CommandTester(new DevelopmentModeCommand(configCachePath: $configCachePath));
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
            filename: DevelopmentModeCommand::DIST_FILE,
            data    : $contents,
        );
    }
}
