<?php

declare(strict_types=1);

use BAGArt\TelegramBot\ApiCommunication;
use BAGArt\TelegramBot\ApiCommunication\Exceptions\TgApiReturnException;
use BAGArt\TelegramBot\TgApi;
use BAGArt\TelegramBot\TgApiServices;
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

$tgApiDTOMapper = new TgApiServices\TgApiDTOMapper(
    logger: $logger,
    tgApiDTORegistry: new TgApiServices\TgEntityToDTORegistryFactory($logger)
        ->default(TgApi\TgApiEntityScopeEnum::class),
);

$tgDTOClient = new ApiCommunication\TgBotApiDTOClient(
    tgClient: new ApiCommunication\TgBotApiClient(
        rateLimiter: new ApiCommunication\ClientServices\TgRateLimiter($cache),
        circuitBreaker: new ApiCommunication\ClientServices\TgCircuitBreaker($cache),
        retryPolicy: new ApiCommunication\ClientServices\TgRetryPolicy(),
    ),
    tgApiDTOMapper: $tgApiDTOMapper,
    returnParser: new ApiCommunication\TgBotApiReturnParser(
        tgApiDTOMapper: $tgApiDTOMapper,
        logger: $logger,
    ),
);

echo "=== Example: Long Poller Mode (with DTO) ===\n";
echo "Starting long poller. Press Ctrl+C to stop.\n\n";

$options = getopt('', ['echo', 'show']);
$show = array_key_exists('show', $options);
$echo = array_key_exists('echo', $options);

$offset = 0;
$updateCount = 0;
while (true) {
    try {
        $response = $tgDTOClient->request(
            $token,
            new TgApi\Methods\DTO\GetUpdatesMethodDTO(
                allowedUpdates: ['message', 'callback_query'],
                limit: 100,
                timeout: 60,
                offset: $offset,
            )
        );

        foreach ($response->result as $update) {
            assert($update instanceof TgApi\Types\DTO\UpdateTypeDTO);
            $offset = max($offset, $update->updateId + 1);

            if ($update->message) {
                if ($show) {
                    var_dump([
                        'class' => $update::class,
                        '$update' => $tgApiDTOMapper->toArray($update),
                    ]);
                }
                if ($echo) {
                    $sendMessageResponse = $tgDTOClient->request(
                        $token,
                        new TgApi\Methods\DTO\SendMessageMethodDTO(
                            chatId: $update->message->chat->id,
                            text: "echo: {$update->message->text}",
                        ),
                    );
                    if ($sendMessageResponse->ok) {
                        echo '+';
                    }
                }
            }
        }
    } catch (TgApiReturnException $e) {
        var_dump($e);
    }

    echo '.';
    //usleep(500000);
}
