<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Services\TgApiResponse;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;

test('creates response with bool result', function () {
    $response = new TgApiResponse(
        ok: true,
        possibleResultTypes: ['bool'],
        result: true,
    );

    expect($response->ok)->toBeTrue();
    expect($response->result)->toBeTrue();
    expect($response->possibleResultTypes)->toBe(['bool']);
});

test('creates response with dto result', function () {
    $user = new UserTypeDTO(id: '1', isBot: true, firstName: 'Bot');
    $response = new TgApiResponse(
        ok: true,
        possibleResultTypes: [UserTypeDTO::class],
        result: $user,
    );

    expect($response->ok)->toBeTrue();
    expect($response->result)->toBe($user);
});
