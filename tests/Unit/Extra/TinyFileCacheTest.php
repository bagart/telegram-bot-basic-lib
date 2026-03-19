<?php

declare(strict_types=1);

use BAGArt\TelegramBotBasic\Extra\TinyFileCache;

beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir().'/tiny_cache_test_'.uniqid();
    $this->cache = new TinyFileCache($this->cacheDir);
});

afterEach(function () {
    if (is_dir($this->cacheDir)) {
        $this->cache->clear();
        @rmdir($this->cacheDir);
    }
});

test('set and get value', function () {
    $this->cache->set('my_key', 'my_value');

    expect($this->cache->get('my_key'))->toBe('my_value');
});

test('get returns default for missing key', function () {
    expect($this->cache->get('missing', 'fallback'))->toBe('fallback');
});

test('get returns null for missing key without default', function () {
    expect($this->cache->get('missing'))->toBeNull();
});

test('has returns true for existing key', function () {
    $this->cache->set('exists', 'value');

    expect($this->cache->has('exists'))->toBeTrue();
});

test('has returns false for missing key', function () {
    expect($this->cache->has('nonexistent'))->toBeFalse();
});

test('delete removes key', function () {
    $this->cache->set('to_delete', 'value');
    expect($this->cache->has('to_delete'))->toBeTrue();

    $this->cache->delete('to_delete');

    expect($this->cache->has('to_delete'))->toBeFalse();
    expect($this->cache->get('to_delete'))->toBeNull();
});

test('delete returns true for non-existent key', function () {
    expect($this->cache->delete('ghost'))->toBeTrue();
});

test('set overwrites existing value', function () {
    $this->cache->set('key', 'old');
    $this->cache->set('key', 'new');

    expect($this->cache->get('key'))->toBe('new');
});

test('set stores array value', function () {
    $data = ['a' => 1, 'b' => [2, 3]];
    $this->cache->set('arr', $data);

    expect($this->cache->get('arr'))->toBe($data);
});

test('set stores integer value', function () {
    $this->cache->set('num', 42);

    expect($this->cache->get('num'))->toBe(42);
});

test('set stores boolean value', function () {
    $this->cache->set('bool_true', true);
    $this->cache->set('bool_false', false);

    expect($this->cache->get('bool_true'))->toBeTrue();
    expect($this->cache->get('bool_false'))->toBeFalse();
});

test('put is alias for set', function () {
    $this->cache->put('alias_key', 'alias_value');

    expect($this->cache->get('alias_key'))->toBe('alias_value');
});

test('clear removes all keys', function () {
    $this->cache->set('key1', 'val1');
    $this->cache->set('key2', 'val2');
    $this->cache->set('key3', 'val3');

    $this->cache->clear();

    expect($this->cache->get('key1'))->toBeNull();
    expect($this->cache->get('key2'))->toBeNull();
    expect($this->cache->get('key3'))->toBeNull();
});

test('expired value returns default', function () {
    $this->cache->set('expiring', 'value', 1);
    sleep(2);

    expect($this->cache->get('expiring'))->toBeNull();
    expect($this->cache->get('expiring', 'default'))->toBe('default');
});

test('has returns false for expired key', function () {
    $this->cache->set('will_expire', 'value', 1);
    sleep(2);

    expect($this->cache->has('will_expire'))->toBeFalse();
});

test('increment creates new key', function () {
    $result = $this->cache->increment('counter');

    expect($result)->toBe(1);
});

test('increment increments existing key', function () {
    $this->cache->set('counter', 5);
    $result = $this->cache->increment('counter');

    expect($result)->toBe(6);
});

test('increment with custom offset', function () {
    $this->cache->set('counter', 10);
    $result = $this->cache->increment('counter', 5);

    expect($result)->toBe(15);
});

test('increment with custom initial value', function () {
    $result = $this->cache->increment('new_counter', 10);

    expect($result)->toBe(10);
});

test('getMultiple returns empty array', function () {
    $this->cache->set('a', 1);
    $this->cache->set('b', 2);

    expect($this->cache->getMultiple(['a', 'b']))->toBe([]);
});

test('setMultiple returns true', function () {
    expect($this->cache->setMultiple(['a' => 1, 'b' => 2]))->toBeTrue();
});

test('deleteMultiple returns true', function () {
    expect($this->cache->deleteMultiple(['a', 'b']))->toBeTrue();
});

test('clear on empty cache returns true', function () {
    expect($this->cache->clear())->toBeTrue();
});
