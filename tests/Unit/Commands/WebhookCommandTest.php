<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\TgApi\Types\DTO\WebhookInfoTypeDTO;
use BAGArt\TelegramBotBasic\Commands\WebhookCommand;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    $this->command = new class () extends WebhookCommand {
        public function exposeResolveBotName(
            TgBotApiDTOClientContract $client,
            string $token,
        ): string {
            return $this->resolveBotName($client, $token);
        }

        public function exposeDisplayWebhookInfo(WebhookInfoTypeDTO $info, \BAGArt\TelegramBot\TgIntegration\WebhookManager $webhookManager): void
        {
            $this->displayWebhookInfo($info, $webhookManager);
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
        ->with(Mockery::type(TgBotConfig::class), Mockery::any())
        ->andReturn(new \BAGArt\TelegramBot\Http\Pure\TgApiResponse(true, [], $user));

    $name = $this->command->exposeResolveBotName($client, '123456789:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

    expect($name)->toBe('@mybot');
});

test('resolveBotName returns firstName when no username', function () {
    $client = Mockery::mock(TgBotApiDTOClientContract::class);
    $user = new UserTypeDTO(id: '1', isBot: true, firstName: 'MyBot', username: '');
    $client->shouldReceive('request')
        ->with(Mockery::type(TgBotConfig::class), Mockery::any())
        ->andReturn(new \BAGArt\TelegramBot\Http\Pure\TgApiResponse(true, [], $user));

    $name = $this->command->exposeResolveBotName($client, '123456789:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

    expect($name)->toBe('MyBot');
});

test('resolveBotName returns unknown on error', function () {
    $client = Mockery::mock(TgBotApiDTOClientContract::class);
    $client->shouldReceive('request')
        ->with(Mockery::type(TgBotConfig::class), Mockery::any())
        ->andThrow(new RuntimeException('Network error'));

    $name = $this->command->exposeResolveBotName($client, '123456789:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

    expect($name)->toBe('unknown');
});
