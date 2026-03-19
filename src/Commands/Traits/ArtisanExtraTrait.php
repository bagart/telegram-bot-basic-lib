<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Traits;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** @mixin Command */
trait ArtisanExtraTrait
{
    protected bool $debug = false;

    protected function dbg(string|array|null|\Throwable $dbg): void
    {
        if ($dbg instanceof \Throwable) {
            $dbg = $dbg::class.": {$dbg->getMessage()} "
                ."at\n{$dbg->getFile()}:{$dbg->getLine()}\n{$dbg->getTraceAsString()}";
            $dbg = str_replace(base_path().'/', '', $dbg);
        } elseif (is_string($dbg)) {
            $dbg = trim($dbg);
        }
        if (!$dbg && !is_numeric($dbg)) {
            return;
        }
        if (!$this->debug && $this->hasOption('dbg')) {
            $this->debug = $this->option('dbg');
        }
        if (is_array($dbg)) {
            Log::debug(json_encode($dbg));
            if ($this->debug) {
                $this->line('[DBG]');
                dump($dbg);
                $this->line('[/DBG]');
            }
        } else {
            Log::debug($dbg);
            if ($this->debug) {
                $this->line('    [DBG]'.str_replace("\n", "\n    [DBG] ", $dbg));
            }
        }
    }

    protected function prepareOptions(array $entities, array $allPossible): array
    {
        if ($entities == ['*']) {
            return $allPossible;
        }

        $allPossible = array_fill_keys($allPossible, true);
        $errors = [];
        foreach ($entities as $key => $option) {
            if (empty($option)) {
                unset($entities[$key]);

                continue;
            }
            throw_if(
                $option == '*',
                '[ERROR] Unsupported option values: * and other: ['.implode(', ', $entities).']'
            );
            if (!isset($allPossible[$option])) {
                $errors[$option] = $option;
            }
        }
        throw_if($errors, '[ERROR] Unsupported option values: ['.implode(', ', $errors).']');

        return $entities === [] ? array_keys($allPossible) : array_values($entities);
    }
}
