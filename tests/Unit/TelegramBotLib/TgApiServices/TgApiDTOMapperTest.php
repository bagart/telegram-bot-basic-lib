<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Services\TgApiDTOMapper;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use BAGArt\TelegramBot\Contracts\Services\TgApiDTORegistryContract;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->logger = new TgBotLogWrapper(Mockery::mock(LoggerInterface::class));
    $this->registry = Mockery::mock(TgApiDTORegistryContract::class);
    $this->mapper = new TgApiDTOMapper($this->logger, $this->registry);
});

afterEach(function () {
    Mockery::close();
});

test('toArray converts simple DTO to array', function () {
    $dto = new UserTypeDTO(
        id: '123',
        isBot: false,
        firstName: 'John',
        username: 'johndoe',
    );

    $result = $this->mapper->toArray($dto);

    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('123');
    expect($result['is_bot'])->toBeFalse();
    expect($result['first_name'])->toBe('John');
    expect($result['username'])->toBe('johndoe');
});

test('toArray skips null non-required fields', function () {
    $dto = new UserTypeDTO(
        id: '123',
        isBot: false,
        firstName: 'John',
    );

    $result = $this->mapper->toArray($dto);

    expect($result)->not->toHaveKey('username');
    expect($result)->not->toHaveKey('last_name');
});

test('toArray handles nested DTO', function () {
    $chat = new ChatTypeDTO(
        id: '456',
        type: ChatPropTypeEnum::PRIVATE,
        firstName: 'Test',
    );

    $result = $this->mapper->toArray($chat);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('type');
    expect($result['type'])->toBe('private');
});

test('toArray handles enum values', function () {
    $chat = new ChatTypeDTO(
        id: '456',
        type: ChatPropTypeEnum::GROUP,
        title: 'Group',
    );

    $result = $this->mapper->toArray($chat);

    expect($result['type'])->toBe('group');
});

test('fromArray with DTO class string', function () {
    $data = [
        'id' => '789',
        'is_bot' => true,
        'first_name' => 'BotUser',
        'username' => 'testbot',
    ];

    $dto = $this->mapper->fromArray(UserTypeDTO::class, $data);

    expect($dto)->toBeInstanceOf(UserTypeDTO::class);
    expect($dto->id)->toBe('789');
    expect($dto->isBot)->toBeTrue();
    expect($dto->firstName)->toBe('BotUser');
    expect($dto->username)->toBe('testbot');
});

test('fromArray handles null values', function () {
    $data = [
        'id' => '789',
        'is_bot' => false,
        'first_name' => 'User',
        'username' => null,
    ];

    $dto = $this->mapper->fromArray(UserTypeDTO::class, $data);

    expect($dto->username)->toBeNull();
});

test('fromArray warns on unexpected keys', function () {
    $psrLogger = Mockery::mock(LoggerInterface::class);
    $psrLogger->shouldReceive('warning')->once();
    $logger = new TgBotLogWrapper($psrLogger);
    $mapper = new TgApiDTOMapper($logger, $this->registry);

    $data = [
        'id' => '789',
        'is_bot' => false,
        'first_name' => 'User',
        'unknown_field' => 'value',
    ];

    $mapper->fromArray(UserTypeDTO::class, $data);
});
