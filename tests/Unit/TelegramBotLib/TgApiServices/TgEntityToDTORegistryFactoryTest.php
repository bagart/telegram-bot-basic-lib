<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Services\TgEntityToDTORegistryFactory;
use BAGArt\TelegramBot\Services\TgEntityToDTORegistry;
use BAGArt\TelegramBot\TgApi\TgApiEntityScopeEnum;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->logger = new TgBotLogWrapper(Mockery::mock(LoggerInterface::class));
    $this->factory = new TgEntityToDTORegistryFactory($this->logger);
});

afterEach(function () {
    Mockery::close();
});

test('default creates registry with all entities', function () {
    $registry = $this->factory->default();

    expect($registry)->toBeInstanceOf(TgEntityToDTORegistry::class);

    // Should be able to find common types
    $userClass = $registry->getDTO('User');
    expect($userClass)->toBe(UserTypeDTO::class);

    $chatClass = $registry->getDTO('Chat');
    expect($chatClass)->toBe(ChatTypeDTO::class);
});

test('default creates registry with methods too', function () {
    $registry = $this->factory->default();

    $sendMessage = $registry->getDTO('sendMessage');
    expect($sendMessage)->toContain('SendMessageMethodDTO');
});
