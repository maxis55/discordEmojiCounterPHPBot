<?php


namespace Helpers;


use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;
use Illuminate\Support\Collection as IllumCollection;
use Models\AuthorModel;
use Models\EmojiModel;
use Models\MessageModel;
use Throwable;

class Helper
{
    const MESSAGE_HISTORY_LIMIT = 100;

    /**
     * @param Message         $message
     * @param IllumCollection $emojis
     * @param IllumCollection $authors
     * @param Message         $originalMessage
     */
    public static function processAllMessages(
        Message $message,
        IllumCollection $emojis,
        IllumCollection $authors,
        Message $originalMessage
    ) {
        $lastMessage = $message;

        $message->channel->getMessageHistory([
            'before' => $lastMessage,
            'limit'  => self::MESSAGE_HISTORY_LIMIT
        ])->done(function (Collection $messages) use (
            $emojis,
            $authors,
            $originalMessage
        ) {
            /**
             * @var Message $message
             */
            try {
                foreach ($messages as $message) {
                    self::processOneMessage($message, $emojis, $authors);

                    $lastMessage = $message;
                }

                $lastAmount = $messages->count();
                if (self::MESSAGE_HISTORY_LIMIT == $lastAmount
                    && isset($lastMessage)
                ) {
                    self::processAllMessages($lastMessage, $emojis, $authors,
                        $originalMessage);
                } else {
                    $originalMessage->reply('Done parsing');
                }

            } catch (Throwable $exception) {
                var_dump($exception->getMessage());
            }

        });
    }

    /**
     * @param Message                                   $message
     * @param \Illuminate\Support\Collection|Collection $emojis
     * @param \Illuminate\Support\Collection|Collection $authors
     * @param bool                                      $processGuildAndChannel
     */
    public static function processOneMessage(
        Message $message,
        $emojis = null,
        $authors = null,
        bool $processGuildAndChannel = false
    ) {
        if ($processGuildAndChannel) {
            self::processGuildAndChannel($message);
        }

        if ($message->author->bot) {
            //no need to count messages from bots
            return;
        }

        self::createAuthorIfNotExists($message->author, $authors);

        /**
         * @var  MessageModel $messageModel
         */
        $messageModel = MessageModel::updateOrCreate(
            [
                'message_id' => $message->id
            ],
            [
                'author_id'        => $message->author->id,
                'channel_id'       => $message->channel_id,
                'guild_id'         => $message->channel->guild_id,
                //saving "content" is too expensive and unnecessary
                'content'          => env('SAVE_MESSAGE_CONTENT', false)
                    ? $message->content : null,
                'type'             => $message->type,
                'edited_timestamp' => $message->edited_timestamp,
                'timestamp'        => $message->timestamp,
            ]
        );

        if (env('SAVE_ATTACHMENTS', false)) {
            $messageModel->saveAttachments($message);
        }

        $messageModel->createEmojiCounts($message, $emojis, $authors);


    }

    /**
     * @param Message $message
     */
    public static function processGuildAndChannel(Message $message)
    {
        $discoGuild = $message->channel->guild;

        /**
         * @var \Models\GuildModel $guild
         */
        $guild
            = \Models\GuildModel::updateOrCreate(
            [
                'guild_id' => $discoGuild->id,
            ],
            [
                'name'              => $discoGuild->name,
                'owner_id'          => $discoGuild->owner_id,
                'joined_at'         => $discoGuild->joined_at,
                'system_channel_id' => $discoGuild->system_channel_id,
                'icon'              => $discoGuild->icon,
                'region'            => $discoGuild->region,
                'member_count'      => $discoGuild->member_count
            ]
        );


        $discoChannel = $message->channel;

        /**
         * @var \Models\ChannelModel $channel
         */
        $channel = $guild->channels()
            ->updateOrCreate(
                [
                    'channel_id' => $discoChannel->id,
                ],
                [
                    'name'           => $discoChannel->name,
                    'type'           => $discoChannel->type,
                    'position'       => $discoChannel->position,
                    'is_private'     => $discoChannel->is_private,
                    'nsfw'           => $discoChannel->nsfw,
                    'owner_id'       => $discoChannel->owner_id,
                    'application_id' => $discoChannel->application_id,
                    'parent_id'      => $discoChannel->parent_id,
                ]);
    }

    /**
     * @param User|Member                    $author
     * @param \Illuminate\Support\Collection $authors
     */
    public static function createAuthorIfNotExists($author, $authors = null)
    {
        if (is_null($authors)) {
            $authors = AuthorModel::where('author_id', $author->id)
                ->get()->keyBy('author_id');
        }
        $authorModel = $authors->get($author->id);
        if (is_null($authorModel) || ! $authorModel->username) {
            var_dump('creating new user');
            /**
             * @var AuthorModel $authorModel
             */
            $authorModel = AuthorModel::updateOrCreate(
                [
                    'author_id' => $author->id
                ],
                [
                    'username'      => $author->username,
                    'avatar'        => $author->avatar,
                    'discriminator' => $author->discriminator,
                    'bot'           => $author->bot,
                    'system'        => $author->system,
                    'mfa_enabled'   => $author->mfa_enabled,
                    'locale'        => $author->locale,
                    'verified'      => $author->verified,
                    'email'         => $author->email,
                    'flags'         => $author->flags,
                    'premium_type'  => $author->premium_type,
                    'public_flags'  => $author->public_flags,
                ]

            );

            $authors->put($authorModel->author_id, $authorModel);
        }
    }

    /**
     * @param $emojiRanked
     *
     * @return string
     */
    public static function getRankedEmojis($emojiRanked): string
    {
        $answer = 'Ranking is as follows:' . PHP_EOL;
        foreach ($emojiRanked as $key => $item) {
            $answer .= ($key + 1) . '. '
                . EmojiModel::combineEmoji($item->name, $item->emoji_id)
                . ' => '
                . $item->emoji_count . PHP_EOL;
        }

        return $answer;
    }

    /**
     * @param Message $message
     *
     * @return bool
     */
    public static function authorIsAdmin(Message $message): bool
    {
        if ($message->channel->guild->owner_id == $message->author->id) {
            return true;
        }
        foreach ($message->author->roles as $role) {
            if ($role->permissions->administrator) {
                return true;
            }
        }

        return false;
    }


}