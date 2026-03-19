<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\DevTool;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class TgLibUpdater
{
    public function update(): string
    {
        $command = 'npm update @grom.js/bot-api-spec';

        $result = Process::run($command);

        throw_unless(
            $result->successful(),
            RuntimeException::class,
            "[ERROR] Unable to update tg lib: {$result->errorOutput()}"
        );

        return $result->output();
    }
}
