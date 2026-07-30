<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Demo;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiClientContract;
use Illuminate\Console\Command;

class ExampleParallelFuturesCommand extends Command
{
    protected $signature = 'tg:example:parallel-futures
                            {token : Telegram Bot Token}
                            {--count=5 : Number of parallel requests}';

    protected $description = 'Example: N parallel API requests via ASKFuture — fire all, then await all';

    public function handle(TgBotApiClientContract $client): int
    {
        $token = $this->argument('token');
        $count = (int)$this->option('count');
        $config = new TgBotConfig(token: $token);

        $start = microtime(true);

        $futures = [];
        for ($i = 0; $i < $count; $i++) {
            $futures[$i] = $client->requestAsync($config, 'getMe');
        }

        foreach ($futures as $i => $future) {
            $result = $future->await();
            $this->line("  [{$i}] ok: ".($result['ok'] ? 'true' : 'false'));
        }

        $elapsed = (microtime(true) - $start) * 1000;
        $this->info("Parallel (futures): {$count} requests in ".round($elapsed, 1)." ms");

        return self::SUCCESS;
    }
}
