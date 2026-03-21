<?php

declare(strict_types=1);

use BAGArt\TelegramBot\ApiCommunication\ClientServices\TgRateLimiter;
use BAGArt\TelegramBot\Wrappers\TgBotCacheWrapper;
use Psr\SimpleCache\CacheInterface;

beforeEach(function () {
    $this->cacheInterface = Mockery::mock(CacheInterface::class);
    $this->cache = new TgBotCacheWrapper($this->cacheInterface);
    $this->limiter = new TgRateLimiter($this->cache);
});

afterEach(function () {
    TgBotCacheWrapper::$initCache = null;
    Mockery::close();
});

test('acquire returns true when under limit', function () {
    $this->cacheInterface->shouldReceive('get')->with(Mockery::type('string'), 0)->andReturn(0);
    $this->cacheInterface->shouldReceive('increment')->andReturn(1);
    $this->cacheInterface->shouldReceive('set')->andReturn(true);

    expect($this->limiter->acquire('test_key'))->toBeTrue();
});

test('acquire returns false when at limit', function () {
    $this->cacheInterface->shouldReceive('get')->with(Mockery::type('string'), 0)->andReturn(50);

    expect($this->limiter->acquire('test_key'))->toBeFalse();
});

test('available returns remaining capacity', function () {
    $this->cacheInterface->shouldReceive('get')->with(Mockery::type('string'), 0)->andReturn(30);

    expect($this->limiter->available('test_key'))->toBe(20);
});

test('available returns zero when at limit', function () {
    $this->cacheInterface->shouldReceive('get')->with(Mockery::type('string'), 0)->andReturn(50);

    expect($this->limiter->available('test_key'))->toBe(0);
});

test('reset clears the rate limit', function () {
    $this->cacheInterface->shouldReceive('delete')->once()->andReturn(true);

    $this->limiter->reset('test_key');
});
