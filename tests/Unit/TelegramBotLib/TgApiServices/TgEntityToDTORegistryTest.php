<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Services\TgEntityToDTORegistry;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\TgApiTypesEnum;
use BAGArt\TelegramBot\TgApi\TgApiEntityScopeEnum;
use BAGArt\TelegramBot\Exceptions\TgUnregisteredEntityNameException;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->logger = new TgBotLogWrapper(Mockery::mock(LoggerInterface::class));
    $this->registry = new TgEntityToDTORegistry($this->logger);
});

afterEach(function () {
    Mockery::close();
});

test('register and getDTO by entity name string', function () {
    $this->registry->register(UserTypeDTO::class);

    $result = $this->registry->getDTO('User');

    expect($result)->toBe(UserTypeDTO::class);
});

test('register and getDTO by enum', function () {
    $this->registry->register(UserTypeDTO::class);

    $result = $this->registry->getDTO(TgApiTypesEnum::User);

    expect($result)->toBe(UserTypeDTO::class);
});

test('getDTO throws for unregistered entity', function () {
    $this->registry->getDTO('NonExistent');
})->throws(TgUnregisteredEntityNameException::class);

test('getDTO with scope finds correct entity', function () {
    $this->registry->register(UserTypeDTO::class);

    $result = $this->registry->getDTO(
        'User',
        TgApiEntityScopeEnum::Type,
    );

    expect($result)->toBe(UserTypeDTO::class);
});

test('getDTO with wrong scope throws', function () {
    $this->registry->register(UserTypeDTO::class);

    $this->registry->getDTO('User', TgApiEntityScopeEnum::Method);
})->throws(TgUnregisteredEntityNameException::class);

test('overwrite replaces existing registration', function () {
    $this->registry->register(UserTypeDTO::class);
    $this->registry->register(UserTypeDTO::class, overwrite: true);

    $result = $this->registry->getDTO('User');
    expect($result)->toBe(UserTypeDTO::class);
});
