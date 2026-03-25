<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands;

use BAGArt\TelegramBot\Chat\ChatSession;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\Terminal\Input\ControlKeyEvent;
use BAGArt\TelegramBot\Terminal\Input\EnterPressedEvent;
use BAGArt\TelegramBot\Terminal\Input\EscapeSequenceEvent;
use BAGArt\TelegramBot\Terminal\Input\PrintableCharEvent;
use BAGArt\TelegramBot\Terminal\Input\TerminalInputHandler;
use BAGArt\TelegramBot\Terminal\TerminalUiRenderer;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use BAGArt\TelegramBotBasic\Commands\Traits\ArtisanExtraTrait;
use BAGArt\TelegramBotBasic\Commands\Traits\LongPollingCommandTrait;
use BAGArt\TelegramBotBasic\Commands\Traits\TokenResolverTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function handle(TgBotApiDTOClientContract $tgDTOClient): int
    {
        $token = $this->resolveToken();
        if ($token === null) {
            return self::FAILURE;
        }

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

        $chatSession = new ChatSession($chatId, $userId, $username);
        $inputHandler = new TerminalInputHandler();
        $uiRenderer = new TerminalUiRenderer();

        $inputHandler->enableNonBlockingMode();
        $uiRenderer->renderChatInterface($chatSession->getMessages(), $inputHandler->getInputBuffer());

        return $this
            ->buildLongPoller(
                tgDTOClient: $tgDTOClient,
                logger: new TgBotLogWrapper(Log::channel('single')),
                token: $token,
            )
            ->run(
                fn: function (UpdateTypeDTO $update, int $total) use (
                    $tgDTOClient,
                    $token,
                    $chatSession,
                    $inputHandler,
                    $uiRenderer,
                ): bool {
                    $this->processInput($tgDTOClient, $token, $chatSession, $inputHandler, $uiRenderer);

                    if ($chatSession->matchesFilter($update)) {
                        $chatSession->addMessage($update->message);
                        $uiRenderer->renderChatInterface($chatSession->getMessages(), $inputHandler->getInputBuffer());
                    }

                    return true;
                },
                fnRetry: 5,
                noAck: false,
                delayOnFn: 0,
            );
    }

    private function processInput(
        TgBotApiDTOClientContract $tgDTOClient,
        string $token,
        ChatSession $chatSession,
        TerminalInputHandler $inputHandler,
        TerminalUiRenderer $uiRenderer,
    ): void {
        $event = $inputHandler->poll();
        if ($event === null) {
            return;
        }

        $needsRender = false;

        if ($event instanceof EnterPressedEvent) {
            $text = $inputHandler->handleEnter();
            if ($text !== null) {
                $this->sendMessage($tgDTOClient, $token, $chatSession->getChatId(), $text);
                $needsRender = true;
            }
        } elseif ($event instanceof EscapeSequenceEvent) {
            if ($event->isArrowUp()) {
                $needsRender = $inputHandler->handleArrowUp();
            } elseif ($event->isArrowDown()) {
                $needsRender = $inputHandler->handleArrowDown();
            } elseif ($event->isArrowLeft()) {
                $needsRender = $inputHandler->handleArrowLeft();
            } elseif ($event->isArrowRight()) {
                $needsRender = $inputHandler->handleArrowRight();
            }
        } elseif ($event instanceof ControlKeyEvent) {
            if ($event->isCtrlU()) {
                $needsRender = $inputHandler->handleCtrlU();
            } elseif ($event->isCtrlA()) {
                $needsRender = $inputHandler->handleCtrlA();
            } elseif ($event->isCtrlE()) {
                $needsRender = $inputHandler->handleCtrlE();
            }
        } elseif ($event instanceof PrintableCharEvent) {
            if ($event->char === "\x7f") {
                $needsRender = $inputHandler->handleBackspace();
            } else {
                $needsRender = $inputHandler->handlePrintableChar($event->char);
            }
        }

        if ($needsRender) {
            $uiRenderer->renderChatInterface($chatSession->getMessages(), $inputHandler->getInputBuffer());
        }
    }

    private function sendMessage(
        TgBotApiDTOClientContract $tgDTOClient,
        string $token,
        string $chatId,
        string $text
    ): void {
        try {
            $tgDTOClient->request(
                $token,
                new SendMessageMethodDTO(
                    chatId: $chatId,
                    text: $text,
                ),
            );
        } catch (Throwable $e) {
            $this->error("Failed to send message: {$e->getMessage()}");
        }
    }
}
