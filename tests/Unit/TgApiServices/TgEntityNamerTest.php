<?php

declare(strict_types=1);

use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBot\Services\TgEntityNamer;

beforeEach(function () {
    $this->namer = new TgEntityNamer();
});

test('names user by username', function () {
    $user = new UserTypeDTO(
        id: '123',
        isBot: false,
        firstName: 'John',
        lastName: 'Doe',
        username: 'johndoe',
    );

    expect($this->namer->name($user))->toBe('@johndoe');
});

test('names bot user by username', function () {
    $user = new UserTypeDTO(
        id: '456',
        isBot: true,
        firstName: 'MyBot',
        username: 'mytestbot',
    );

    expect($this->namer->name($user))->toBe('@mytestbot');
});

test('names user without username by first name', function () {
    $user = new UserTypeDTO(
        id: '123',
        isBot: false,
        firstName: 'John',
        lastName: '',
        username: '',
    );

    expect($this->namer->name($user))->toBe('John');
});

test('names user without username by full name', function () {
    $user = new UserTypeDTO(
        id: '123',
        isBot: false,
        firstName: 'John',
        lastName: 'Doe',
        username: '',
    );

    expect($this->namer->name($user))->toBe('John Doe');
});

test('names bot without username with emoji', function () {
    $user = new UserTypeDTO(
        id: '456',
        isBot: true,
        firstName: 'TestBot',
        lastName: '',
        username: '',
    );

    expect($this->namer->name($user))->toBe('🤖TestBot');
});

test('names user without username or name by id', function () {
    $user = new UserTypeDTO(
        id: '789',
        isBot: false,
        firstName: '',
        lastName: '',
        username: '',
    );

    expect($this->namer->name($user))->toBe('[789]');
});

test('names bot without username or name by id with emoji', function () {
    $user = new UserTypeDTO(
        id: '789',
        isBot: true,
        firstName: '',
        lastName: '',
        username: '',
    );

    expect($this->namer->name($user))->toBe('[🤖789]');
});

test('names chat by username', function () {
    $chat = new ChatTypeDTO(
        id: '100',
        type: ChatPropTypeEnum::PRIVATE,
        username: 'testchat',
        firstName: 'Ignored',
    );

    expect($this->namer->name($chat))->toBe('@testchat');
});

test('names chat by title', function () {
    $chat = new ChatTypeDTO(
        id: '200',
        type: ChatPropTypeEnum::GROUP,
        title: 'My Group Chat',
        username: '',
    );

    expect($this->namer->name($chat))->toBe('My Group Chat');
});

test('names chat without username by first name', function () {
    $chat = new ChatTypeDTO(
        id: '300',
        type: ChatPropTypeEnum::PRIVATE,
        username: '',
        firstName: 'Alice',
        lastName: 'Smith',
    );

    expect($this->namer->name($chat))->toBe('Alice Smith');
});

test('names chat without username by id', function () {
    $chat = new ChatTypeDTO(
        id: '400',
        type: ChatPropTypeEnum::GROUP,
        username: '',
        firstName: '',
        lastName: '',
    );

    expect($this->namer->name($chat))->toBe('[400]');
});
