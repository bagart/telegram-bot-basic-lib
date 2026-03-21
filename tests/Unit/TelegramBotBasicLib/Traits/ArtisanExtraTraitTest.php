<?php

declare(strict_types=1);

use BAGArt\TelegramBotBasic\Commands\Traits\ArtisanExtraTrait;
use Illuminate\Console\Command;

beforeEach(function () {
    $this->command = new class extends Command {
        use ArtisanExtraTrait;

        protected $signature = 'test:extra';

        protected $description = 'Test command';

        public function testPrepareOptions(array $entities, array $allPossible): array
        {
            return $this->prepareOptions($entities, $allPossible);
        }
    };
});

test('returns all possible when wildcard passed', function () {
    $result = $this->command->testPrepareOptions(['*'], ['a', 'b', 'c']);

    expect($result)->toBe(['a', 'b', 'c']);
});

test('returns filtered entities', function () {
    $result = $this->command->testPrepareOptions(['a', 'c'], ['a', 'b', 'c']);

    expect($result)->toBe(['a', 'c']);
});

test('returns all possible for empty array', function () {
    $result = $this->command->testPrepareOptions([], ['x', 'y']);

    expect($result)->toBe(['x', 'y']);
});

test('returns single valid option', function () {
    $result = $this->command->testPrepareOptions(['b'], ['a', 'b', 'c']);

    expect($result)->toBe(['b']);
});

test('skips empty values', function () {
    $result = $this->command->testPrepareOptions(['a', '', 'c'], ['a', 'b', 'c']);

    expect($result)->toBe(['a', 'c']);
});

test('throws on wildcard mixed with others', function () {
    $this->command->testPrepareOptions(['*', 'a'], ['a', 'b']);
})->throws('[ERROR] Unsupported option values: * and other: [*, a]');

test('throws on invalid option', function () {
    $this->command->testPrepareOptions(['invalid'], ['a', 'b']);
})->throws('[ERROR] Unsupported option values: [invalid]');
