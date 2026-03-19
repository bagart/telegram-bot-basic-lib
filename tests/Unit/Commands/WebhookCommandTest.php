<?php

declare(strict_types=1);

use BAGArt\TelegramBotBasic\Commands\WebhookCommand;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\WebhookInfoTypeDTO;
use BAGArt\TelegramBot\Services\TgApiResponse;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    $this->command = new class extends WebhookCommand {
        public function exposeResolveBotName(
            TgBotApiDTOClientContract $client,
            string $token,
        ): string {
            return $this->resolveBotName($client, $token);
        }

        public function exposeDisplayWebhookInfo(WebhookInfoTypeDTO $info): void
        {
            $this->displayWebhookInfo($info);
        }
    };
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput());
    $this->command->setOutput($output);
});

afterEach(function () {
    Mockery::close();
});

test('resolveBotName returns @username when available', function () {
    $client = Mockery::mock(TgBotApiDTOClientContract::class);
    $user = new UserTypeDTO(id: '1', isBot: true, firstName: 'Bot', username: 'mybot');
    $client->shouldReceive('request')
        ->andReturn(new TgApiResponse(true, [], $user));

    $name = $this->command->exposeResolveBotName($client, 'token');

    expect($name)->toBe('@mybot');
});

test('resolveBotName returns firstName when no username', function () {
    $client = Mockery::mock(TgBotApiDTOClientContract::class);
    $user = new UserTypeDTO(id: '1', isBot: true, firstName: 'MyBot', username: '');
    $client->shouldReceive('request')
        ->andReturn(new TgApiResponse(true, [], $user));

    $name = $this->command->exposeResolveBotName($client, 'token');

    expect($name)->toBe('MyBot');
});

test('resolveBotName returns unknown on error', function () {
    $client = Mockery::mock(TgBotApiDTOClientContract::class);
    $client->shouldReceive('request')
        ->andThrow(new RuntimeException('Network error'));

    $name = $this->command->exposeResolveBotName($client, 'token');

    expect($name)->toBe('unknown');
});

test('displayWebhookInfo shows url when set', function () {
    $info = new WebhookInfoTypeDTO(
        url: 'https://example.com/hook',
        hasCustomCertificate: false,
        pendingUpdateCount: 3,
        ipAddress: '1.2.3.4',
        maxConnections: 40,
        allowedUpdates: ['message'],
    );

    $this->command->exposeDisplayWebhookInfo($info);
})->throwsNoExceptions();

test('displayWebhookInfo shows not set when url empty', function () {
    $info = new WebhookInfoTypeDTO(
        url: '',
        hasCustomCertificate: false,
        pendingUpdateCount: 0,
    );

    $this->command->exposeDisplayWebhookInfo($info);
})->throwsNoExceptions();
