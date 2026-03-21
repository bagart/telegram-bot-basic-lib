<?php

declare(strict_types=1);

use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\TgApiTypesEnum;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;

test('UserTypeDTO constructs and has metadata', function () {
    $dto = new UserTypeDTO(
        id: '123',
        isBot: false,
        firstName: 'John',
        username: 'johndoe',
    );

    expect($dto->id)->toBe('123');
    expect($dto->isBot)->toBeFalse();
    expect($dto->firstName)->toBe('John');
    expect($dto->username)->toBe('johndoe');
    expect($dto->dto)->toBe(TgApiTypesEnum::User);
});

test('ChatTypeDTO has property metas', function () {
    $dto = new ChatTypeDTO(
        id: '456',
        type: ChatPropTypeEnum::GROUP,
        title: 'Test Group',
    );

    $metas = ChatTypeDTO::tgPropertyMetas();
    expect($metas)->toHaveKey('id');
    expect($metas)->toHaveKey('type');
    expect($metas)->toHaveKey('title');
    expect($dto->dto)->toBe(TgApiTypesEnum::Chat);
});
