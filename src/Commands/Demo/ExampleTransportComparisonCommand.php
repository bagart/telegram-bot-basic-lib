<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Demo;

use BAGArt\ASKClient\Contracts\Transport\HttpTransportContract;
use BAGArt\ASKClient\Dto\ASKHttpRequest;
use BAGArt\ASKClient\Transport\Adapters\ASKSocketTransportAdapter;
use BAGArt\ASKClient\Transport\Adapters\CurlMultiTransportAdapter;
use BAGArt\ASKClient\Transport\Adapters\GuzzleTransportAdapter;
use Illuminate\Console\Command;

class ExampleTransportComparisonCommand extends Command
{
    protected $signature = 'tg:example:transports
                            {token : Telegram Bot Token}
                            {--count=4 : Number of requests per transport}';

    protected $description = 'Compare pure HTTP transports: curl-multi, guzzle, raw-socket';

    public function handle(): int
    {
        $token = $this->argument('token');
        $count = (int)$this->option('count');

        $this->info("Comparing transports with {$count} parallel getMe requests each:");
        $this->newLine();

        $transports = [];

        $transports['curl-multi'] = new CurlMultiTransportAdapter();

        if (extension_loaded('sockets')) {
            $transports['raw-socket'] = new ASKSocketTransportAdapter();
        }

        if (class_exists(\GuzzleHttp\Client::class)) {
            $transports['guzzle'] = new GuzzleTransportAdapter();
        }

        $results = [];

        foreach ($transports as $name => $transport) {
            $elapsed = $this->measureTransport($name, $transport, $token, $count);
            $results[] = ['transport' => $name, 'ms' => $elapsed];
        }

        $this->newLine();
        $this->table(
            ['Transport', 'Time (ms)', 'vs fastest'],
            array_map(fn (array $r) => [
                $r['transport'],
                round($r['ms'], 1),
                round($r['ms'] / min(array_column($results, 'ms')), 2).'x',
            ], $results),
        );

        return self::SUCCESS;
    }

    private function measureTransport(string $label, HttpTransportContract $transport, string $token, int $count): float
    {
        $url = "https://api.telegram.org/bot{$token}/getMe";

        $start = microtime(true);

        $promises = [];
        for ($i = 0; $i < $count; $i++) {
            $req = new ASKHttpRequest(
                url: $url,
                method: 'POST',
                headers: ['Content-Type' => 'application/json'],
                body: '{}',
                requestName: "getMe-{$i}",
            );
            $promises[$i] = $transport->requestAsync($req);
        }

        $errors = 0;
        foreach ($promises as $i => $promise) {
            try {
                $response = $promise->await();
                $this->line("  {$label}[{$i}] status={$response->getStatusCode()}");
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        $elapsed = (microtime(true) - $start) * 1000;
        $this->line("  {$label}: ".round($elapsed, 1)." ms".($errors ? " ({$errors} errors)" : ''));

        return $elapsed;
    }
}
