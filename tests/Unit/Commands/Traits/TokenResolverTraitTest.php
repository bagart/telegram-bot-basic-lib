<?php

declare(strict_types=1);

use BAGArt\TelegramBotBasic\Commands\Traits\TokenResolverTrait;
use Illuminate\Console\Command;

beforeEach(function () {
    $this->command = new class () extends Command {
        use TokenResolverTrait;

        protected $signature = 'test:token {token?}';

        protected $description = 'Test command';

        public function testResolveToken(): ?string
        {
            return $this->resolveToken();
        }
    };
});

test('valid token format with numbers and alphanumeric hash', function () {
    $this->command->setInput('123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
    $token = $this->command->testResolveToken();

    expect($token)->toBe('123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
});

test('valid token with underscores and dashes', function () {
    $this->command->setInput('987654321:abc_def-123_ABC');
    $token = $this->command->testResolveToken();

    expect($token)->toBe('987654321:abc_def-123_ABC');
});

test('invalid token without colon returns null', function () {
    $this->command->setInput('invalidtoken');
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});

test('invalid token with only numbers returns null', function () {
    $this->command->setInput('123456');
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});

test('invalid token with special chars returns null', function () {
    $this->command->setInput('123456:ABC@DEF!');
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});

test('invalid token with spaces returns null', function () {
    $this->command->setInput('123456:ABC DEF');
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});

test('empty string is invalid', function () {
    $this->command->setInput('');
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});
