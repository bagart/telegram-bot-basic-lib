<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Traits;

use BAGArt\TelegramBot\ApiCommunication\Exceptions\TgApiCommunicationException;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Exceptions\TgApiUserBreakException;
use BAGArt\TelegramBot\TgApi\Methods\DTO\GetUpdatesMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\Wrappers\TgBotLogWrapper;
use Illuminate\Console\Command;

/** @mixin Command */
trait LongPollingCommandTrait
{
    protected bool $keepRunning = true;
    protected int $barInterval = 1;

    /**
     * @props $fnRetry fn retry count befor throw
     */
    private function longPolling(
        TgBotApiDTOClientContract $tgDTOClient,
        TgBotLogWrapper $logger,
        string $token,
        callable $fn,
        array $allowedUpdates = ['message', 'callback_query', 'edited_channel_post'],
        int $fnRetry = 1,
        bool $noAck = false,
        int $timeout = 30,
        int $limit = 100,
        int $offset = 0,
        float|int $delayOnFn = 0,
    ): int {
        $delayOnErr = 5;
        $this->output->info('Telegram bot started. Press Ctrl+C to stop.');

        $this->trap(SIGINT, function (): void {
            $this->keepRunning = false;
            $this->output->newLine();
            $this->output->info('Stopping...');

            throw new TgApiUserBreakException(GetUpdatesMethodDTO::tgApiEntity()->name);
        });
        $bar = $this->output->createProgressBar($limit);
        $updateCount = 0;
        $lastId = 0;
        $alreadyWarnAbounFullBuffer = false;
        try {
            while ($this->keepRunning) {
                try {
                    $getUpdatesResponse = $tgDTOClient->request(
                        $token,
                        new GetUpdatesMethodDTO(
                            offset: $noAck ? 0 : $lastId,
                            limit: $limit,
                            timeout: $timeout,
                            allowedUpdates: $allowedUpdates,
                        )
                    );
                } catch (TgApiUserBreakException $e) {
                    return static::FAILURE;
                } catch (TgApiCommunicationException $e) {
                    $msg = 'Tg Api Connection '.$e::class." while LongPolling: {$e->getMessage()}";
                    $logger->info($msg, [
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                    $this->output->error($msg);
                    usleep($delayOnErr * 1000 * 1000);
                    $delayOnErr = min((int)($delayOnErr * 1.2 + 0.5), 30);
                }

                $lastUpdateCount = $updateCount;
                foreach ($getUpdatesResponse?->result ?? [] as $update) {
                    if ($update->updateId < $lastId) {
                        continue;
                    }
                    ++$updateCount;
                    if ($this->barInterval && ($updateCount % $this->barInterval) === 0) {
                        $bar->setMaxSteps($updateCount + $limit * 10);
                        $bar->advance($this->barInterval);
                    }
                    assert($update instanceof UpdateTypeDTO);
                    if ($fn) {
                        $tries = $fnRetry;
                        while ($tries-- > 0) {
                            try {
                                $this->keepRunning = $fn($update, $updateCount)
                                    ?? $this->keepRunning;
                                $tries = 0;
                                if ($delayOnFn > 0) {
                                    usleep((int)($delayOnFn * 1000 * 1000));
                                }
                            } catch (TgApiUserBreakException $e) {
                                return static::FAILURE;
                            } catch (TgApiCommunicationException $e) {
                                $msg = 'Tg Api Connection '.$e::class
                                    ." while run Response Reaction: {$e->getMessage()}";
                                $logger->warning($msg, [
                                    'exception' => $e::class,
                                    'message' => $e->getMessage(),
                                    'trace' => str_replace(
                                        base_path().'/',
                                        '',
                                        "{$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}"
                                    ),
                                ]);
                                $this->output->error($msg);
                                usleep($delayOnErr * 1000 * 1000);
                                $delayOnErr = min((int)($delayOnErr * 1.2 + 0.5), 30);
                            } catch (\Throwable $e) {
                                if ($noAck) {
                                    throw $e;
                                }
                                $msg = 'Tg Response Reaction '.$e::class." : {$e->getMessage()}";
                                $logger->error($msg, [
                                    'exception' => $e::class,
                                    'message' => $e->getMessage(),
                                    'trace' => str_replace(
                                        base_path().'/',
                                        '',
                                        "{$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}"
                                    ),
                                ]);
                                $this->output->error($msg);
                                usleep($delayOnErr * 1000 * 1000);
                                $delayOnErr = min((int)($delayOnErr * 1.2 + 0.5), 30);
                            }
                        }
                    }

                    $lastId = max($lastId, $update->updateId + 1);
                }

                if (
                    $noAck
                    && count($getUpdatesResponse?->result ?? []) >= $limit
                    && $lastUpdateCount === $updateCount
                ) {
                    if (!$alreadyWarnAbounFullBuffer) {
                        $this->warn('Buffer is full(New messages will delivery after ack queue).');
                        $alreadyWarnAbounFullBuffer = true;
                    }
                } else {
                    $alreadyWarnAbounFullBuffer = false;
                }

                if ($noAck) {
                    usleep((int)(2 * 1000 * 1000));
                } else {
                    $delayOnErr = max((int)($delayOnErr * 0.9), 5);
                    usleep((int)(0.5 * 1000 * 1000));
                }
            }

            return self::SUCCESS;
        } catch (TgApiUserBreakException $e) {
            return self::FAILURE;
        }
    }
}
