<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands;

use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\WebhookInfoTypeDTO;
use BAGArt\TelegramBotBasic\Commands\Traits\TokenResolverTrait;
use BAGArt\TelegramBotBasic\TgApiServices\Webhook;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class WebhookCommand extends Command
{
    use TokenResolverTrait;

    protected $signature = 'tg:webhook
                            {token? : Telegram Bot Token}
                            {--remove : Remove webhook}
                            {--url= : Webhook URL to set}
                            {--certificate= : Public key certificate file path}
                            {--ip-address= : Fixed IP address for webhook}
                            {--max-connections= : Max simultaneous connections (1-100)}
                            {--allowed-updates=* : Allowed update types}
                            {--drop-pending : Drop all pending updates on set/delete}
                            {--secret-token= : Secret token for webhook requests}';

    protected $description = 'Manage Telegram webhooks vy One Token';

    private const ALLOWED_UPDATES = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'callback_query',
        'inline_query',
        'chosen_inline_result',
        'my_chat_member',
        'chat_member',
        'chat_join_request',
    ];

    public function handle(
        Webhook $webhook,
        TgBotApiDTOClientContract $tgDTOClient,
    ): int {
        $token = $this->resolveToken();
        if ($token === null) {
            return self::FAILURE;
        }

        $currentInfo = $this->showCurrentState($webhook, $tgDTOClient, $token);

        if ($this->option('remove')) {
            return $this->removeWebhook($webhook, $token);
        }

        $url = $this->resolveOption(
            cliValue: $this->option('url'),
            currentValue: $currentInfo?->url ?: null,
            prompt: fn () => text(
                label: 'Webhook URL',
                placeholder: 'https://example.com/tg/webhook/...',
                required: true,
            ),
        );

        $certificate = $this->option('certificate');

        $ipAddress = $this->resolveOption(
            cliValue: $this->option('ip-address'),
            currentValue: $currentInfo?->ipAddress ?: null,
            prompt: fn () => text(
                label: 'IP White list',
                placeholder: '10.20.30.40',
            ),
        );

        $maxConnections = $this->resolveOption(
            cliValue: $this->option('max-connections') !== null ? (int) $this->option('max-connections') : null,
            currentValue: $currentInfo?->maxConnections,
            prompt: null,
        );

        $allowedUpdates = $this->resolveAllowedUpdates($currentInfo);

        $dropPending = $this->option('drop-pending');
        $secretToken = $this->option('secret-token');

        try {
            $webhook->set(
                token: $token,
                url: $url,
                certificate: $certificate,
                ipAddress: $ipAddress,
                maxConnections: $maxConnections,
                allowedUpdates: $allowedUpdates ?: null,
                dropPendingUpdates: $dropPending,
                secretToken: $secretToken,
            );
            $this->info('Webhook set successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to set webhook: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function showCurrentState(
        Webhook $webhook,
        TgBotApiDTOClientContract $tgDTOClient,
        string $token,
    ): ?WebhookInfoTypeDTO {
        $botName = $this->resolveBotName($tgDTOClient, $token);
        $this->info("--- Bot: {$botName} ---");

        try {
            $info = $webhook->get($token);
            $this->displayWebhookInfo($info);

            return $info;
        } catch (Throwable $e) {
            $this->error("Failed to get webhook info: {$e->getMessage()}");

            return null;
        }
    }

    public function resolveBotName(
        TgBotApiDTOClientContract $tgDTOClient,
        string $token,
    ): string {
        try {
            $meResponse = $tgDTOClient->request($token, new GetMeMethodDTO());
            $me = $meResponse->result;
            assert($me instanceof UserTypeDTO);

            return $me->username ? "@{$me->username}" : ($me->firstName ?? 'unknown');
        } catch (Throwable) {
            return 'unknown';
        }
    }

    public function displayWebhookInfo(WebhookInfoTypeDTO $info): void
    {
        if ($info->url) {
            $this->warn('URL: '.$info->url);
            $this->line('Has custom certificate: '.($info->hasCustomCertificate ? 'yes' : 'no'));
            $this->line('Pending updates: '.$info->pendingUpdateCount);
            $this->line('Max connections: '.($info->maxConnections ?? 'default'));
            $this->line('Allowed updates: '.($info->allowedUpdates ? json_encode($info->allowedUpdates) : 'all'));
            $this->line('Last error: '.($info->lastErrorMessage ?: 'none'));
            $this->line('IP address: '.($info->ipAddress ?? 'default'));
        } else {
            $this->warn('Webhook not set');
        }
        $this->newLine();
    }

    private function removeWebhook(Webhook $webhook, string $token): int
    {
        try {
            $webhook->delete($token, $this->option('drop-pending'));
            $this->info('Webhook removed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to remove webhook: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function resolveOption(
        mixed $cliValue,
        mixed $currentValue,
        ?callable $prompt,
    ): mixed {
        if ($cliValue !== null) {
            return $cliValue;
        }

        if ($currentValue !== null) {
            return $currentValue;
        }

        return $prompt ? $prompt() : null;
    }

    private function resolveAllowedUpdates(?WebhookInfoTypeDTO $currentInfo): array
    {
        $cliValue = $this->option('allowed-updates');
        if ($cliValue !== [] && $cliValue !== ['*']) {
            return $cliValue;
        }

        $default = $currentInfo?->allowedUpdates
            ?: ['message', 'edited_channel_post', 'callback_query'];

        return multiselect(
            label: 'Allowed updates',
            options: array_combine(self::ALLOWED_UPDATES, self::ALLOWED_UPDATES),
            default: $default,
        );
    }
}
