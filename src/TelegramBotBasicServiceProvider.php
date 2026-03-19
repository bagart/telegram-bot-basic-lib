<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic;

use BAGArt\TelegramBotBasic\Commands\Demo\DemoSendPollCommand;
use BAGArt\TelegramBotBasic\Commands\TgDevDTOActualizeCommand;
use BAGArt\TelegramBotBasic\Commands\TgPollerCommand;
use BAGArt\TelegramBotBasic\Commands\TgWhoamiCommand;
use BAGArt\TelegramBotBasic\Commands\WebhookCommand;
use Illuminate\Support\ServiceProvider;

class TelegramBotBasicServiceProvider extends ServiceProvider
{
    protected array $commands = [
        WebhookCommand::class,
        TgPollerCommand::class,
        TgDevDTOActualizeCommand::class,
        TgWhoamiCommand::class,
        DemoSendPollCommand::class,
    ];

    public function register(): void
    {
        $this->commands($this->commands);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
