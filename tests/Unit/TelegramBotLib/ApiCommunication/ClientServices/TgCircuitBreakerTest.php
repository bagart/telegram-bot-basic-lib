<?php

declare(strict_types=1);

use BAGArt\TelegramBot\ApiCommunication\ClientServices\TgCircuitBreaker;
use BAGArt\TelegramBot\Wrappers\TgBotCacheWrapper;
use Psr\SimpleCache\CacheInterface;

beforeEach(function () {
    $this->ci = Mockery::mock(CacheInterface::class);
    $this->cache = new TgBotCacheWrapper($this->ci);
    $this->breaker = new TgCircuitBreaker($this->cache);
});

afterEach(function () {
    TgBotCacheWrapper::$initCache = null;
    Mockery::close();
});

test('canExecute returns true when no failures', function () {
    $this->ci->shouldReceive('get')->andReturn(0);
    expect($this->breaker->canExecute('sendMessage'))->toBeTrue();
});

test('canExecute returns false when threshold reached', function () {
    $this->ci->shouldReceive('get')->andReturnValues([5, time()]);
    expect($this->breaker->canExecute('sendMessage'))->toBeFalse();
});

test('canExecute returns true when recovered after timeout', function () {
    $this->ci->shouldReceive('get')->andReturnValues([5, time() - 60]);
    $this->ci->shouldReceive('delete')->andReturn(true);
    expect($this->breaker->canExecute('sendMessage'))->toBeTrue();
});

test('recordFailure stores via set', function () {
    $this->ci->shouldReceive('get')->andReturn(0);
    $this->ci->shouldReceive('set')->twice()->andReturn(true);
    $this->breaker->recordFailure('sendMessage');
});

test('recordSuccess clears via delete', function () {
    $this->ci->shouldReceive('delete')->twice()->andReturn(true);
    $this->breaker->recordSuccess('sendMessage');
});

test('reset calls flush', function () {
    $this->ci->shouldReceive('flush')->once()->andReturn(true);
    $this->breaker->reset();
});
