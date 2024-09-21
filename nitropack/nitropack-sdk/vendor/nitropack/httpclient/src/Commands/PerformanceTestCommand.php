<?php
declare(strict_types=1);

namespace NitroPack\HttpClient\Commands;

use Closure;
use NitroPack\HttpClient\HttpClient;
use NitroPack\HttpClient\HttpClientMulti;
use NitroPack\HttpClient\HttpConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use UnexpectedValueException;

class PerformanceTestCommand extends Command {
    protected static $defaultName = 'nitropack:http-client:performance-test';
    private OutputInterface $output;

    protected function configure(): void
    {
        $this->setDescription('Run a performance test');
        $this->addArgument('iterations', InputArgument::OPTIONAL, 'How much requests to make', '1000');
        $this->addArgument('concurrency', InputArgument::OPTIONAL, 'How much requests to make concurrently', '10');
        $this->addArgument('encoding', InputArgument::OPTIONAL, 'What encoding to request from the web server', 'gzip');
        $this->addOption('url', null, InputArgument::OPTIONAL, 'The URL to test against', 'http://127.0.0.1:3000');
        $this->addOption('expected-file', null, InputArgument::OPTIONAL, 'The expected file content of the response', null);
    }

    private function getPositiveIntegerArgumentValue(string $argumentName, InputInterface $input, OutputInterface $output): int
    {
        $argumentValue = $input->getArgument($argumentName);

        if (! ctype_digit($argumentValue)) {
            throw new UnexpectedValueException($argumentName. ' must be a number');
        }

        $value = (int) $argumentValue;

        if ($value <= 0) {
            throw new UnexpectedValueException($argumentName. ' must be a positive number');
        }

        return $value;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $url = $input->getOption('url');
        $expectedFile = $input->getOption('expected-file');

        $expectedContent = match ($expectedFile !== null) {
            true => match (file_exists($expectedFile)) {
                true => file_get_contents($expectedFile),
                false => throw new UnexpectedValueException('Expected file does not exist'),
            },
            default => $this->getExpectedContent(),
        };

        $concurrency = $this->getPositiveIntegerArgumentValue('concurrency', $input, $output);
        $iterations = $this->getPositiveIntegerArgumentValue('iterations', $input, $output);
        $encoding = $input->getArgument('encoding');

        $output->writeln("Issuing $iterations requests using $encoding encoding with concurrency of $concurrency..");

        $startedAt = microtime(true);
        [$countSuccessfulRequests, $countFailedRequests] = $this->runTest($encoding, $url, $iterations, $concurrency, $expectedContent);

        $output->writeln("\n");

        $output->writeln(
            sprintf(
                "Finished in %.2f seconds",
                microtime(true) - $startedAt
            )
        );

        if ($countFailedRequests === 0) {
            return 0;
        }

        $output->writeln("<info>Successful requests: $countSuccessfulRequests</info>");
        $output->writeln("<error>Failed requests: $countFailedRequests</error>");

        return 1;
    }

    private function runTest(string $acceptEncoding, string $url, int $iterations, int $concurrency, string $expectedContent): array
    {
        $httpConfig = new HttpConfig();
        $httpConfig->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');

        $scraper = new HttpClientMulti();
        $progressBar = new ProgressBar($this->output, $iterations);

        $factoryHttpClient = fn() => $this->factoryHttpClient($url, $httpConfig, $acceptEncoding);
        $manager = new class ($progressBar, $scraper, $factoryHttpClient, $iterations, $concurrency, $expectedContent) {
            private int $countSuccessfulRequests = 0;
            private int $countFailedRequests = 0;
            private int $totalRequestScheduledCount = 0;

            public function __construct(
                private readonly ProgressBar $progressBar,
                private readonly HttpClientMulti $scraper,
                private readonly Closure $factoryHttpClient,
                private readonly int $iterations,
                private readonly int $concurrency,
                private readonly string $expectedContent,
            ) {}
            public function onSuccess(HttpClient $httpClient): void
            {
                $actualContent = $httpClient->getBody();

                if (! is_string($actualContent)) {
                    throw new UnexpectedValueException('Response body is not a string');
                }

                if ($actualContent !== $this->expectedContent) {
                    file_put_contents('err_actual.txt', $actualContent);
                    file_put_contents('err_expected.txt', $this->expectedContent);

                    dd('Expected content does not match actual content. Check err_actual.txt vs err_expected.txt');
                }

                ++$this->countSuccessfulRequests;
                $this->advance();
            }

            public function onFailure(HttpClient $httpClient): void
            {
                ++$this->countFailedRequests;
                $this->advance();
            }

            private function advance(): void
            {
                if ($this->totalRequestScheduledCount >= $this->iterations) {
                    $this->progressBar->finish();
                    return;
                }

                $this->progressBar->advance();
                ++$this->totalRequestScheduledCount;

                /** @var HttpClient $httpClient */
                $httpClient = $this->factoryHttpClient->__invoke();
                $httpClient->fetch(true, 'GET', true);

                $this->scraper->push($httpClient);
            }

            public function getCount(): array
            {
                return [$this->countSuccessfulRequests, $this->countFailedRequests];
            }

            public function start(): void
            {
                foreach (range(1, min($this->concurrency, $this->iterations)) as $i) {
                    /** @var HttpClient $httpClient */
                    $httpClient = $this->factoryHttpClient->__invoke();
                    $this->scraper->push($httpClient);

                    ++$this->totalRequestScheduledCount;
                    $this->progressBar->advance();
                }

                $this->scraper->fetchAll();
                HttpClient::drainConnections();
            }
        };

        $scraper->returnClients(false);
        $scraper->onSuccess($manager->onSuccess(...));
        $scraper->onError($manager->onFailure(...));

        $manager->start();

        return $manager->getCount();
    }

    private function factoryHttpClient(string $url, HttpConfig $httpConfig, string $acceptEncoding): HttpClient
    {
        $httpClient = new HttpClient($url, $httpConfig);
        $httpClient->accept_deflate = false;
        $httpClient->setHeader('Accept-Encoding', $acceptEncoding);

        return $httpClient;
    }

    private function getExpectedContent(): string
    {
        $string = 'Hello, World!';
        $parts = [];

        foreach (range(1, 5) as $i) {
            $parts[] = trim(str_repeat($string .' ', 8));
        }

        return implode("\n", $parts);
    }
}
