<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use Psr\Log\LoggerInterface;

test('wraps logger and delegates info calls', function () {
    $inner = Mockery::mock(LoggerInterface::class);
    $inner->shouldReceive('info')->with('test message', [])->once();

    $wrapper = new TgBotLogWrapper($inner);
    $wrapper->info('test message');
});

test('adds prefix to error calls', function () {
    $inner = Mockery::mock(LoggerInterface::class);
    $inner->shouldReceive('error')->with('[!!!] error msg', [])->once();

    $wrapper = new TgBotLogWrapper($inner);
    $wrapper->error('error msg');
});

test('adds prefix to warning calls', function () {
    $inner = Mockery::mock(LoggerInterface::class);
    $inner->shouldReceive('warning')->with('[!] warn msg', [])->once();

    $wrapper = new TgBotLogWrapper($inner);
    $wrapper->warning('warn msg');
});

test('adds prefix to debug calls', function () {
    $inner = Mockery::mock(LoggerInterface::class);
    $inner->shouldReceive('debug')->with('[DBG] debug msg', [])->once();

    $wrapper = new TgBotLogWrapper($inner);
    $wrapper->debug('debug msg');
});

test('init sets static logger', function () {
    $inner = Mockery::mock(LoggerInterface::class);

    TgBotLogWrapper::init($inner);

    expect(TgBotLogWrapper::$initLogger)->toBe($inner);

    TgBotLogWrapper::$initLogger = null;
});

test('uses init logger when no logger passed', function () {
    $inner = Mockery::mock(LoggerInterface::class);
    $inner->shouldReceive('info')->with('hello', [])->once();

    TgBotLogWrapper::init($inner);
    $wrapper = new TgBotLogWrapper();
    $wrapper->info('hello');

    TgBotLogWrapper::$initLogger = null;
});

afterEach(function () {
    TgBotLogWrapper::$initLogger = null;
    Mockery::close();
});
