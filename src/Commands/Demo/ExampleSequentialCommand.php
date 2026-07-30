<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Demo;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiClientContract;
use Illuminate\Console\Command;

class ExampleSequentialCommand extends Command
{
    protected $signature = 'tg:example:sequential
                            {token : Telegram Bot Token}
                            {--count=5 : Number of sequential requests}';

    protected $description = 'Example: N sequential API requests, one after another';

    public function handle(TgBotApiClientContract $client): int
    {
        $token = $this->argument('token');
        $count = (int)$this->option('count');
        $config = new TgBotConfig(token: $token);

        $start = microtime(true);

        for ($i = 0; $i < $count; $i++) {
            $result = $client->request($config, 'getMe');
            $this->line("  [{$i}] ok: ".($result['ok'] ? 'true' : 'false'));
        }

        $elapsed = (microtime(true) - $start) * 1000;
        $this->info("Sequential: {$count} requests in ".round($elapsed, 1)." ms");

        return self::SUCCESS;
    }
}
