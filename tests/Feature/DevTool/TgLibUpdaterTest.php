<?php

declare(strict_types=1);

use BAGArt\TelegramBotBasic\DevTool\TgLibUpdater;
use Illuminate\Support\Facades\Process;

test('update runs npm command', function () {
    Process::fake([
        'npm update*' => Process::result(
            output: "updated successfully\n",
            exitCode: 0,
        ),
    ]);

    $updater = new TgLibUpdater();
    $result = $updater->update();

    Process::assertRan('npm update @grom.js/bot-api-spec');
    expect(trim($result))->toBe('updated successfully');
});

test('update throws on failure', function () {
    Process::fake([
        'npm update*' => Process::result(
            output: '',
            errorOutput: 'npm error',
            exitCode: 1,
        ),
    ]);

    $updater = new TgLibUpdater();

    $updater->update();
})->throws(RuntimeException::class);
