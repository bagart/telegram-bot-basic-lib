<?php

declare(strict_types=1);

use BAGArt\TelegramBotBasic\Commands\Traits\TokenResolverTrait;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    $this->output = new OutputStyle(new ArrayInput([]), new BufferedOutput());

    $this->command = new class () extends Command {
        use TokenResolverTrait;

        protected $signature = 'test:token {--token=}';

        protected $description = 'Test command';

        public function testResolveToken(): ?string
        {
            return $this->resolveToken();
        }
    };

    $this->command->setOutput($this->output);
});

function makeInput(?string $token): ArrayInput
{
    return new ArrayInput(
        ['--token' => $token],
        new InputDefinition([
            new InputOption('token', null, InputOption::VALUE_OPTIONAL, '', null),
        ]),
    );
}

test('valid token format with numbers and alphanumeric hash', function () {
    $this->command->setInput(makeInput('123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11'));
    $token = $this->command->testResolveToken();

    expect($token)->toBe('123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
});

test('valid token with underscores and dashes', function () {
    $this->command->setInput(makeInput('987654321:abc_def-123_ABC'));
    $token = $this->command->testResolveToken();

    expect($token)->toBe('987654321:abc_def-123_ABC');
});

test('invalid token without colon returns null', function () {
    $this->command->setInput(makeInput('invalidtoken'));
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});

test('invalid token with only numbers returns null', function () {
    $this->command->setInput(makeInput('123456'));
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});

test('invalid token with special chars returns null', function () {
    $this->command->setInput(makeInput('123456:ABC@DEF!'));
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});

test('invalid token with spaces returns null', function () {
    $this->command->setInput(makeInput('123456:ABC DEF'));
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});

test('empty string is invalid', function () {
    $this->command->setInput(makeInput(''));
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});

test('missing token returns null', function () {
    $this->command->setInput(makeInput(null));
    $token = $this->command->testResolveToken();

    expect($token)->toBeNull();
});
