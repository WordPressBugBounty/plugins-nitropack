<?php
declare(strict_types=1);

namespace Tests\Feature;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Socket\SocketServer;
use ReflectionClass;
use Symfony\Component\Process\Process;
use Testcontainer\Container\Container;
use Testcontainer\Wait\WaitForHttp;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected const DOCKER_IMAGE_WEB_CONTAINER = 'nitropack-httpclient:latest';
    protected const MOCKSERVER_ADDR = '127.0.0.1:3000';
    protected const LOG_LEVEL = Logger::CRITICAL;

    protected static Process $mockServer;
    protected static int $reactServerPid;
    protected static Container $webContainer;
    protected static string $webContainerEntryPointPath;
    protected static LoggerInterface $logger;
    protected static string $tempDir = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$tempDir = static::getTempDir();
        static::$logger = static::createLogger(static::LOG_LEVEL, self::getClassShortName());
    }

    protected static function reactServerStart(callable $callback): int
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            die('Could not fork');
        }

        if ($pid) {
            // Wait for the web server to start
            $healthEndpoint = static::mockServerUrl('/health');

            while (@file_get_contents($healthEndpoint) === false) {
                usleep(10000); // Wait for 10 milliseconds
            }

            return $pid;
        }

        (new HttpServer(new StreamingRequestMiddleware(), $callback))
            ->listen(new SocketServer(static::MOCKSERVER_ADDR));

        (Loop::get())->run();

        exit(0);
    }

    protected static function webContainerStart(?string $rootDir = null): Container
    {
        if (! is_string($rootDir)) {
            $rootDir = sprintf(
                '%s/%s/mockserver',
                static::getClassDirectory(),
                static::getClassShortName()
            );
        }

        $container = Container::make(static::DOCKER_IMAGE_WEB_CONTAINER);
        $container->withHealthCheckCommand('nc -z 127.0.0.1 3000');
        $container->withMount($rootDir, '/var/www/html');
        $container->withPort('3000', '3000');
        $container->withWait(WaitForHttp::make(3000)->withStatusCode(403));

        $entryPointContent =<<<'EOL'
#!/bin/sh
set -eux;

## Disabling deflate module, so we have complete control over the response.
## If enabled, we can't properly test chunked responses.
a2dismod deflate -f

## Listen on port 3000
sed -i 's/Listen 80/Listen 3000/' /etc/apache2/ports.conf
sed -i 's/<VirtualHost \*:80>/<VirtualHost *:3000>/' /etc/apache2/sites-enabled/000-default.conf

## Required PHP modules
docker-php-ext-install pcntl
pecl install -f -o brotli && docker-php-ext-enable brotli

/usr/local/bin/apache2-foreground
EOL;

        $entryPointPath = tempnam(static::$tempDir, 'entrypoint');

        if (! is_string($entryPointPath)) {
            throw new \RuntimeException('Could not create entrypoint file');
        }

        static::$webContainerEntryPointPath = $entryPointPath;

        if (! file_put_contents($entryPointPath, $entryPointContent)) {
            throw new \RuntimeException('Could not write entrypoint file');
        }

        if (! chmod($entryPointPath, 0755)) {
            throw new \RuntimeException('Could not chmod entrypoint file');
        }

        $container->withMount($entryPointPath, '/entrypoint.sh');
        $container->withEntryPoint('/entrypoint.sh');

        $container->run();

        return $container;
    }

    protected static function webContainerStop(Container $container): void
    {
        $container->stop();
        unlink(static::$webContainerEntryPointPath);
    }

    protected static function reactServerStop(int $pid): void
    {
        posix_kill($pid, SIGTERM);
        pcntl_wait($status); //Protect against Zombie children
    }

    protected static function mockServerStart(?string $rootDir = null): Process
    {
        if (! is_string($rootDir)) {
            $rootDir = sprintf(
                '%s/%s/mockserver',
                static::getClassDirectory(),
                static::getClassShortName()
            );
        }

        $mockServer = new Process(['php', '-S', self::MOCKSERVER_ADDR, '-t', $rootDir,]);
        $mockServer->setEnv(['PHP_CLI_SERVER_WORKERS' => '4']);

        $mockServer->start();

        $i = 0;
        $startedAt = microtime(true);

        do {
            static::$logger->debug('Waiting for webserver to start', ['attempt' => ++$i]);
            usleep(10000);

            $output = $mockServer->getErrorOutput();

            if (str_contains($output, 'Development Server')) {
                static::$logger->debug('Webserver started', ['took' => microtime(true) - $startedAt]);
                break;
            }

            if (! $mockServer->isRunning()) {
                throw new \RuntimeException('PHP mock server failed to start: '. $mockServer->getErrorOutput());
            }

            if ($i > 30) {
                dump($mockServer->getOutput(), $mockServer->getErrorOutput());
                throw new \RuntimeException('Timeout waiting for PHP mock server');
            }
        } while (true);

        return $mockServer;
    }

    protected static function mockServerStop(Process $mockServer, int $signal = SIGKILL): void
    {
        static::mockServerStopChildren($mockServer, $signal);

        $mockServer->stop(0, $signal);

        $i = 0;
        $startedAt = microtime(true);

        do {
            static::$logger->debug('Waiting for webserver to stop..', ['attempt' => ++$i]);
            usleep(1000);

            if (! $mockServer->isRunning()) {
                static::$logger->debug('Webserver stopped', ['took' => microtime(true) - $startedAt]);
                break;
            }

            if ($i > 30) {
                echo "\n\n";
                throw new \RuntimeException('PHP mock server failed to stop: ' . $mockServer->getErrorOutput());
            }
        } while (true);
    }

    protected static function mockServerStopChildren(Process $mockServer, int $signal = SIGKILL): void
    {
        $mockServerPid = $mockServer->getPid();

        $childrenFilePath = '/proc/' . $mockServerPid . '/task/' . $mockServerPid . '/children';

        if (! file_exists($childrenFilePath) || ! is_readable($childrenFilePath)) {
            static::$logger->critical('Could not read children file', ['path' => $childrenFilePath]);

            return;
        }

        /** @var string[] $children */
        $children = explode(' ', file_get_contents($childrenFilePath));

        foreach ($children as $pid) {
            if (! ctype_digit($pid)) {
                continue;
            }

            shell_exec("kill -{$signal} {$pid}");
        }
    }

    protected static function mockServerUrl(string $url): string
    {
        return 'http://' . static::MOCKSERVER_ADDR . $url;
    }

    protected static function getClassShortName(): string
    {
        return substr(strrchr(static::class, '\\'), 1);
    }

    protected static function getClassDirectory(): string
    {
        return dirname((new ReflectionClass(static::class))->getFileName());
    }

    public static function createLogger(int $level = Logger::CRITICAL, string $name = 'DebugTools'): Logger
    {
        /** @var array<string, Logger> $loggers */
        static $loggers = [];

        $key = md5($name . $level);

        if (array_key_exists($key, $loggers)) {
            return $loggers[$key];
        }

        return $loggers[$key] = new Logger($name, [new StreamHandler('php://stdout', $level)]);
    }

    public static function setupExceptionErrorHandler(): void
    {
        set_error_handler(static function (int $errNo, string $errString, string $errFile = null, int $errLine): void {
            if (!(error_reporting() & $errNo)) {
                // This error code is not included in error_reporting
                return;
            }

            throw new \ErrorException($errString, 0, $errNo, $errFile, $errLine);
        });
    }

    protected static function getTempDir(): string
    {
        if (static::$tempDir !== '') {
            return static::$tempDir;
        }

        // Bitbucket Pipelines won't allow writing to /tmp, so we need to use the clone directory
        return $_SERVER['BITBUCKET_CLONE_DIR'] ?? sys_get_temp_dir();
    }
}