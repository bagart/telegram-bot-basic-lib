<?php

declare(strict_types=1);

use BAGArt\TelegramBotBasic\TelegramBotManagementServiceProvider;

test('service provider registers commands', function () {
    $provider = new TelegramBotManagementServiceProvider($this->app);

    $reflection = new ReflectionClass($provider);
    $commandsProperty = $reflection->getProperty('commands');
    $commandsProperty->setAccessible(true);
    $commands = $commandsProperty->getValue($provider);

    expect($commands)->toContain(\BAGArt\TelegramBotBasic\Commands\WebhookCommand::class);
    expect($commands)->toContain(\BAGArt\TelegramBotBasic\Commands\TgPollerCommand::class);
    expect($commands)->toContain(\BAGArt\TelegramBotBasic\Commands\TgWhoamiCommand::class);
    expect($commands)->toContain(\BAGArt\TelegramBotBasic\Commands\Demo\DemoSendPollCommand::class);
    expect($commands)->toContain(\BAGArt\TelegramBotBasic\Commands\TgDevDTOActualizeCommand::class);
    expect($commands)->toHaveCount(5);
});
