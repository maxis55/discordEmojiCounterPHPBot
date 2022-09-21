<?php

use Discord\Discord;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Helpers\Emoji;
use Helpers\EmojiHelper;
use Illuminate\Database\Capsule\Manager as Capsule;
use Models\EmojiModel;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory;
use React\Http\Browser;
use Constants\Constants;

require __DIR__ . '/vendor/autoload.php';
require_once dirname(__FILE__) . '/Constants/Constants.php';
require_once dirname(__FILE__) . '/Helpers/Helper.php';
require_once dirname(__FILE__) . '/Helpers/Emoji.php';
require_once dirname(__FILE__) . '/Helpers/EmojiHelper.php';
require_once dirname(__FILE__) . '/Models/GuildModel.php';
require_once dirname(__FILE__) . '/Models/ChannelModel.php';
require_once dirname(__FILE__) . '/Models/MessageModel.php';
require_once dirname(__FILE__) . '/Models/AuthorModel.php';
require_once dirname(__FILE__) . '/Models/EmojiModel.php';
require_once dirname(__FILE__) . '/Models/EmojiUsedModel.php';
require_once dirname(__FILE__) . '/Models/AttachmentModel.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => env('DB_DRIVER', 'mysql'),
    'host'      => env('DB_HOST'),
    'port'      => env('DB_PORT', '3306'),
    'database'  => env('DB_DATABASE'),
    'username'  => env('DB_USERNAME'),
    'password'  => env('DB_PASSWORD'),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();


const PREFIX = Constants::PREFIX;
const LIMIT  = 20;

$loop = Factory::create();

$browser = new Browser($loop);
$logger  = new Logger('disco_logger');
$logger->pushHandler(
    new StreamHandler(
        __DIR__ . '/logs/full.log',
        Logger::DEBUG
    )
);

$discord = new Discord([
    'token'          => env('BOT_TOKEN'),
    'loadAllMembers' => false,
    'loop'           => $loop,
    'logger'         => $logger
]);


$discord->once('ready', function (Discord $discord) {
    $activity = new \Discord\Parts\User\Activity($discord, [
        'type' => \Discord\Parts\User\Activity::TYPE_PLAYING,
        'name' => 'with prefix ' . PREFIX
    ]);
    $discord->updatePresence($activity);
});

$discord->on('message',
    function (Message $message, Discord $discord) use ($browser, $logger) {


        if (0 === strpos($message->content, PREFIX . 'test')
            && ! $message->author->bot
        ) {
            $emoji  = '🖖🏻';
            $emoji2 = '🖖';
//            $emoji2 = '🫀';
//            $emoji3 = '🤱';
            $arr[] = $emoji;
            $arr[] = Emoji::Encode($emoji);
            $arr[] = $emoji2;
            $arr[] = Emoji::Encode($emoji2);
//var_dump($arr);
            foreach ($arr as $item) {
//                $message->reply($item);
            }
            EmojiHelper::possibleDecodingViaIntl($message);

            /**
             * @var \Discord\Parts\Channel\Channel $channelTEst
             */
            $specialSymbol='?';
            $channelTEst=$discord->guilds->offsetGet('484796051698483211')->channels->offsetGet('825852741897027644');
            $messageTest=$channelTEst->getMessage('830945293049921598')->then(function (Message $message){
                var_dump($message->content);
            });
//            var_dump($messageTest);
//            var_dump($messageTest);
//            $message->channel->getMessageHistory(([
//                'before' => $message,
//                'limit'  => self::MESSAGE_HISTORY_LIMIT
//            ])->done(function (Collection $messages) use (
//                $emojis,
//                $authors,
//                $originalMessage
//            ) {)


        }

        if ($message->content == PREFIX . 'rankByGuildActive') {
            try {
                $message->channel->guild->emojis->freshen()->done(function (
                    \Discord\Repository\Guild\EmojiRepository $emojiRepository
                ) use ($message, $discord, $logger) {
                    $guildEmojiIds = $emojiRepository->map(function ($el) {
                        return $el->id;
                    })->toArray();
                    $logger->info('emojis:' . implode(',', $guildEmojiIds));

                    $guildId = $message->channel->guild_id;
                    $emojiRankedChunks
                             = \Models\EmojiUsedModel::emojisByGuild($guildId)
                        ->orderBy('emoji_count', 'DESC')
                        ->where('emoji_used.author_id', '!=', $discord->id)
                        ->whereIn('emojis.emoji_id', $guildEmojiIds)
                        ->get()
                        ->chunk(20);//chunk by 20 or it wont fit into 1 message and fail


                    foreach ($emojiRankedChunks as $emojiRankedChunk) {
                        $message->channel->sendMessage(\Helpers\Helper::getRankedEmojis($emojiRankedChunk));
                    }

                });
            } catch (Throwable $exception) {
                var_dump($exception->getMessage());
            }
        }

        if (substr($message->content, 0, strlen(Constants::RBGAT)) == Constants::RBGAT) {

            try {
                $periods = trim(str_replace(Constants::RBGAT, '', $message->content));
                $periods = explode('_', $periods, 2);

                $start = \Illuminate\Support\Carbon::parse($periods[0]);
                $end = \Illuminate\Support\Carbon::parse($periods[1]);

                $message->reply('Going to give ranking for period from '.$start->toAtomString().' to '.$end->toAtomString());

                $message->channel->guild->emojis->freshen()->done(function (
                    \Discord\Repository\Guild\EmojiRepository $emojiRepository
                ) use ($message, $discord, $logger, $start, $end) {
                    $guildEmojiIds = $emojiRepository->map(function ($el) {
                        return $el->id;
                    })->toArray();
                    $logger->info('emojis:' . implode(',', $guildEmojiIds));

                    $guildId = $message->channel->guild_id;
                    $emojiRankedChunks
                        = \Models\EmojiUsedModel::emojisByGuild($guildId)
                        ->orderBy('emoji_count', 'DESC')
                        ->where('emoji_used.author_id', '!=', $discord->id)
                        ->whereIn('emojis.emoji_id', $guildEmojiIds)
                        ->join('messages', 'messages.message_id', 'emoji_used.message_id')
                        ->whereBetween('messages.timestamp', [$start, $end])
                        ->get()
                        ->chunk(20);//chunk by 20 or it wont fit into 1 message and fail


                    foreach ($emojiRankedChunks as $emojiRankedChunk) {
                        $message->channel->sendMessage(\Helpers\Helper::getRankedEmojis($emojiRankedChunk));
                    }

                });
            } catch (Throwable $exception) {
                var_dump($exception->getMessage());
                $message->reply($exception->getMessage());
            }
        }


        if (strtolower($message->content) == PREFIX . 'dance') {


            if ( ! \Helpers\Helper::authorIsAdmin($message)) {
                $message->reply('Nope.');

                return;
            }

            \Helpers\Helper::processGuildAndChannel($message);

            $emojis  = new \Illuminate\Support\Collection();
            $authors = new \Illuminate\Support\Collection();


            \Helpers\Helper::processAllMessages($message, $emojis, $authors,
                $message);

            $message->reply('K');
        }

        if ($message->content == PREFIX . 'danceInEveryChannel') {

            if ( ! \Helpers\Helper::authorIsAdmin($message)) {
                $message->reply('Nope.');

                return;
            }
            $message->reply('Fuck. OK');

            \Helpers\Helper::processGuildAndChannel($message);

            $guild = $message->channel->guild;

            /**
             * @var \Discord\Parts\Channel\Channel $discoChannel
             */
            foreach ($guild->channels as $discoChannel) {
                if ($discoChannel->type == 4) { //channel section
                    continue;
                }
                $message->channel->sendMessage('Started doing channel '.$discoChannel->name);
                try {
                    $emojis = new \Illuminate\Support\Collection();
                    $authors = new \Illuminate\Support\Collection();


                    \Helpers\Helper::processAllMessages($message, $emojis, $authors,
                        $message, $discoChannel);
                } catch (Throwable $exception) {
                    $message->reply('Cant process channel '.$discoChannel->name. ' because '.$exception->getMessage());
                }
            }

            $message->reply('Doing all '.$guild->channels->count().' channels');
        }

        if ($message->content == PREFIX . 'rankByGuild') {
            $guildId     = $message->channel->guild_id;
            $emojiRanked = \Models\EmojiUsedModel::emojisByGuild($guildId)
                ->orderBy('emoji_count', 'DESC')
                ->where('emoji_used.author_id', '!=', $discord->id)
                ->limit(LIMIT)
                ->get();


            $message->channel->sendMessage(\Helpers\Helper::getRankedEmojis($emojiRanked));
        }

        if ($message->content == PREFIX . 'rankByChannel') {
            $channelId   = $message->channel_id;
            $guildId     = $message->channel->guild_id;
            $emojiRanked = \Models\EmojiUsedModel::emojisByGuild($guildId)
                ->orderBy('emoji_count', 'DESC')
                ->where('emoji_used.channel_id', $channelId)
                ->where('emoji_used.author_id', '!=', $discord->id)
                ->limit(LIMIT)
                ->get();

            $answer = 'Ranking is as follows:' . PHP_EOL;
            foreach ($emojiRanked as $key => $item) {
                $answer .= ($key + 1) . '. '
                    . EmojiModel::combineEmoji($item->name, $item->emoji_id)
                    . ' => '
                    . $item->emoji_count . PHP_EOL;
            }
            $message->channel->sendMessage($answer);
        }

        if ($message->content == PREFIX . 'rankByChannelBottom10') {
            $channelId   = $message->channel_id;
            $guildId     = $message->channel->guild_id;
            $emojiRanked = \Models\EmojiUsedModel::emojisByGuild($guildId)
                ->orderBy('emoji_count', 'ASC')
                ->where('emoji_used.channel_id', $channelId)
                ->where('emoji_used.author_id', '!=', $discord->id)
                ->limit(10)
                ->get();

            $message->channel->sendMessage(\Helpers\Helper::getRankedEmojis($emojiRanked));
        }

        if ($message->content == PREFIX . 'rankByAuthor') {
            $guildId     = $message->channel->guild_id;
            $authorId    = $message->author->id;
            $emojiRanked = \Models\EmojiUsedModel::emojisByGuild($guildId)
                ->orderBy('emoji_count', 'DESC')
                ->where('emoji_used.author_id', $authorId)
                ->where('emoji_used.author_id', '!=', $discord->id)
                ->limit(LIMIT)
                ->get();

            $message->channel->sendMessage(\Helpers\Helper::getRankedEmojis($emojiRanked));
        }




        if (substr($message->content, 0, strlen(Constants::RBAT)) == Constants::RBAT) {

            try {
                $periods = trim(str_replace(Constants::RBAT, '', $message->content));
                $periods = explode('_', $periods, 2);

                $start = \Illuminate\Support\Carbon::parse($periods[0]);
                $end = \Illuminate\Support\Carbon::parse($periods[1]);
                $message->reply('Going to give ranking for period from '.$start->toAtomString().' to '.$end->toAtomString());
                $guildId = $message->channel->guild_id;
                $authorId = $message->author->id;
                $emojiRanked = \Models\EmojiUsedModel::emojisByGuild($guildId)
                    ->orderBy('emoji_count', 'DESC')
                    ->where('emoji_used.author_id', $authorId)
                    ->where('emoji_used.author_id', '!=', $discord->id)
                    ->join('messages', 'messages.message_id', 'emoji_used.message_id')
                    ->whereBetween('messages.timestamp', [$start, $end])
                    ->limit(LIMIT)
                    ->get();

                $message->channel->sendMessage(\Helpers\Helper::getRankedEmojis($emojiRanked));
            } catch (Throwable $exception) {
                $message->reply('Cant process because '.$exception->getMessage());
            }

        }

        if ($message->content == PREFIX . 'rankByAuthorLimitless') {
            $guildId     = $message->channel->guild_id;
            $authorId    = $message->author->id;
            $emojiRanked = \Models\EmojiUsedModel::emojisByGuild($guildId)
                ->orderBy('emoji_count', 'DESC')
                ->where('emoji_used.author_id', $authorId)
                ->where('emoji_used.author_id', '!=', $discord->id)
                ->get()
                ->chunk(20);

            foreach ($emojiRanked as $chunk){
                $message->channel->sendMessage(\Helpers\Helper::getRankedEmojis($chunk));
            }
        }

        if ($message->content == PREFIX . 'rankByAuthorAndChannel') {
            $channelId   = $message->channel_id;
            $guildId     = $message->channel->guild_id;
            $authorId    = $message->author->id;
            $emojiRanked = \Models\EmojiUsedModel::emojisByGuild($guildId)
                ->orderBy('emoji_count', 'DESC')
                ->where('emoji_used.author_id', $authorId)
                ->where('emoji_used.channel_id', $channelId)
                ->where('emoji_used.author_id', '!=', $discord->id)
                ->limit(LIMIT)
                ->get();

            $message->channel->sendMessage(\Helpers\Helper::getRankedEmojis($emojiRanked));
        }

        if (substr($message->content, 0, strlen(Constants::RBAACT)) == Constants::RBAACT) {
            try{
                $periods = trim(str_replace(Constants::RBAACT, '', $message->content));
                $periods = explode('_', $periods, 2);

                $start = \Illuminate\Support\Carbon::parse($periods[0]);
                $end = \Illuminate\Support\Carbon::parse($periods[1]);
                $message->reply('Going to give ranking for period from '.$start->toAtomString().' to '.$end->toAtomString());
                $channelId   = $message->channel_id;
                $guildId     = $message->channel->guild_id;
                $authorId    = $message->author->id;
                $emojiRanked = \Models\EmojiUsedModel::emojisByGuild($guildId)
                    ->orderBy('emoji_count', 'DESC')
                    ->join('messages', 'messages.message_id', 'emoji_used.message_id')
                    ->whereBetween('messages.timestamp', [$start, $end])
                    ->where('emoji_used.author_id', $authorId)
                    ->where('emoji_used.channel_id', $channelId)
                    ->where('emoji_used.author_id', '!=', $discord->id)
                    ->limit(LIMIT)
                    ->get();

                $message->channel->sendMessage(\Helpers\Helper::getRankedEmojis($emojiRanked));
            } catch (Throwable $exception) {
                $message->reply('Cant process because '.$exception->getMessage());
            }

        }

        if (strtolower($message->content) == PREFIX . 'help') {
            $message->reply('Nobody will help you.');
        }

        if (strtolower($message->content) == PREFIX . 'joke') {
            $browser->get('https://official-joke-api.appspot.com/jokes/random')
                ->then(function (ResponseInterface $response) use ($message) {
                    $joke      = json_decode($response->getBody());
                    $setUp     = $joke->setup;
                    $punchline = $joke->punchline;
                    $response  = $setUp . PHP_EOL . "||" . $punchline . "||";

                    $message->reply($response);
                });
        }

        if (strtolower($message->content) == PREFIX . 'd20') {
            $message->reply('Your number is ' . random_int(1, 20));
        }

        if (strtolower($message->content) == PREFIX . 'true?') {
            $message->reply('Your answer is ' . (random_int(0, 1) ? 'true'
                    : 'false'));
        }

        //process message and add to DB

        \Helpers\Helper::processOneMessage($message, null, null, true);
    });

$discord->run();