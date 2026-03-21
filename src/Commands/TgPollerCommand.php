<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands;

use BAGArt\TelegramBot\BotServices\WebhookManager;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use BAGArt\TelegramBotBasic\Commands\Traits\ArtisanExtraTrait;
use BAGArt\TelegramBotBasic\Commands\Traits\LongPollingCommandTrait;
use BAGArt\TelegramBotBasic\Commands\Traits\TokenResolverTrait;
use Illuminate\Console\Command;
use Throwable;

class TgPollerCommand extends Command
{
    use TokenResolverTrait;
    use LongPollingCommandTrait;
    use ArtisanExtraTrait;

    protected $signature = 'tg:poll
                            {token?       : Telegram Bot Token}
                            {--echo       : ECHO-mode(ping-pong)}
                            {--show       : Show messages}
                            {--silent     : Do not ask about Delete WebHook}
                            {--timeout=30 : Long-polling server timeout in seconds}
                            {--limit=100  : Maximum updates per request (1–100)}
                            {--once       : Process one batch of updates and exit}
                            {--no-ack     : Process one batch of updates and exit}
                            {--debug      : Debug messages}';

    protected $description = 'Start the Telegram bot in long-polling mode with Echo mode';

    public function handle(
        TgBotApiDTOClientContract $tgDTOClient,
        TgBotLogWrapper $logger,
        WebhookManager $webhookManager,
    ): int {
        $token = $this->resolveToken();
        if ($token === null) {
            return self::FAILURE;
        }

        $once = $this->option('once');
        $echoMode = $this->option('echo');
        $showMode = $this->option('show');
        $noAck = $this->option('no-ack');

        try {
            $webhookInfo = $webhookManager->get($token);
            if ($webhookInfo->url) {
                $this->warn("Webhook already exist: {$webhookInfo->url}");
                if (
                    !$this->option('silent')
                    && !$this->option('once')
                    && $this->confirm('Is need to DeleteWebhook')
                ) {
                    $webhookManager->delete($token);
                }
            } else {
                $this->line('Webhook not set');
            }
        } catch (Throwable $e) {
            $this->dbg($e);
            $this->error("Failed to remove webhook: {$e->getMessage()}; ".$e::class);

            return self::FAILURE;
        }

        return $this->longPolling(
            tgDTOClient: $tgDTOClient,
            logger: $logger,
            token: $token,
            fn: function (
                UpdateTypeDTO $update,
                int $total,
            ) use (
                $tgDTOClient,
                $token,
                $echoMode,
                $showMode,
                $once,
            ): ?bool {
                if ($showMode) {
                    if ($update->message) {
                        $this->line("\t{$update->message->chat->id}: {$update->message->text}");
                    } else {
                        $bp = 1;//@todo
                    }
                }
                if ($echoMode) {
                    if ($update->message) {
                        $sendMessageResponse = $tgDTOClient->request(
                            $token,
                            new SendMessageMethodDTO(
                                chatId: $update->message->chat->id,
                                text: "echo: {$update->message->text}",
                            ),
                        );
                        assert($sendMessageResponse->ok === true);
                    } else {
                        $bp = 1;//@todo
                    }
                }

                if ($once) {
                    return false;
                }

                return true;
            },
            fnRetry: 5,
            noAck: $noAck,
            delayOnFn: $echoMode ? 1.2 : 0,
        );
    }
}
