<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands;

use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
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
                            {chat_id      : Target chat ID}
                            {token?       : Telegram Bot Token}
                            {--user-id=   : Filter by user ID}
                            {--username=  : Filter by username (without @)}
                            {--timeout=30 : Long-polling server timeout in seconds}
                            {--dbg        : Debug messages}';

    protected $description = 'Interactive Telegram chat from terminal';

    private const MAX_MESSAGES = 20;
    private array $messages = [];
    private int $messageCount = 0;
    private string $inputBuffer = '';
    private array $inputHistory = [];
    private int $historyIndex = -1;
    private int $cursorPos = 0;

    public function handle(TgBotApiDTOClientContract $tgDTOClient): int
    {
        $token = $this->resolveToken();
        if ($token === null) {
            return self::FAILURE;
        }

        $chatId = $this->argument('chat_id');
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

        stream_set_blocking(STDIN, false);
        $this->renderInterface();

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
                    $chatId,
                    $userId,
                    $username
                ): bool {
                    $this->pollInput($tgDTOClient, $token, $chatId);

                    if (!$update->message) {
                        return true;
                    }

                    $message = $update->message;

                    if ((string)$message->chat->id !== (string)$chatId) {
                        return true;
                    }

                    if ($userId && (string)$message->from->id !== (string)$userId) {
                        return true;
                    }

                    if ($username && $message->from->username !== $username) {
                        return true;
                    }

                    $this->addMessage($message);
                    $this->renderInterface();

                    return true;
                },
                fnRetry: 5,
                noAck: false,
                delayOnFn: 0,
            );
    }

    private function addMessage(MessageTypeDTO $message): void
    {
        $this->messageCount++;
        $from = $message->from->username ?? $message->from->firstName ?? 'Unknown';
        $text = $message->text ?? '[media]';
        $chatType = $message->chat->type ?? 'private';

        $this->messages[] = [
            'id' => $this->messageCount,
            'from' => $from,
            'text' => $text,
            'time' => date('H:i:s'),
            'chatType' => $chatType,
            'userId' => $message->from->id ?? null,
        ];

        if (count($this->messages) > self::MAX_MESSAGES) {
            array_shift($this->messages);
        }
    }

    private function pollInput(TgBotApiDTOClientContract $tgDTOClient, string $token, string $chatId): void
    {
        $char = fread(STDIN, 1);
        if ($char === false || $char === '') {
            return;
        }

        if ($char === "\n" || $char === "\r") {
            $this->handleEnter($tgDTOClient, $token, $chatId);
        } elseif ($char === "\x1b") {
            $this->handleEscapeSequence();
        } elseif ($char === "\x15") {
            $this->handleCtrlU();
        } elseif ($char === "\x01") {
            $this->handleCtrlA();
        } elseif ($char === "\x05") {
            $this->handleCtrlE();
        } elseif ($char === "\x7f" || $char === "\x08") {
            $this->handleBackspace();
        } elseif (ord($char) >= 32) {
            $this->handlePrintableChar($char);
        }
    }

    private function handleEnter(TgBotApiDTOClientContract $tgDTOClient, string $token, string $chatId): void
    {
        if (!empty(trim($this->inputBuffer))) {
            $this->sendMessage($tgDTOClient, $token, $chatId, trim($this->inputBuffer));
            $this->inputHistory[] = $this->inputBuffer;
            if (count($this->inputHistory) > 50) {
                array_shift($this->inputHistory);
            }
            $this->inputBuffer = '';
            $this->cursorPos = 0;
            $this->historyIndex = -1;
            $this->renderInterface();
        }
    }

    private function handleEscapeSequence(): void
    {
        $seq = fread(STDIN, 2);
        if ($seq === false || $seq === '') {
            return;
        }

        if ($seq === "[A") {
            $this->handleArrowUp();
        } elseif ($seq === "[B") {
            $this->handleArrowDown();
        } elseif ($seq === "[D") {
            $this->handleArrowLeft();
        } elseif ($seq === "[C") {
            $this->handleArrowRight();
        }
    }

    private function handleArrowUp(): void
    {
        if (empty($this->inputHistory)) {
            return;
        }

        if ($this->historyIndex < count($this->inputHistory) - 1) {
            $this->historyIndex++;
            $this->inputBuffer = $this->inputHistory[count($this->inputHistory) - 1 - $this->historyIndex];
            $this->cursorPos = strlen($this->inputBuffer);
            $this->renderInterface();
        }
    }

    private function handleArrowDown(): void
    {
        if ($this->historyIndex > 0) {
            $this->historyIndex--;
            $this->inputBuffer = $this->inputHistory[count($this->inputHistory) - 1 - $this->historyIndex];
            $this->cursorPos = strlen($this->inputBuffer);
            $this->renderInterface();
        } elseif ($this->historyIndex === 0) {
            $this->historyIndex = -1;
            $this->inputBuffer = '';
            $this->cursorPos = 0;
            $this->renderInterface();
        }
    }

    private function handleArrowLeft(): void
    {
        if ($this->cursorPos > 0) {
            $this->cursorPos--;
            $this->renderInterface();
        }
    }

    private function handleArrowRight(): void
    {
        if ($this->cursorPos < strlen($this->inputBuffer)) {
            $this->cursorPos++;
            $this->renderInterface();
        }
    }

    private function handleCtrlU(): void
    {
        $this->inputBuffer = '';
        $this->cursorPos = 0;
        $this->renderInterface();
    }

    private function handleCtrlA(): void
    {
        $this->cursorPos = 0;
        $this->renderInterface();
    }

    private function handleCtrlE(): void
    {
        $this->cursorPos = strlen($this->inputBuffer);
        $this->renderInterface();
    }

    private function handleBackspace(): void
    {
        if ($this->cursorPos > 0) {
            $this->inputBuffer = substr($this->inputBuffer, 0, $this->cursorPos - 1)
                .substr($this->inputBuffer, $this->cursorPos);
            $this->cursorPos--;
            $this->renderInterface();
        }
    }

    private function handlePrintableChar(string $char): void
    {
        $this->inputBuffer = substr($this->inputBuffer, 0, $this->cursorPos)
            .$char
            .substr($this->inputBuffer, $this->cursorPos);
        $this->cursorPos++;
        $this->renderInterface();
    }

    private function renderInterface(): void
    {
        $lines = [];
        foreach ($this->messages as $msg) {
            $lines[] = sprintf(
                "[%s] #%d @%s: %s",
                $msg['time'],
                $msg['id'],
                $msg['from'],
                $msg['text']
            );
        }

        while (count($lines) < self::MAX_MESSAGES) {
            array_unshift($lines, '');
        }

        echo "\033[2J\033[H";
        echo "\033[36m╔══════════════════════════════════════════════════════╗\033[0m\n";
        echo "\033[36m║\033[0m \033[1mTelegram Chat Active Mode\033[0m                           \033[36m║\033[0m\n";
        echo "\033[36m╠══════════════════════════════════════════════════════╣\033[0m\n";

        foreach ($lines as $line) {
            $padded = mb_substr($line, 0, 50);
            $padded = str_pad($padded, 50, ' ', STR_PAD_RIGHT);
            echo "\033[36m║\033[0m {$padded} \033[36m║\033[0m\n";
        }

        echo "\033[36m╠══════════════════════════════════════════════════════╣\033[0m\n";
        echo "\033[36m║\033[0m \033[33m> \033[0m".str_pad(
                $this->inputBuffer,
                48,
                ' ',
                STR_PAD_RIGHT
            )." \033[36m║\033[0m\n";
        echo "\033[36m╚══════════════════════════════════════════════════════╝\033[0m\n";
        echo "\033[2A\033[5C";
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
