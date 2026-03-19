<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Exceptions\ApiCommunication\TgApiCommunicationException;
use BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBotBasic\Commands\Traits\TokenResolverTrait;
use Illuminate\Console\Command;

class TgWhoamiCommand extends Command
{
    use TokenResolverTrait;

    protected $signature = 'tg:whoami
                            {--token= : Telegram Bot Token}';

    protected $description = 'Method: getMe';

    public function handle(
        TgBotApiDTOClientContract $tgDTOClient,
    ): int {
        $token = $this->resolveToken();
        if ($token === null) {
            return self::FAILURE;
        }

        try {
            $response = $tgDTOClient->request(
                new TgBotConfig(token: $token),
                new GetMeMethodDTO(),
            );
            $user = $response->result;
            assert($user instanceof UserTypeDTO);

            $this->info(
                "✅ Bot verified: @{$user->username} ({$user->firstName})"
            );
        } catch (TgApiCommunicationException $e) {
            $this->error("❌ Failed to connect to Telegram: {$e->getMessage()}; ".$e::class);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
