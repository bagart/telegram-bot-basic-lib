<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands;

use BAGArt\TelegramBotBasic\Commands\Traits\ArtisanExtraTrait;
use BAGArt\TelegramBotBasic\DevTool\DTOGenerator;
use BAGArt\TelegramBotBasic\DevTool\TgLibUpdater;
use BAGArt\TelegramBotBasic\DevTool\TgSchemaPreparer;
use Illuminate\Console\Command;

class TgDevDTOActualizeCommand extends Command
{
    use ArtisanExtraTrait;

    public const string STEP_LIB = 'lib';

    public const string STEP_SCHEMA = 'schema';

    public const string STEP_DTO = 'dto';

    protected $signature = 'tg:dev:dto:actualize
                            {--steps=*lib,schema,dto : Step of work to do: LibUp->SchemaPrepare->DTOGenerate. Options: lib,schema,dto }
                            {--schema=misc/BAGArt/telegram-bot-lib/tg-bots-api.json : File of JSON Schema}
                            {--full : Full re-gen: delete full path with dto/enum before generate }
                            {--debug : Debug output}';

    protected $description = 'Tg Bot Dev Tool: Actualize DTO';

    public function handle(
        DTOGenerator $dtoGenerator,
        TgSchemaPreparer $schemaPreparer,
        TgLibUpdater $tgLivUpdater,
    ): int {
        $steps = $this->prepareOptions($this->option('steps'), [
            static::STEP_LIB,
            static::STEP_SCHEMA,
            static::STEP_DTO,
        ]);

        $schema = base_path($this->option('schema'));
        $this->debug = $this->option('debug');
        $full = $this->option('full');

        $this->line(
            "{$this->description}:\n"
            .implode(
                ' ',
                array_filter([
                    '[STEPS='.implode(',', $steps).']',
                    $full ? '[FULL]' : null,
                    $this->debug ? '[DBG]' : null,
                ])
            )
            ."\nschema=$schema"
        );

        $this->newLine();
        if (in_array(static::STEP_LIB, $steps, true)) {
            $this->line('[STEP 1] Update Tg Lib');
            $output = $tgLivUpdater->update();
            $this->dbg($output);
        }

        if (in_array(static::STEP_SCHEMA, $steps, true)) {
            $this->line("[STEP 2] Load actual schema: $schema");
            $before = round(filesize($schema) / 1024).'kb';
            $output = $schemaPreparer->prepare($schema);
            $this->dbg($output ?: 'Empty oputput');
            $this->line("   $before => ".round(filesize($schema) / 1024).'kb');
        }

        if (in_array(static::STEP_DTO, $steps, true)) {
            $this->line("[STEP 3] Generate DTO: $schema".($full ? '; FULL' : null));
            $dtoGenerator->jsonPath = $schema;
            $dtoGenerator->full = $full;
            $result = $dtoGenerator->generate();
            $this->newLine();
            $this->dbg($result);

            foreach ($result as $action => $filesByType) {
                foreach ($filesByType as $type => $files) {
                    $result[$action][$type] = count($files);
                }
            }
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
        }

        $this->line('Done');

        return self::SUCCESS;
    }
}
