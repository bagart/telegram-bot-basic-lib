<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Contracts\Services\TgApiDTOMapperContract;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBot\Services\TgApiResponse;
use BAGArt\TelegramBot\Services\TgEntityNamer;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use BAGArt\TelegramBotBasic\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->tgDTOClient = Mockery::mock(TgBotApiDTOClientContract::class);
    $this->tgApiDTOMapper = Mockery::mock(TgApiDTOMapperContract::class);
    $this->psrLogger = Mockery::mock(LoggerInterface::class);
    $this->logger = new TgBotLogWrapper($this->psrLogger);
    $this->tgEntityNamer = new TgEntityNamer();

    $this->app->instance(TgBotApiDTOClientContract::class, $this->tgDTOClient);
    $this->app->instance(TgApiDTOMapperContract::class, $this->tgApiDTOMapper);
    $this->app->instance(TgBotLogWrapper::class, $this->logger);
    $this->app->instance(TgEntityNamer::class, $this->tgEntityNamer);

    Route::post('tg/{token}', [WebhookController::class, 'handle']);

    $this->token = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
});

afterEach(function () {
    Mockery::close();
    TgBotLogWrapper::$initLogger = null;
});

function createChat(string $id = '999', string $username = 'testchat'): ChatTypeDTO
{
    return new ChatTypeDTO(
        id: $id,
        type: ChatPropTypeEnum::PRIVATE,
        username: $username,
        firstName: 'Test',
    );
}

function createUser(string $id = '111', string $username = 'testuser'): UserTypeDTO
{
    return new UserTypeDTO(
        id: $id,
        isBot: false,
        firstName: 'Test',
        username: $username,
    );
}

function createMessage(string $text = 'Hello', ?ChatTypeDTO $chat = null, ?UserTypeDTO $from = null): MessageTypeDTO
{
    return new MessageTypeDTO(
        messageId: 1,
        date: time(),
        chat: $chat ?? createChat(),
        from: $from ?? createUser(),
        text: $text,
    );
}

function createUpdate(?MessageTypeDTO $message = null): UpdateTypeDTO
{
    return new UpdateTypeDTO(
        updateId: 123,
        message: $message,
    );
}

test('webhook returns ok true for message update', function () {
    $chat = createChat();
    $from = createUser();
    $message = createMessage('Hello bot', $chat, $from);
    $update = createUpdate($message);

    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->with(UpdateTypeDTO::class, Mockery::type('array'))
        ->once()
        ->andReturn($update);

    $botUser = new UserTypeDTO(
        id: '555',
        isBot: true,
        firstName: 'EchoBot',
        username: 'echobot',
    );

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], $botUser));

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], null));

    $this->psrLogger->shouldReceive('info')->once();

    $response = $this->postJson("/tg/{$this->token}", [
        'update_id' => 123,
        'message' => [
            'message_id' => 1,
            'date' => time(),
            'chat' => ['id' => 999, 'type' => 'private', 'username' => 'testchat', 'first_name' => 'Test'],
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Test', 'username' => 'testuser'],
            'text' => 'Hello bot',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['ok' => true]);
});

test('webhook returns ok true for update without message', function () {
    $update = createUpdate(null);

    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->with(UpdateTypeDTO::class, Mockery::type('array'))
        ->once()
        ->andReturn($update);

    $response = $this->postJson("/tg/{$this->token}", [
        'update_id' => 124,
    ]);

    $response->assertOk();
    $response->assertJson(['ok' => true]);
});

test('webhook returns ok false when update parsing fails', function () {
    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->andThrow(new RuntimeException('Invalid update data'));

    $this->psrLogger->shouldReceive('error')->once();

    $response = $this->postJson("/tg/{$this->token}", [
        'invalid' => 'data',
    ]);

    $response->assertOk();
    $response->assertJson(['ok' => false]);
});

test('webhook logs echo reply error when send message fails', function () {
    $chat = createChat();
    $from = createUser();
    $message = createMessage('Hello', $chat, $from);
    $update = createUpdate($message);

    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->once()
        ->andReturn($update);

    $botUser = new UserTypeDTO(
        id: '555',
        isBot: true,
        firstName: 'EchoBot',
        username: 'echobot',
    );

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], $botUser));

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO::class))
        ->once()
        ->andThrow(new RuntimeException('API error'));

    $this->psrLogger->shouldReceive('info')->once();
    $this->psrLogger->shouldReceive('error')->once();

    $response = $this->postJson("/tg/{$this->token}", [
        'update_id' => 125,
        'message' => [
            'message_id' => 2,
            'date' => time(),
            'chat' => ['id' => 999, 'type' => 'private', 'username' => 'testchat', 'first_name' => 'Test'],
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Test', 'username' => 'testuser'],
            'text' => 'Hello',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['ok' => true]);
});

test('webhook works when message has no from user', function () {
    $chat = createChat();
    $message = createMessage('Channel post', $chat, null);
    $update = createUpdate($message);

    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->once()
        ->andReturn($update);

    $botUser = new UserTypeDTO(
        id: '555',
        isBot: true,
        firstName: 'EchoBot',
        username: 'echobot',
    );

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], $botUser));

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], null));

    $this->psrLogger->shouldReceive('info')->once();

    $response = $this->postJson("/tg/{$this->token}", [
        'update_id' => 126,
        'message' => [
            'message_id' => 3,
            'date' => time(),
            'chat' => ['id' => 888, 'type' => 'private', 'first_name' => 'Test'],
            'text' => 'Channel post',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['ok' => true]);
});

test('webhook resolves bot name and sends echo message', function () {
    $chat = new ChatTypeDTO(
        id: '777',
        type: ChatPropTypeEnum::PRIVATE,
        username: 'alice',
        firstName: 'Alice',
    );
    $from = new UserTypeDTO(
        id: '222',
        isBot: false,
        firstName: 'Alice',
        username: 'alice',
    );
    $message = createMessage('Ping', $chat, $from);
    $update = createUpdate($message);

    $this->tgApiDTOMapper
        ->shouldReceive('fromArray')
        ->once()
        ->andReturn($update);

    $botUser = new UserTypeDTO(
        id: '555',
        isBot: true,
        firstName: 'TestBot',
        username: 'mytestbot',
    );

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], $botUser));

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::on(function ($dto) {
            return $dto instanceof \BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO
                && $dto->chatId === '777'
                && $dto->text === 'echo: Ping';
        }))
        ->once()
        ->andReturn(new TgApiResponse(true, [], null));

    $this->psrLogger->shouldReceive('info')->once();

    $response = $this->postJson("/tg/{$this->token}", [
        'update_id' => 200,
        'message' => [
            'message_id' => 10,
            'date' => time(),
            'chat' => ['id' => 777, 'type' => 'private', 'username' => 'alice', 'first_name' => 'Alice'],
            'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Alice', 'username' => 'alice'],
            'text' => 'Ping',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['ok' => true]);
});
