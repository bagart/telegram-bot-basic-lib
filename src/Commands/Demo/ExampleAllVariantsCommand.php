<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Demo;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiClientContract;
use Illuminate\Console\Command;

class ExampleAllVariantsCommand extends Command
{
    protected $signature = 'tg:example:all-variants
                            {token : Telegram Bot Token}
                            {--count=6 : Number of requests per variant}';

    protected $description = 'Compare all parallel request variants: sequential, futures, batch';

    public function handle(TgBotApiClientContract $client): int
    {
        $token = $this->argument('token');
        $count = (int)$this->option('count');
        $config = new TgBotConfig(token: $token);

        $this->info("Comparing request patterns with {$count} requests each:");
        $this->newLine();

        $results = [];

        $elapsed = $this->measure('Sequential', function () use ($client, $config, $count): void {
            for ($i = 0; $i < $count; $i++) {
                $client->request($config, 'getMe');
            }
        });
        $results[] = ['variant' => 'Sequential', 'ms' => $elapsed];

        $elapsed = $this->measure('Parallel (futures)', function () use ($client, $config, $count): void {
            $futures = [];
            for ($i = 0; $i < $count; $i++) {
                $futures[$i] = $client->requestAsync($config, 'getMe');
            }
            foreach ($futures as $future) {
                $future->await();
            }
        });
        $results[] = ['variant' => 'Parallel (futures)', 'ms' => $elapsed];

        $batchSize = max(2, (int)ceil($count / 3));
        $elapsed = $this->measure(
            "Batch (batch={$batchSize})",
            function () use ($client, $config, $count, $batchSize): void {
                foreach (array_chunk(range(0, $count - 1), $batchSize) as $indices) {
                    $futures = [];
                    foreach ($indices as $i) {
                        $futures[$i] = $client->requestAsync($config, 'getMe');
                    }
                    foreach ($futures as $future) {
                        $future->await();
                    }
                }
            }
        );
        $results[] = ['variant' => "Batch (batch={$batchSize})", 'ms' => $elapsed];

        $this->newLine();
        $this->table(
            ['Variant', 'Time (ms)', 'Speedup'],
            array_map(fn (array $r, int $i) => [
                $r['variant'],
                round($r['ms'], 1),
                $i === 0 ? '1.0x' : round($results[0]['ms'] / $r['ms'], 2).'x',
            ], $results, array_keys($results)),
        );

        return self::SUCCESS;
    }

    private function measure(string $label, callable $fn): float
    {
        $start = microtime(true);
        $fn();
        $elapsed = (microtime(true) - $start) * 1000;

        $this->line("  {$label}: ".round($elapsed, 1)." ms");

        return $elapsed;
    }
}
