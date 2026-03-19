<?php

declare(strict_types=1);

use BAGArt\TelegramBot\ApiCommunication;
use BAGArt\TelegramBot\ApiCommunication\Exceptions\TgApiReturnException;
use BAGArt\TelegramBot\Wrappers\TgBotCacheWrapper;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use BAGArt\TelegramBotBasic\Extra\TinyFileCache;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

require_once __DIR__.'/../../../../vendor/autoload.php';

$token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN');
if (!$token) {
    throw new RuntimeException('Use "export TELEGRAM_BOT_TOKEN=xxx:xxx" to set token');
}

TgBotCacheWrapper::init(new TinyFileCache());
$cache = new TgBotCacheWrapper();

TgBotLogWrapper::init(
    logger: new Logger(
        name: 'TelegramBot',
        handlers: [
            new StreamHandler(
                stream: 'php://stderr',
                level: Level::Debug,
            ),
        ],
    ),
);
$logger = new TgBotLogWrapper();

$tgClient = new ApiCommunication\TgBotApiClient(
    rateLimiter: new ApiCommunication\ClientServices\TgRateLimiter($cache),
    circuitBreaker: new ApiCommunication\ClientServices\TgCircuitBreaker($cache),
    retryPolicy: new ApiCommunication\ClientServices\TgRetryPolicy(),
);

echo "=== Example: Long Poller Mode (with DTO) ===\n";
echo "Starting long poller. Press Ctrl+C to stop.\n\n";

$offset = 0;
$updateCount = 0;
while (true) {
    try {
        $response = $tgClient
            ->requestAsync(
                token: $token,
                method: 'getUpdates',
                params: [
                    'allowedUpdates' => ['message', 'callback_query'],
                    'limit' => 100,
                    'timeout' => 60,
                    'offset' => $offset,
                ],
                attempt: 1,
            )
            ->wait();

        foreach ($response['result'] ?? [] as $update) {
            $offset = max($offset, $update['update_id'] + 1);

            var_dump(['$update' => $update]);
        }
    } catch (TgApiReturnException $e) {
        var_dump($e);
    }

    echo '.';
    //usleep(500000);
}
