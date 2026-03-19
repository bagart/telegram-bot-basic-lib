<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Traits;

use Illuminate\Console\Command;

/** @mixin Command */
trait TokenResolverTrait
{
    protected function resolveToken(): ?string
    {
        $token = $this->hasArgument('token') ? ($this->argument('token') ?: null) : null;

        if ($token === null) {
            $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: null;
        }

        if ($token === null) {
            $this->error('❌ Token not provided. Pass via argument or set TELEGRAM_BOT_TOKEN env.');
        } elseif (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
            $this->error('❌ Invalid token format. Token should be like: 123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');

            return null;
        }

        return $token ?: null;
    }
}
