<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Processors;

use BAGArt\TelegramBot\CLI\Chatting\Input\ControlKeyEvent;
use BAGArt\TelegramBot\CLI\Chatting\Input\EnterPressedEvent;
use BAGArt\TelegramBot\CLI\Chatting\Input\EscapeSequenceEvent;
use BAGArt\TelegramBot\CLI\Chatting\Input\PrintableCharEvent;
use BAGArt\TelegramBot\CLI\Chatting\Input\TerminalInputHandler;
use BAGArt\TelegramBot\CLI\Chatting\TerminalUiRenderer;
use BAGArt\TelegramBot\CLI\Chatting\TuiChatSession;
use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgTypeDTOProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\Wrappers\Wrappers\TgOutputWrapper;
use LogicException;
use Throwable;

/**
 * Terminal UI chat processor behind tg:chatting: polls keyboard input, sends
 * typed messages, renders incoming updates. Instance-registered per command
 * run — dependencies are the live TUI session, so ::build() has no meaningful
 * context-only construction.
 */
final class TuiChattingUpdateProcessor implements TgTypeDTOProcessorContract
{
    public function __construct(
        private readonly TgBotApiDTOClientContract $dtoClient,
        private readonly TgBotConfig $botConfig,
        private readonly TuiChatSession $chatSession,
        private readonly TerminalInputHandler $inputHandler,
        private readonly TerminalUiRenderer $uiRenderer,
        private readonly TgOutputWrapper $output,
        private readonly bool $isStrictOrdered = false,
    ) {
    }

    public static function build(BotProcessorContext $context): self
    {
        throw new LogicException(
            self::class.' is instance-registered (live TUI session dependencies); construct it directly.'
        );
    }

    public function support(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return $dto instanceof UpdateTypeDTO;
    }

    public function isStrictOrdered(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return $this->isStrictOrdered;
    }

    public function isNeedUpdateDTO(): bool
    {
        return false;
    }

    public function executionKey(TgApiTypeDTOContract $dto): ?string
    {
        return null;
    }

    public function process(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): void {
        assert($dto instanceof UpdateTypeDTO);

        $this->processInput();

        if ($this->chatSession->matchesFilter($dto)) {
            $this->chatSession->addMessage($dto->message);
            $this->uiRenderer->renderChatInterface(
                $this->chatSession->getMessages(),
                $this->inputHandler->getInputBuffer(),
            );
        }
    }

    private function processInput(): void
    {
        $event = $this->inputHandler->poll();
        if ($event === null) {
            return;
        }

        $needsRender = false;

        if ($event instanceof EnterPressedEvent) {
            $text = $this->inputHandler->handleEnter();
            if ($text !== null) {
                $this->sendMessage($this->chatSession->getChatId(), $text);
                $needsRender = true;
            }
        } elseif ($event instanceof EscapeSequenceEvent) {
            if ($event->isArrowUp()) {
                $needsRender = $this->inputHandler->handleArrowUp();
            } elseif ($event->isArrowDown()) {
                $needsRender = $this->inputHandler->handleArrowDown();
            } elseif ($event->isArrowLeft()) {
                $needsRender = $this->inputHandler->handleArrowLeft();
            } elseif ($event->isArrowRight()) {
                $needsRender = $this->inputHandler->handleArrowRight();
            }
        } elseif ($event instanceof ControlKeyEvent) {
            if ($event->isCtrlU()) {
                $needsRender = $this->inputHandler->handleCtrlU();
            } elseif ($event->isCtrlA()) {
                $needsRender = $this->inputHandler->handleCtrlA();
            } elseif ($event->isCtrlE()) {
                $needsRender = $this->inputHandler->handleCtrlE();
            }
        } elseif ($event instanceof PrintableCharEvent) {
            if ($event->char === "\x7f") {
                $needsRender = $this->inputHandler->handleBackspace();
            } else {
                $needsRender = $this->inputHandler->handlePrintableChar($event->char);
            }
        }

        if ($needsRender) {
            $this->uiRenderer->renderChatInterface(
                $this->chatSession->getMessages(),
                $this->inputHandler->getInputBuffer(),
            );
        }
    }

    private function sendMessage(string $chatId, string $text): void
    {
        try {
            $this->dtoClient->request(
                $this->botConfig,
                new SendMessageMethodDTO(
                    chatId: $chatId,
                    text: $text,
                ),
            );
        } catch (Throwable $e) {
            $this->output->error("Failed to send message: {$e->getMessage()}");
        }
    }
}
