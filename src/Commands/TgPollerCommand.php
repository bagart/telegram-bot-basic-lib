<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands;

use BAGArt\AsyncKernel\AsyncKernel;
use BAGArt\AsyncKernel\Contracts\ASKSchedulerContract;
use BAGArt\AsyncKernel\Drivers\ASKFiberScheduler;
use BAGArt\AsyncKernel\Wrappers\ASKLogWrapper;
use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Configs\TgPollerConfig;
use BAGArt\TelegramBot\Configs\TgServiceConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiClientContract;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Exceptions\TgApiUserBreakException;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\TgIntegration\WebhookManager;
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
                            {--token=     : Telegram Bot Token}
                            {--echo       : ECHO-mode(ping-pong)}
                            {--show       : Show messages}
                            {--silent     : Do not ask about Delete WebHook}
                            {--timeout=30 : Long-polling server timeout in seconds}
                            {--limit=100  : Maximum updates per request (1–100)}
                            {--once       : Process one batch of updates and exit}
                            {--no-ack     : Do not send ack to Telegram (Process one batch of updates and exit)}
                            {--dbg        : Debug messages}';

    protected $description = 'Start the Telegram bot in long-polling mode with Echo mode';

    public function handle(
        TgBotApiDTOClientContract $tgDTOClient,
        TgBotApiClientContract $client,
        ASKLogWrapper $logger,
        WebhookManager $webhookManager,
    ): int {
        $token = $this->resolveToken();
        if ($token === null) {
            return self::FAILURE;
        }

        $timeout = (int) $this->option('timeout');
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

        $asyncKernel = new AsyncKernel(logger: $logger);
        $asyncKernel->addTickable(new ASKFiberScheduler());

        $configPoller = $this->buildConfigPoller(
            token: $token,
            fn: function (
                TgApiTypeDTOContract $dto,
                TgServiceConfig $config,
                ?string $action = null,
                ?ASKSchedulerContract $scheduler = null,
            ) use (&$configPollerRef, $tgDTOClient, $token, $echoMode, $showMode, $once): void {
                $this->processPollUpdate(
                    dto: $dto,
                    tgDTOClient: $tgDTOClient,
                    botConfig: new TgBotConfig(token: $token),
                    echoMode: $echoMode,
                    showMode: $showMode,
                    once: $once,
                );
            },
            logger: $logger,
            pollerConfig: new TgPollerConfig(
                timeout: $timeout,
                noAck: $noAck,
            ),
        );

        $asyncKernel->addDaemon($configPoller);

        if ($this->botSetup !== null) {
            foreach ($this->botSetup->daemons as $daemon) {
                $asyncKernel->addDaemon($daemon);
            }
        }

        $asyncKernel->run();

        return self::SUCCESS;
    }

    private function processPollUpdate(
        TgApiTypeDTOContract $dto,
        TgBotApiDTOClientContract $tgDTOClient,
        TgBotConfig $botConfig,
        bool $echoMode,
        bool $showMode,
        bool $once,
    ): void {
        $update = $dto;
        assert($update instanceof UpdateTypeDTO);

        if ($showMode && $update->message) {
            $this->line("\t{$update->message->chat->id}: {$update->message->text}");
        } else {
            $bp = 1; // @todo
        }

        if ($echoMode && $update->message) {
            $sendMessageResponse = $tgDTOClient->request(
                $botConfig,
                new SendMessageMethodDTO(
                    chatId: $update->message->chat->id,
                    text: "echo: {$update->message->text}",
                ),
            );
            assert($sendMessageResponse->ok === true);
        } else {
            $bp = 1; // @todo
        }

        if ($once) {
            throw new TgApiUserBreakException('once');
        }
    }
}
