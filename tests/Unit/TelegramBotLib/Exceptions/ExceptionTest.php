<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Exceptions\TgUnregisteredEntityNameException;
use BAGArt\TelegramBot\Exceptions\TgUnexpectedDataFormatException;
use BAGArt\TelegramBot\Exceptions\TgBotTechnicalException;

test('TgUnregisteredEntityNameException stores entity name', function () {
    $e = new TgUnregisteredEntityNameException('sendMessage');

    expect($e->tgEntityName)->toBe('sendMessage');
    expect($e->tgEntityScope)->toBe('*');
    expect($e->getMessage())->toContain('sendMessage');
});

test('TgUnregisteredEntityNameException stores scope', function () {
    $e = new TgUnregisteredEntityNameException('User', 'Type');

    expect($e->tgEntityName)->toBe('User');
    expect($e->tgEntityScope)->toBe('Type');
});

test('TgUnexpectedApiReturnException stores data', function () {
    $e = new TgUnexpectedDataFormatException('sendMessage', 'bool', 'not_bool');

    expect($e->tgEntityName)->toBe('sendMessage');
    expect($e->expectType)->toBe('bool');
    expect($e->response)->toBe('not_bool');
    expect($e->getCode())->toBe(400);
});

test('TgBotTechnicalException formats message', function () {
    $e = new TgBotTechnicalException('getMe', 'connection failed');

    expect($e->tgEntityName)->toBe('getMe');
    expect($e->getMessage())->toContain('connection failed');
    expect($e->getCode())->toBe(500);
});
