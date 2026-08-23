<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands;

use BAGArt\AsyncKernel\AsyncKernel;
use BAGArt\AsyncKernel\Drivers\ASKFiberScheduler;
use BAGArt\AsyncKernel\Wrappers\ASKLogWrapper;
use BAGArt\TelegramBot\CLI\Chatting\Input\TerminalInputHandler;
use BAGArt\TelegramBot\CLI\Chatting\TerminalUiRenderer;
use BAGArt\TelegramBot\CLI\Chatting\TuiChatSession;
use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiClientContract;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Wrappers\Wrappers\TgOutputWrapper;
use BAGArt\TelegramBotBasic\Commands\Processors\TuiChattingUpdateProcessor;
use BAGArt\TelegramBotBasic\Commands\Traits\ArtisanExtraTrait;
use BAGArt\TelegramBotBasic\Commands\Traits\LongPollingCommandTrait;
use BAGArt\TelegramBotBasic\Commands\Traits\TokenResolverTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TgChattingCommand extends Command
{
    use TokenResolverTrait;
    use LongPollingCommandTrait;
    use ArtisanExtraTrait;

    protected $signature = 'tg:chatting
                            {--token=     : Telegram Bot Token}
                            {--chat=      : Target chat ID}
                            {--user-id=   : Filter by user ID}
                            {--username=  : Filter by username (without @)}
                            {--timeout=30 : Long-polling server timeout in seconds}
                            {--dbg        : Debug messages}';

    protected $description = 'Interactive Telegram chat from terminal';

    public function handle(
        TgBotApiDTOClientContract $tgDTOClient,
        TgBotApiClientContract $client,
    ): int {
        $token = $this->resolveToken();
        if ($token === null) {
            return self::FAILURE;
        }
        $botConfig = new TgBotConfig(token: $token);

        $chatId = $this->option('chat');
        $userId = $this->option('user-id');
        $username = $this->option('username');

        if (!$chatId) {
            $this->error('Chat ID is required.');

            return self::FAILURE;
        }

        $this->info("Starting active chat mode for chat: {$chatId}");
        if ($userId) {
            $this->info("Filtering by user ID: {$userId}");
        } elseif ($username) {
            $this->info("Filtering by username: @{$username}");
        }
        $this->info('Press Ctrl+C to stop. Type message and press Enter to send.');
        $this->info('Shortcuts: Ctrl+U=clear, Ctrl+A=home, Ctrl+E=end, Up/Down=history');
        $this->newLine();

        $chatSession = new TuiChatSession($chatId, $userId, $username);
        $inputHandler = new TerminalInputHandler();
        $uiRenderer = new TerminalUiRenderer();

        $inputHandler->enableNonBlockingMode();
        $uiRenderer->renderChatInterface($chatSession->getMessages(), $inputHandler->getInputBuffer());

        $tgLogger = new ASKLogWrapper(Log::channel('single'));

        $asyncKernel = new AsyncKernel(logger: $tgLogger);
        $asyncKernel->addTickable(new ASKFiberScheduler());

        $configPoller = $this->buildConfigPoller(
            token: $token,
            updateProcessor: new TuiChattingUpdateProcessor(
                dtoClient: $tgDTOClient,
                botConfig: $botConfig,
                chatSession: $chatSession,
                inputHandler: $inputHandler,
                uiRenderer: $uiRenderer,
                output: new TgOutputWrapper($this->output),
            ),
            logger: $tgLogger,
        );

        $asyncKernel->addDaemon($configPoller);
        $asyncKernel->run();

        return self::SUCCESS;
    }
}
