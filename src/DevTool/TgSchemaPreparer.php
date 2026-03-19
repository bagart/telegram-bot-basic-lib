<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\DevTool;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class TgSchemaPreparer
{
    public function __construct(public ?string $javaScript = null)
    {
        $this->javaScript = $javaScript ?: __DIR__.'/json-schema-updater.js';
    }

    public function prepare(string $json): string
    {
        $command = "node {$this->javaScript} > $json";
        $result = Process::run($command);
        throw_unless(
            $result->successful(),
            RuntimeException::class,
            "[ERROR] Unable to prepare actual Json Schema: {$result->errorOutput()}"
        );

        return $result->output();
    }
}
