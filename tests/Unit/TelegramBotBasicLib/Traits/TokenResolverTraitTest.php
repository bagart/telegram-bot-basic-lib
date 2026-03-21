<?php

declare(strict_types=1);

test('valid token format with numbers and alphanumeric hash', function () {
    $token = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
    expect(preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token))->toBe(1);
});

test('valid token with underscores and dashes', function () {
    $token = '123:ABC_def-GHI_123-xyz';
    expect(preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token))->toBe(1);
});

test('invalid token without colon', function () {
    $token = '123456ABCDEF';
    expect(preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token))->toBe(0);
});

test('invalid token with only numbers', function () {
    $token = '123456';
    expect(preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token))->toBe(0);
});

test('invalid token with special chars', function () {
    $token = '123:abc!@#$';
    expect(preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token))->toBe(0);
});

test('invalid token with spaces', function () {
    $token = '123:abc def';
    expect(preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token))->toBe(0);
});

test('empty string is invalid', function () {
    expect(preg_match('/^\d+:[A-Za-z0-9_-]+$/', ''))->toBe(0);
});
