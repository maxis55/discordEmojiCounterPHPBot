<?php

use Discord\Discord;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Illuminate\Database\Capsule\Manager as Capsule;
use Models\EmojiModel;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory;
use React\Http\Browser;

require __DIR__ . '/vendor/autoload.php';
require_once dirname(__FILE__) . '/Constants/Constants.php';
require_once dirname(__FILE__) . '/Helpers/Helper.php';
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
    'driver'    => 'mysql',
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


const PREFIX = '%%';
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
    'token'          => $_ENV['BOT_TOKEN'],
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


        if ($message->content == PREFIX . 'testReactions') {
            $message->channel->getMessageHistory([
                'before' => $message,
                'limit'  => LIMIT
            ])->done(function (Collection $messages)  {
                /**
                 * @var Message $message
                 */
                foreach ($messages as $message){
                    if($message->reactions->count()>0){
                        /**
                         * @var \Discord\Parts\Channel\Reaction $reaction
                         */
                        foreach ($message->reactions as $reaction){
                            echo 'message.'.$reaction->message_id;
                            echo 'message.emoji.'.$reaction->emoji;
                            var_dump($reaction->message_id);
                            var_dump($reaction->getUsers()->then(function ($el){
                                var_dump($el);
                            }));
//                         var_dump($reaction);
                        }
//                       $message->getReactionsTest()->done(function ($res){
//                           var_dump($res);
//                       });
//                        var_dump('creating reaction collector '.$message->content);
//                        var_dump($message->reactions);
//                        $message->createReactionCollector(function (MessageReaction $reaction) {
//                            echo 'getting reaction'.PHP_EOL;
//                            var_dump($reaction);
//                            // return true or false depending on whether you want the reaction to be collected.
//                            return true;
//                        }, [
////                            'time' => false,
//                            'limit' => 10,
//                        ])->done(function (Collection $reactions) {
//                            var_dump($reactions);
//                            foreach ($reactions as $reaction) {
//                                // ...
//                            }
//                        })->;
                    }
                }
            });
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
                        ->get()->chunk(20);//chunk by 20 or it wont fit into 1 message and fail


                    foreach ($emojiRankedChunks as $emojiRankedChunk) {
                        $message->reply(\Helpers\Helper::getRankedEmojis($emojiRankedChunk));
                    }

                });
            } catch (Throwable $exception) {
                var_dump($exception->getMessage());
            }


        }

        if (strtolower($message->content) == PREFIX . 'dance') {

            if (!\Helpers\Helper::isAdmin($message->author)) {
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

        if ($message->content == PREFIX . 'rankByGuild') {
            $guildId     = $message->channel->guild_id;
            $emojiRanked = \Models\EmojiUsedModel::emojisByGuild($guildId)
                ->orderBy('emoji_count', 'DESC')
                ->where('emoji_used.author_id', '!=', $discord->id)
                ->limit(LIMIT)
                ->get();


            $message->reply(\Helpers\Helper::getRankedEmojis($emojiRanked));
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
            $message->reply($answer);
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

            $message->reply(\Helpers\Helper::getRankedEmojis($emojiRanked));
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

            $message->reply(\Helpers\Helper::getRankedEmojis($emojiRanked));
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

            $message->reply(\Helpers\Helper::getRankedEmojis($emojiRanked));
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

        $temp = null;
        \Helpers\Helper::processOneMessage($message, $temp, true);
    });

$discord->run();