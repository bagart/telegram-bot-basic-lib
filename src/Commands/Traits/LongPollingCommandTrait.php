<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Traits;

use BAGArt\TelegramBot\BotServices\TgLongPoller;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Exceptions\TgApiUserBreakException;
use BAGArt\TelegramBot\TgApi\Methods\DTO\GetUpdatesMethodDTO;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use BAGArt\TelegramBot\Wrappers\TgOutputWrapper;
use Illuminate\Console\Command;

/** @mixin Command */
trait LongPollingCommandTrait
{
    private function buildLongPoller(
        TgBotApiDTOClientContract $tgDTOClient,
        TgBotLogWrapper $logger,
        string $token,
    ): TgLongPoller {
        $output = new TgOutputWrapper($this->output);

        $poller = new TgLongPoller(
            tgDTOClient: $tgDTOClient,
            logger: $logger,
            output: $output,
            token: $token,
        );

        $this->trap(SIGINT, function () use ($poller, $output): void {
            $poller->stop();
            $output->newLine();
            $output->info('Stopping...');

            throw new TgApiUserBreakException(
                GetUpdatesMethodDTO::tgApiEntity()->name
            );
        });

        return $poller;
    }
}
