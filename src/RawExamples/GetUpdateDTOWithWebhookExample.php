<?php

declare(strict_types=1);

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

echo "\n=== Example: Callback (Webhook) Mode ===\n";

$webhookPayloads = [
    [
        'update_id' => 123456789,
        'message' => [
            'message_id' => 1,
            'from' => [
                'id' => 123456789,
                'is_bot' => false,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'username' => 'johndoe',
            ],
            'chat' => [
                'id' => 123456789,
                'type' => 'private',
            ],
            'date' => time(),
            'text' => '/start',
        ],
    ],
    [
        'update_id' => 123456790,
        'message' => [
            'message_id' => 2,
            'chat' => ['id' => 987654321, 'type' => 'private'],
            'date' => time(),
            'text' => 'Hello bot!',
        ],
    ],
    [
        'update_id' => 123456791,
        'callback_query' => [
            'id' => '123456789',
            'from' => [
                'id' => 123456789,
                'is_bot' => false,
                'first_name' => 'John',
            ],
            'chat_instance' => '123456789',
            'data' => 'button_click_1',
        ],
    ],
];

foreach ($webhookPayloads as $updateRaw) {
    $update = $tgApiDTOMapper->fromArray(
        TgApi\Types\DTO\UpdateTypeDTO::class,
        $updateRaw
    );
    assert($update instanceof TgApi\Types\DTO\UpdateTypeDTO);

    var_dump([
        'class' => $update::class,
        '$update' => $tgApiDTOMapper->toArray($update),
    ]);
}

echo "\nDone!\n";
