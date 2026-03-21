<?php

declare(strict_types=1);

use BAGArt\TelegramBotBasic\Http\Controllers\WebhookController;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Contracts\Services\TgApiDTOMapperContract;
use BAGArt\TelegramBot\Services\TgEntityNamer;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->tgDTOClient = Mockery::mock(TgBotApiDTOClientContract::class);
    $this->tgApiDTOMapper = Mockery::mock(TgApiDTOMapperContract::class);
    $this->logger = new TgBotLogWrapper(Mockery::mock(LoggerInterface::class));
    $this->tgEntityNamer = new TgEntityNamer();

    $this->controller = new WebhookController(
        $this->tgDTOClient,
        $this->tgApiDTOMapper,
        $this->logger,
        $this->tgEntityNamer,
    );
});

afterEach(function () {
    Mockery::close();
});

test('parseUpdate returns UpdateTypeDTO from array', function () {
    $update = new UpdateTypeDTO(
        updateId: 100,
        message: null,
    );

    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->with(UpdateTypeDTO::class, ['update_id' => 100])
        ->once()
        ->andReturn($update);

    $result = $this->controller->parseUpdate(['update_id' => 100]);

    expect($result)->toBeInstanceOf(UpdateTypeDTO::class);
    expect($result->updateId)->toBe(100);
});

test('parseUpdate throws on invalid data', function () {
    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->andThrow(new RuntimeException('Invalid'));

    $this->controller->parseUpdate(['bad' => 'data']);
})->throws(RuntimeException::class);

test('parseUpdate returns update with message', function () {
    $chat = new ChatTypeDTO(
        id: '999',
        type: ChatPropTypeEnum::PRIVATE,
        username: 'test',
    );
    $from = new UserTypeDTO(
        id: '111',
        isBot: false,
        firstName: 'Test',
    );
    $message = new MessageTypeDTO(
        messageId: 1,
        date: time(),
        chat: $chat,
        from: $from,
        text: 'Hello',
    );
    $update = new UpdateTypeDTO(
        updateId: 200,
        message: $message,
    );

    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->once()
        ->andReturn($update);

    $result = $this->controller->parseUpdate([]);

    expect($result->message)->toBeInstanceOf(MessageTypeDTO::class);
    expect($result->message->text)->toBe('Hello');
});
