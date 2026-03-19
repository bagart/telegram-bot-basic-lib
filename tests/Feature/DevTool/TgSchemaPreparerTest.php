<?php

declare(strict_types=1);

use BAGArt\TelegramBotBasic\DevTool\TgSchemaPreparer;
use Illuminate\Support\Facades\Process;

test('prepare runs node script', function () {
    Process::fake([
        'node*' => Process::result(
            output: "schema output\n",
            exitCode: 0,
        ),
    ]);

    $preparer = new TgSchemaPreparer('/custom/script.js');
    $result = $preparer->prepare('/tmp/schema.json');

    expect(trim($result))->toBe('schema output');
});

test('prepare throws on failure', function () {
    Process::fake([
        'node*' => Process::result(
            output: '',
            errorOutput: 'node error',
            exitCode: 1,
        ),
    ]);

    $preparer = new TgSchemaPreparer('/custom/script.js');

    $preparer->prepare('/tmp/schema.json');
})->throws(RuntimeException::class);

test('constructor uses default script path', function () {
    $preparer = new TgSchemaPreparer();

    expect($preparer->javaScript)->toEndWith('json-schema-updater.js');
});
