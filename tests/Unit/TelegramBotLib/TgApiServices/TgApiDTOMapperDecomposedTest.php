<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Services\TgApiDTOMapper;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use BAGArt\TelegramBot\Contracts\Services\TgApiDTORegistryContract;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    $this->logger = new TgBotLogWrapper(Mockery::mock(LoggerInterface::class));
    $this->registry = Mockery::mock(TgApiDTORegistryContract::class);
    $this->mapper = new TgApiDTOMapper($this->logger, $this->registry);
});

afterEach(function () {
    Mockery::close();
});

test('categorizeTypes separates DTO/Enum into priority1', function () {
    $result = $this->mapper->categorizeTypes([
        UserTypeDTO::class,
        'string',
        'int',
    ]);

    expect($result)->toContain(UserTypeDTO::class);
    expect($result)->toContain('string');
    expect($result)->toContain('int');
    expect(array_search(UserTypeDTO::class, $result, true))->toBeLessThan(array_search('string', $result, true));
});

test('categorizeTypes puts arrays into priority3', function () {
    $result = $this->mapper->categorizeTypes([
        ['string'],
        'bool',
    ]);

    expect($result)->toContain('bool');
    expect($result)->toContain(['string']);
    expect(array_search('bool', $result, true))->toBeLessThan(array_search(['string'], $result, true));
});

test('categorizeTypes puts Enum and DTO suffix into priority1', function () {
    $result = $this->mapper->categorizeTypes([
        'SomeEnum',
        'MyDTO',
    ]);

    expect($result)->toContain('SomeEnum');
    expect($result)->toContain('MyDTO');
    expect(array_search('SomeEnum', $result, true))->toBeLessThan(array_search('MyDTO', $result, true));
});

test('matchPrimitiveType validates int', function () {
    expect($this->mapper->matchPrimitiveType('int', 42))->toBeTrue();
    expect($this->mapper->matchPrimitiveType('int', '42'))->toBeFalse();
    expect($this->mapper->matchPrimitiveType('int', true))->toBeFalse();
});

test('matchPrimitiveType validates bool', function () {
    expect($this->mapper->matchPrimitiveType('bool', true))->toBeTrue();
    expect($this->mapper->matchPrimitiveType('bool', false))->toBeTrue();
    expect($this->mapper->matchPrimitiveType('bool', 1))->toBeFalse();
});

test('matchPrimitiveType validates string', function () {
    expect($this->mapper->matchPrimitiveType('string', 'hello'))->toBeTrue();
    expect($this->mapper->matchPrimitiveType('string', 123))->toBeFalse();
});

test('matchPrimitiveType validates null', function () {
    expect($this->mapper->matchPrimitiveType('null', null))->toBeTrue();
    expect($this->mapper->matchPrimitiveType('null', 0))->toBeFalse();
});

test('matchPrimitiveType validates float', function () {
    expect($this->mapper->matchPrimitiveType('float', 1.5))->toBeTrue();
    expect($this->mapper->matchPrimitiveType('float', 1))->toBeFalse();
});

test('matchPrimitiveType returns false for unknown type', function () {
    expect($this->mapper->matchPrimitiveType('unknown', 'val'))->toBeFalse();
});

test('toArray converts enum to its value', function () {
    $dto = new \BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO(
        id: '1',
        type: \BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum::GROUP,
        title: 'Test',
    );

    $result = $this->mapper->toArray($dto);
    expect($result['type'])->toBe('group');
});

test('toArray includes required null fields', function () {
    $dto = new UserTypeDTO(
        id: '1',
        isBot: false,
        firstName: 'Test',
    );

    $result = $this->mapper->toArray($dto);
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('first_name');
    expect($result)->not->toHaveKey('username');
});
