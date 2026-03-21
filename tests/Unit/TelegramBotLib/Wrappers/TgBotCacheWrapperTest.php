<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Wrappers\TgBotCacheWrapper;
use Psr\SimpleCache\CacheInterface;

test('wraps cache and delegates get', function () {
    $inner = Mockery::mock(CacheInterface::class);
    $inner->shouldReceive('get')->with('key', null)->andReturn('value');

    $wrapper = new TgBotCacheWrapper($inner);
    expect($wrapper->get('key'))->toBe('value');
});

test('wraps cache and delegates set', function () {
    $inner = Mockery::mock(CacheInterface::class);
    $inner->shouldReceive('set')->with('key', 'val', null)->andReturnTrue();

    $wrapper = new TgBotCacheWrapper($inner);
    expect($wrapper->set('key', 'val'))->toBeTrue();
});

test('wraps cache and delegates has', function () {
    $inner = Mockery::mock(CacheInterface::class);
    $inner->shouldReceive('has')->with('key')->andReturnTrue();

    $wrapper = new TgBotCacheWrapper($inner);
    expect($wrapper->has('key'))->toBeTrue();
});

test('wraps cache and delegates delete', function () {
    $inner = Mockery::mock(CacheInterface::class);
    $inner->shouldReceive('delete')->with('key')->andReturnTrue();

    $wrapper = new TgBotCacheWrapper($inner);
    expect($wrapper->delete('key'))->toBeTrue();
});

test('wraps cache and delegates clear', function () {
    $inner = Mockery::mock(CacheInterface::class);
    $inner->shouldReceive('clear')->andReturnTrue();

    $wrapper = new TgBotCacheWrapper($inner);
    expect($wrapper->clear())->toBeTrue();
});

test('init sets static cache', function () {
    $inner = Mockery::mock(CacheInterface::class);

    TgBotCacheWrapper::init($inner);

    expect(TgBotCacheWrapper::$initCache)->toBe($inner);

    TgBotCacheWrapper::$initCache = null;
});

test('throws when no cache provided and not initialized', function () {
    TgBotCacheWrapper::$initCache = null;
    new TgBotCacheWrapper();
})->throws(RuntimeException::class);

afterEach(function () {
    TgBotCacheWrapper::$initCache = null;
    Mockery::close();
});
