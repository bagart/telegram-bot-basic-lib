<?php

declare(strict_types=1);

use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBot\TgApi\Methods\Enum\SendPollPropTypeEnum;

test('ChatPropTypeEnum has all expected cases', function () {
    expect(ChatPropTypeEnum::PRIVATE->value)->toBe('private');
    expect(ChatPropTypeEnum::GROUP->value)->toBe('group');
    expect(ChatPropTypeEnum::SUPERGROUP->value)->toBe('supergroup');
    expect(ChatPropTypeEnum::CHANNEL->value)->toBe('channel');
});

test('ChatPropTypeEnum can be created from value', function () {
    expect(ChatPropTypeEnum::from('private'))->toBe(ChatPropTypeEnum::PRIVATE);
    expect(ChatPropTypeEnum::tryFrom('invalid'))->toBeNull();
});

test('SendPollPropTypeEnum has expected cases', function () {
    expect(SendPollPropTypeEnum::QUIZ->value)->toBe('quiz');
    expect(SendPollPropTypeEnum::REGULAR->value)->toBe('regular');
});
