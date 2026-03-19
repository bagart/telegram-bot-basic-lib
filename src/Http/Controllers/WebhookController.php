<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Http\Controllers;

use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Contracts\TgApiServices\TgApiDTOMapperContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApiServices\TgEntityNamer;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * @see https://core.telegram.org/bots/api#update
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly TgBotApiDTOClientContract $tgDTOClient,
        private readonly TgApiDTOMapperContract $tgApiDTOMapper,
        private readonly TgBotLogWrapper $logger,
        private readonly TgEntityNamer $tgEntityNamer,
    ) {
    }

    public function handle(Request $request, string $token): JsonResponse
    {
        try {
            $update = $this->parseUpdate($request->all());
        } catch (Throwable $e) {
            $this->logger->error('Webhook parse error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false], 200);
        }

        if ($update->message) {
            assert($update->message instanceof MessageTypeDTO);
            $this->processMessage($token, $update->message);
        }

        return response()->json(['ok' => true]);
    }

    public function parseUpdate(array $data): UpdateTypeDTO
    {
        return $this->tgApiDTOMapper->fromArray(
            UpdateTypeDTO::class,
            $data,
        );
    }

    private function processMessage(
        string $token,
        MessageTypeDTO $message,
    ): void {
        $this->logger->info(
            '#'.$this->tgEntityNamer->name($message->chat)
            .' ['
            .($message->from ? $this->tgEntityNamer->name($message->from).' => ' : null)
            .$this->resolveBotName($token)
            .']: '.$message->text
        );

        try {
            $this->tgDTOClient->request(
                $token,
                new SendMessageMethodDTO(
                    chatId: $message->chat->id,
                    text: "echo: {$message->text}",
                ),
            );
        } catch (Throwable $e) {
            $this->logger->error('Webhook echo reply error', [
                'chat_id' => $message->chat->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function resolveBotName(string $token): string
    {
        $getMeResponse = $this->tgDTOClient->request($token, new GetMeMethodDTO());
        assert($getMeResponse->result instanceof UserTypeDTO);

        return $this->tgEntityNamer->name($getMeResponse->result);
    }
}
