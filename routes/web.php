<?php

declare(strict_types=1);

use BAGArt\TelegramBotBasic\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('tg/{token}', [WebhookController::class, 'handle']);
