<?php

declare(strict_types=1);

use BAGArt\TelegramBot\ApiCommunication\ClientServices\TgRetryPolicy;
use RuntimeException;

test('should not retry when max attempts reached', function () {
    $policy = new TgRetryPolicy();

    expect($policy->shouldRetry('sendMessage', 3, new RuntimeException('error')))->toBeFalse();
});

test('should not retry when no error provided', function () {
    $policy = new TgRetryPolicy();

    expect($policy->shouldRetry('sendMessage', 1, null))->toBeFalse();
});

test('should retry on timeout', function () {
    $policy = new TgRetryPolicy();

    expect($policy->shouldRetry('sendMessage', 1, new RuntimeException('Timed out')))->toBeTrue();
});

test('should retry on cURL error 28', function () {
    $policy = new TgRetryPolicy();

    expect($policy->shouldRetry('sendMessage', 1, new RuntimeException('cURL error 28: timeout')))->toBeTrue();
});

test('should not retry on rate limit message', function () {
    $policy = new TgRetryPolicy();

    expect($policy->shouldRetry('sendMessage', 1, new RuntimeException('rate limit exceeded')))->toBeFalse();
});

test('should retry on 429 status', function () {
    $policy = new TgRetryPolicy();

    expect($policy->shouldRetry('sendMessage', 1, new RuntimeException('HTTP 429')))->toBeTrue();
});

test('should not retry on generic error', function () {
    $policy = new TgRetryPolicy();

    expect($policy->shouldRetry('sendMessage', 1, new RuntimeException('some error')))->toBeFalse();
});

test('getDelay returns exponential backoff', function () {
    $policy = new TgRetryPolicy();

    expect($policy->getDelay(1))->toBe(1);
    expect($policy->getDelay(2))->toBe(2);
    expect($policy->getDelay(3))->toBe(4);
});

test('getMaxAttempts returns 3', function () {
    $policy = new TgRetryPolicy();

    expect($policy->getMaxAttempts())->toBe(3);
});
