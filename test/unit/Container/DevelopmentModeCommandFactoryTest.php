<?php

declare(strict_types=1);

namespace Webware\Console\Test\Unit\Container;

use ArrayObject;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Webware\Console\Container\DevelopmentModeCommandFactory;
use Webware\Console\DevelopmentModeCommand;

use function bin2hex;
use function chdir;
use function file_put_contents;
use function getcwd;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(DevelopmentModeCommandFactory::class)]
#[CoversMethod(DevelopmentModeCommandFactory::class, '__invoke')]
#[CoversMethod(DevelopmentModeCommandFactory::class, 'configCachePath')]
final class DevelopmentModeCommandFactoryTest extends TestCase
{
    private const string CACHE_FILE = 'data/cache/config-cache.php';

    private string $projectRoot = '';

    private string $originalDirectory = '';

    #[Test]
    public function testBuildsTheCommand(): void
    {
        $command = (new DevelopmentModeCommandFactory())($this->container([
            'config_cache_path' => self::CACHE_FILE,
        ]));

        static::assertInstanceOf(DevelopmentModeCommand::class, $command);
    }

    #[Test]
    public function testPassesTheConfiguredCachePathToTheCommand(): void
    {
        $command = (new DevelopmentModeCommandFactory())($this->container([
            'config_cache_path' => self::CACHE_FILE,
        ]));

        static::assertSame(
            Command::SUCCESS,
            new CommandTester($command)->execute(['--enable' => true]),
        );
        static::assertFileDoesNotExist(self::CACHE_FILE);
    }

    #[Test]
    public function testToleratesAConfigCachePathThatIsNotAString(): void
    {
        $command = (new DevelopmentModeCommandFactory())(
            $this->container(['config_cache_path' => 42]),
        );

        static::assertSame(
            Command::SUCCESS,
            new CommandTester($command)->execute(['--enable' => true]),
        );
        static::assertFileExists(self::CACHE_FILE);
    }

    #[Test]
    public function testToleratesAConfigThatIsNotAnArray(): void
    {
        // Deliberately offset-accessible: were the `is_array()` guard removed,
        // this would yield the cache path, the cache would be dropped, and the
        // assertion below would fail. That is what holds the guard in place.
        $command = (new DevelopmentModeCommandFactory())($this->container(
            config: new ArrayObject(['config_cache_path' => self::CACHE_FILE]),
        ));

        static::assertSame(
            Command::SUCCESS,
            new CommandTester($command)->execute(['--enable' => true]),
        );
        static::assertFileExists(self::CACHE_FILE);
    }

    #[Test]
    public function testToleratesConfigWithoutACachePath(): void
    {
        $command = (new DevelopmentModeCommandFactory())($this->container([]));

        static::assertSame(
            Command::SUCCESS,
            new CommandTester($command)->execute(['--enable' => true]),
        );
        static::assertFileExists(self::CACHE_FILE);
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
        mkdir(
            directory  : "{$this->projectRoot}/data/cache",
            permissions: 0o777,
            recursive  : true,
        );

        chdir(directory: $this->projectRoot);

        file_put_contents(
            filename: DevelopmentModeCommand::DIST_FILE,
            data    : 'dist contents',
        );
        file_put_contents(
            filename: self::CACHE_FILE,
            data    : 'cached configuration',
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        chdir(directory: $this->originalDirectory);
        $this->removeDirectory($this->projectRoot);

        parent::tearDown();
    }

    /**
     * @param array<array-key, mixed>|ArrayObject<array-key, mixed>|string $config
     */
    private function container(array|ArrayObject|string $config): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);

        $container->method('get')->willReturn($config);

        return $container;
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
}
