<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\Commands\Demo;

use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendPollMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\Enum\SendPollPropTypeEnum;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\text;

class DemoSendPollCommand extends Command
{
    protected $signature = 'tg:method:sendPoll
                            {token : Telegram Bot Token}
                            {chat_id : Target chat ID}
                            {question : Poll question}
                            {--quiz : Create quiz instead of poll}
                            {--anonymous : Anonymous poll}
                            {--multiple : Allow multiple answers}';

    protected $description = 'Send a poll or quiz to a chat';

    public function handle(TgBotApiDTOClientContract $tgDTOClient): int
    {
        $token = $this->argument('token');
        $chatId = $this->argument('chat_id');
        $question = $this->argument('question');

        $answers = [];
        do {
            $answer = text(
                label: empty($answers) ? 'First answer option' : 'Next answer option (leave empty to finish)',
                placeholder: 'Type answer...',
                required: empty($answers),
            );
            if ($answer !== null && $answer !== '') {
                $answers[] = trim($answer);
            }
        } while ($answer !== null && $answer !== '');

        if (count($answers) < 2) {
            $this->error('At least 2 answers required.');

            return self::FAILURE;
        }

        $isQuiz = $this->option('quiz');
        $anonymous = $this->option('anonymous');
        $multiple = $this->option('multiple');

        try {
            $this->info('Sending poll...');
            $response = $tgDTOClient->request(
                $token,
                new SendPollMethodDTO(
                    chatId: $chatId,
                    question: $question,
                    options: $answers,
                    isAnonymous: $anonymous ?? !$isQuiz,
                    type: $isQuiz ? SendPollPropTypeEnum::QUIZ : SendPollPropTypeEnum::REGULAR,
                    allowsMultipleAnswers: $multiple,
                ),
            );

            $message = $response->result;
            assert($message instanceof MessageTypeDTO);

            $this->info('✅ Poll sent successfully!');
            $this->line("📍 Message ID: {$message->messageId}");
            $this->line('📋 Type: '.($isQuiz ? 'Quiz' : 'Poll'));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("❌ Error: {$e->getMessage()}; ".$e::class);

            return self::FAILURE;
        }
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'chat_id' => 'Which chat ID for making new Poll?',
        ];
    }
}
