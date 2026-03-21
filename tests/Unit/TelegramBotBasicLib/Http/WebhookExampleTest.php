<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Contracts\TgApiServices\TgApiDTOMapperContract;
use BAGArt\TelegramBot\Http\Pure\TgApiResponse;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBot\TgApiServices\TgEntityNamer;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use BAGArt\TelegramBotBasic\Http\WebhookExample;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->tgDTOClient = Mockery::mock(TgBotApiDTOClientContract::class);
    $this->tgApiDTOMapper = Mockery::mock(TgApiDTOMapperContract::class);
    $this->psrLogger = Mockery::mock(LoggerInterface::class);
    $this->logger = new TgBotLogWrapper($this->psrLogger);
    $this->tgEntityNamer = new TgEntityNamer();

    $this->webhookExample = new WebhookExample(
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

    $result = $this->webhookExample->parseUpdate(['update_id' => 100]);

    expect($result)->toBeInstanceOf(UpdateTypeDTO::class);
    expect($result->updateId)->toBe(100);
});

test('parseUpdate throws on invalid data', function () {
    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->andThrow(new RuntimeException('Invalid'));

    $this->webhookExample->parseUpdate(['bad' => 'data']);
})->throws(RuntimeException::class);

test('handle returns ok true for update without message', function () {
    $update = new UpdateTypeDTO(
        updateId: 124,
        message: null,
    );

    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->once()
        ->andReturn($update);

    $result = $this->webhookExample->handle('test-token', ['update_id' => 124]);

    expect($result)->toBe(['ok' => true]);
});

test('handle returns ok false when parsing fails', function () {
    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->andThrow(new RuntimeException('Invalid update data'));

    $this->psrLogger->shouldReceive('error')->once();

    $result = $this->webhookExample->handle('test-token', ['invalid' => 'data']);

    expect($result)->toBe(['ok' => false]);
});

test('handle processes message and sends echo reply', function () {
    $chat = new ChatTypeDTO(
        id: '999',
        type: ChatPropTypeEnum::PRIVATE,
        username: 'testchat',
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

    $botUser = new UserTypeDTO(
        id: '555',
        isBot: true,
        firstName: 'EchoBot',
    );

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with('test-token', Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], $botUser));

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with('test-token', Mockery::type(SendMessageMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], null));

    $this->psrLogger->shouldReceive('info')->once();

    $result = $this->webhookExample->handle('test-token', []);

    expect($result)->toBe(['ok' => true]);
});

test('handle logs error when send message fails', function () {
    $chat = new ChatTypeDTO(
        id: '999',
        type: ChatPropTypeEnum::PRIVATE,
    );
    $message = new MessageTypeDTO(
        messageId: 1,
        date: time(),
        chat: $chat,
        from: null,
        text: 'Hello',
    );
    $update = new UpdateTypeDTO(
        updateId: 201,
        message: $message,
    );

    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->once()
        ->andReturn($update);

    $botUser = new UserTypeDTO(
        id: '555',
        isBot: true,
        firstName: 'EchoBot',
    );

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with('test-token', Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], $botUser));

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with('test-token', Mockery::type(SendMessageMethodDTO::class))
        ->once()
        ->andThrow(new RuntimeException('API error'));

    $this->psrLogger->shouldReceive('info')->once();
    $this->psrLogger->shouldReceive('error')->once();

    $result = $this->webhookExample->handle('test-token', []);

    expect($result)->toBe(['ok' => true]);
});
