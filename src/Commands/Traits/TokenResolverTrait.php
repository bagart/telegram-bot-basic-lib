<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Traits;

use Illuminate\Console\Command;

/** @mixin Command */
trait TokenResolverTrait
{
    protected function resolveToken(): ?string
    {
        $token = $this->option('token') ?: null;

        if ($token === null) {
            $this->error('❌ Token not provided. Pass via --token=xxx:xxx.');

            return null;
        } elseif (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
            $this->error('❌ Invalid token format. Token should be like: 123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');

            return null;
        }

        return $token;
    }
}
