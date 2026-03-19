<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\TgApiServices;

use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\DeleteWebhookMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\GetWebhookInfoMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SetWebhookMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\WebhookInfoTypeDTO;

/**
 * @see https://core.telegram.org/bots/api#webhooks
 */
class Webhook
{
    public function __construct(
        private readonly TgBotApiDTOClientContract $tgDTOClient,
    ) {
    }

    /**
     * @see https://core.telegram.org/bots/api#getwebhookinfo
     */
    public function get(string $token): WebhookInfoTypeDTO
    {
        $response = $this->tgDTOClient->request(
            token: $token,
            dto: new GetWebhookInfoMethodDTO(),
        );
        $webhookInfo = $response->result;
        assert($webhookInfo instanceof WebhookInfoTypeDTO);

        return $webhookInfo;
    }

    /**
     * @see https://core.telegram.org/bots/api#setwebhook
     *
     * @param  string[]|null  $allowedUpdates
     */
    public function set(
        string $token,
        string $url,
        ?string $certificate = null,
        ?string $ipAddress = null,
        ?int $maxConnections = null,
        ?array $allowedUpdates = null,
        ?bool $dropPendingUpdates = null,
        ?string $secretToken = null,
    ): bool {
        $response = $this->tgDTOClient->request(
            token: $token,
            dto: new SetWebhookMethodDTO(
                url: $url,
                certificate: $certificate,
                ipAddress: $ipAddress,
                maxConnections: $maxConnections,
                allowedUpdates: $allowedUpdates,
                dropPendingUpdates: $dropPendingUpdates,
                secretToken: $secretToken,
            ),
        );
        assert($response->result === true);

        return $response->result;
    }

    /**
     * @see https://core.telegram.org/bots/api#deletewebhook
     */
    public function delete(
        string $token,
        ?bool $dropPendingUpdates = null,
    ): bool {
        $response = $this->tgDTOClient->request(
            token: $token,
            dto: new DeleteWebhookMethodDTO(
                dropPendingUpdates: $dropPendingUpdates,
            ),
        );
        assert($response->result === true);

        return $response->result;
    }
}
