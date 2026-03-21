<?php

declare(strict_types=1);

use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBot\Services\TgEntityNamer;

test('names user by username', function () {
    $user = new UserTypeDTO(
        id: '123',
        isBot: false,
        firstName: 'John',
        username: 'johndoe',
    );

    $namer = new TgEntityNamer();
    expect($namer->name($user))->toBe('@johndoe');
});

test('names bot user with emoji when no username', function () {
    $user = new UserTypeDTO(
        id: '456',
        isBot: true,
        firstName: 'MyBot',
        username: '',
    );

    $namer = new TgEntityNamer();
    expect($namer->name($user))->toBe('🤖MyBot');
});

test('names chat by username', function () {
    $chat = new ChatTypeDTO(
        id: '100',
        type: ChatPropTypeEnum::PRIVATE,
        username: 'testchat',
    );

    $namer = new TgEntityNamer();
    expect($namer->name($chat))->toBe('@testchat');
});

test('names chat by title', function () {
    $chat = new ChatTypeDTO(
        id: '200',
        type: ChatPropTypeEnum::GROUP,
        title: 'Group Chat',
        username: '',
    );

    $namer = new TgEntityNamer();
    expect($namer->name($chat))->toBe('Group Chat');
});
