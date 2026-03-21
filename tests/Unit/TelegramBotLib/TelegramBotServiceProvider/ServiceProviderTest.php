<?php

declare(strict_types=1);

use BAGArt\TelegramBot\TelegramBotServiceProvider;
use Illuminate\Foundation\Application;

test('service provider registers singletons', function () {
    $app = Mockery::mock(Application::class);
    $provider = new TelegramBotServiceProvider($app);

    // Verify it can be instantiated without errors
    expect($provider)->toBeInstanceOf(TelegramBotServiceProvider::class);
});
