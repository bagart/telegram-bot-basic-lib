<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic;

use BAGArt\TelegramBotBasic\Commands\Demo\DemoSendPollCommand;
use BAGArt\TelegramBotBasic\Commands\Demo\ExampleAllVariantsCommand;
use BAGArt\TelegramBotBasic\Commands\Demo\ExampleParallelBatchCommand;
use BAGArt\TelegramBotBasic\Commands\Demo\ExampleParallelFuturesCommand;
use BAGArt\TelegramBotBasic\Commands\Demo\ExampleSequentialCommand;
use BAGArt\TelegramBotBasic\Commands\Demo\ExampleTransportComparisonCommand;
use BAGArt\TelegramBotBasic\Commands\TgChattingCommand;
use BAGArt\TelegramBotBasic\Commands\TgWhoamiCommand;
use BAGArt\TelegramBotBasic\Commands\WebhookCommand;
use Illuminate\Support\ServiceProvider;

class TelegramBotBasicServiceProvider extends ServiceProvider
{
    protected array $commands = [
        WebhookCommand::class,
        TgWhoamiCommand::class,
        DemoSendPollCommand::class,
        TgChattingCommand::class,
        ExampleSequentialCommand::class,
        ExampleParallelFuturesCommand::class,
        ExampleParallelBatchCommand::class,
        ExampleAllVariantsCommand::class,
        ExampleTransportComparisonCommand::class,
    ];

    public function register(): void
    {
        $this->commands($this->commands);
    }

    public function boot(): void
    {
        //
    }
}
