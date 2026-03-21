<?php

declare(strict_types=1);

use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\TgApiMethodsEnum;

test('SendMessageMethodDTO constructs with required fields', function () {
    $dto = new SendMessageMethodDTO(
        chatId: '12345',
        text: 'Hello world',
    );

    expect($dto->chatId)->toBe('12345');
    expect($dto->text)->toBe('Hello world');
    expect($dto->parseMode)->toBeNull();
    expect($dto->dto)->toBe(TgApiMethodsEnum::sendMessage);
});

test('GetMeMethodDTO has correct entity', function () {
    $dto = new GetMeMethodDTO();

    expect($dto->dto)->toBe(TgApiMethodsEnum::getMe);
    expect($dto->tgApiEntity())->toBe(TgApiMethodsEnum::getMe);
});
