<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands;

use BAGArt\AsyncKernel\AsyncKernel;
use BAGArt\AsyncKernel\Drivers\ASKFiberScheduler;
use BAGArt\AsyncKernel\Wrappers\ASKLogWrapper;
use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Configs\TgPollerConfig;
use BAGArt\TelegramBot\TgIntegration\WebhookManager;
use BAGArt\TelegramBotBasic\Commands\Processors\ConsoleEchoUpdateProcessor;
use BAGArt\TelegramBotBasic\Commands\Traits\ArtisanExtraTrait;
use BAGArt\TelegramBotBasic\Commands\Traits\LongPollingCommandTrait;
use BAGArt\TelegramBotBasic\Commands\Traits\TokenResolverTrait;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiClientContract;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Wrappers\Wrappers\TgOutputWrapper;
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

        $timeout = (int)$this->option('timeout');
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
            $this->error("Failed to remove tg_webhook: {$e->getMessage()}; ".$e::class);

            return self::FAILURE;
        }

        $asyncKernel = new AsyncKernel(logger: $logger);
        $asyncKernel->addTickable(new ASKFiberScheduler());

        $configPoller = $this->buildConfigPoller(
            token: $token,
            updateProcessor: new ConsoleEchoUpdateProcessor(
                dtoClient: $tgDTOClient,
                output: new TgOutputWrapper($this->output),
                botConfig: new TgBotConfig(token: $token),
                echoMode: $echoMode,
                showMode: $showMode,
                once: $once,
            ),
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
}
