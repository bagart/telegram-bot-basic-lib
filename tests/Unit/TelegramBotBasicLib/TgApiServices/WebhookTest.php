<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\TgApi\Types\DTO\WebhookInfoTypeDTO;
use BAGArt\TelegramBot\Services\TgApiResponse;
use BAGArt\TelegramBotBasic\Services\Webhook;

beforeEach(function () {
    $this->tgDTOClient = Mockery::mock(TgBotApiDTOClientContract::class);
    $this->webhook = new Webhook($this->tgDTOClient);
    $this->token = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
});

afterEach(function () {
    Mockery::close();
});

test('get returns webhook info', function () {
    $webhookInfo = new WebhookInfoTypeDTO(
        url: 'https://example.com/tg/webhook',
        hasCustomCertificate: false,
        pendingUpdateCount: 5,
        ipAddress: '1.2.3.4',
        maxConnections: 40,
        allowedUpdates: ['message', 'callback_query'],
    );

    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\GetWebhookInfoMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], $webhookInfo));

    $result = $this->webhook->get($this->token);

    expect($result)->toBeInstanceOf(WebhookInfoTypeDTO::class);
    expect($result->url)->toBe('https://example.com/tg/webhook');
    expect($result->pendingUpdateCount)->toBe(5);
    expect($result->maxConnections)->toBe(40);
    expect($result->allowedUpdates)->toBe(['message', 'callback_query']);
});

test('get returns webhook info with empty url when not set', function () {
    $webhookInfo = new WebhookInfoTypeDTO(
        url: '',
        hasCustomCertificate: false,
        pendingUpdateCount: 0,
    );

    $this->tgDTOClient
        ->shouldReceive('request')
        ->once()
        ->andReturn(new TgApiResponse(true, [], $webhookInfo));

    $result = $this->webhook->get($this->token);

    expect($result->url)->toBe('');
    expect($result->pendingUpdateCount)->toBe(0);
});

test('set webhook with url only', function () {
    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::on(function ($dto) {
            return $dto instanceof \BAGArt\TelegramBot\TgApi\Methods\DTO\SetWebhookMethodDTO
                && $dto->url === 'https://example.com/tg/abc';
        }))
        ->once()
        ->andReturn(new TgApiResponse(true, [], true));

    $result = $this->webhook->set($this->token, 'https://example.com/tg/abc');

    expect($result)->toBeTrue();
});

test('set webhook with all parameters', function () {
    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::on(function ($dto) {
            return $dto instanceof \BAGArt\TelegramBot\TgApi\Methods\DTO\SetWebhookMethodDTO
                && $dto->url === 'https://example.com/tg/full'
                && $dto->certificate === '/path/to/cert.pem'
                && $dto->ipAddress === '10.0.0.1'
                && $dto->maxConnections === 50
                && $dto->allowedUpdates === ['message']
                && $dto->dropPendingUpdates === true
                && $dto->secretToken === 'secret123';
        }))
        ->once()
        ->andReturn(new TgApiResponse(true, [], true));

    $result = $this->webhook->set(
        token: $this->token,
        url: 'https://example.com/tg/full',
        certificate: '/path/to/cert.pem',
        ipAddress: '10.0.0.1',
        maxConnections: 50,
        allowedUpdates: ['message'],
        dropPendingUpdates: true,
        secretToken: 'secret123',
    );

    expect($result)->toBeTrue();
});

test('delete webhook without options', function () {
    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::type(\BAGArt\TelegramBot\TgApi\Methods\DTO\DeleteWebhookMethodDTO::class))
        ->once()
        ->andReturn(new TgApiResponse(true, [], true));

    $result = $this->webhook->delete($this->token);

    expect($result)->toBeTrue();
});

test('delete webhook with drop pending updates', function () {
    $this->tgDTOClient
        ->shouldReceive('request')
        ->with($this->token, Mockery::on(function ($dto) {
            return $dto instanceof \BAGArt\TelegramBot\TgApi\Methods\DTO\DeleteWebhookMethodDTO
                && $dto->dropPendingUpdates === true;
        }))
        ->once()
        ->andReturn(new TgApiResponse(true, [], true));

    $result = $this->webhook->delete($this->token, dropPendingUpdates: true);

    expect($result)->toBeTrue();
});
