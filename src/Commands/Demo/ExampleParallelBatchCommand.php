<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Demo;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiClientContract;
use Illuminate\Console\Command;

class ExampleParallelBatchCommand extends Command
{
    protected $signature = 'tg:example:parallel-batch
                            {token : Telegram Bot Token}
                            {--count=10 : Total number of requests}
                            {--batch=3 : Requests per batch}';

    protected $description = 'Example: parallel requests in batches of N — fire batch, await all, repeat';

    public function handle(TgBotApiClientContract $client): int
    {
        $token = $this->argument('token');
        $count = (int) $this->option('count');
        $batchSize = (int) $this->option('batch');
        $config = new TgBotConfig(token: $token);

        $start = microtime(true);

        $batches = array_chunk(range(0, $count - 1), $batchSize);

        foreach ($batches as $batchIndex => $indices) {
            $batchStart = microtime(true);

            $futures = [];
            foreach ($indices as $i) {
                $futures[$i] = $client->requestAsync($config, 'getMe');
            }

            foreach ($futures as $i => $future) {
                $result = $future->await();
                $this->line("  batch[{$batchIndex}] req[{$i}] ok: " . ($result['ok'] ? 'true' : 'false'));
            }

            $batchElapsed = (microtime(true) - $batchStart) * 1000;
            $this->line("  batch[{$batchIndex}] done in " . round($batchElapsed, 1) . " ms");
        }

        $elapsed = (microtime(true) - $start) * 1000;
        $this->info("Batch parallel (batch={$batchSize}): {$count} requests in " . round($elapsed, 1) . " ms");

        return self::SUCCESS;
    }
}
