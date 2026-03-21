<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Services\TgApiProperty;

test('creates property with all fields', function () {
    $prop = new TgApiProperty(
        property: 'messageId',
        tgPropName: 'message_id',
        types: ['int'],
        tgTypes: [['type' => 'int32']],
        nullable: false,
        required: true,
    );

    expect($prop->property)->toBe('messageId');
    expect($prop->tgPropName)->toBe('message_id');
    expect($prop->types)->toBe(['int']);
    expect($prop->nullable)->toBeFalse();
    expect($prop->required)->toBeTrue();
});

test('creates nullable optional property', function () {
    $prop = new TgApiProperty(
        property: 'username',
        tgPropName: 'username',
        types: ['string'],
        tgTypes: [['type' => 'str']],
        nullable: true,
        required: false,
    );

    expect($prop->nullable)->toBeTrue();
    expect($prop->required)->toBeFalse();
});
