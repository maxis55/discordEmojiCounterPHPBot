<?php


namespace Helpers;


use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Illuminate\Support\Collection as IllumCollection;
use Models\AttachmentModel;
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
        var_dump('doing this message. ' . $lastMessage->id);
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
                    self::processOneMessage($message, $authors);

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
     * @param \Illuminate\Support\Collection|Collection $authors
     * @param bool                                      $processGuildAndChannel
     */
    public static function processOneMessage(
        Message $message,
        $authors = null,
        bool $processGuildAndChannel = false
    ) {
        if ($processGuildAndChannel) {
            self::processGuildAndChannel($message);
        }

        if (is_null($authors)) {
            $authors = AuthorModel::where('author_id', $message->author->id)
                ->get()->keyBy('author_id');
        }
        $authorModel = $authors->get($message->author->id);
        if (is_null($authorModel) || ! $authorModel->username) {
            /**
             * @var AuthorModel $authorModel
             */
            $authorModel = AuthorModel::updateOrCreate(
                [
                    'author_id' => $message->author->id
                ],
                [
                    'username'      => $message->author->username,
                    'avatar'        => $message->author->avatar,
                    'discriminator' => $message->author->discriminator,
                    'bot'           => $message->author->bot,
                    'system'        => $message->author->system,
                    'mfa_enabled'   => $message->author->mfa_enabled,
                    'locale'        => $message->author->locale,
                    'verified'      => $message->author->verified,
                    'email'         => $message->author->email,
                    'flags'         => $message->author->flags,
                    'premium_type'  => $message->author->premium_type,
                    'public_flags'  => $message->author->public_flags,
                ]

            );

            $authors->put($authorModel->author_id, $authorModel);
        }

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
                'content'          => $message->content,
                'type'             => $message->type,
                'edited_timestamp' => $message->edited_timestamp,
                'timestamp'        => $message->timestamp,
            ]
        );

        $attachmentsParsed = array_map(function ($attachment) {
            return new AttachmentModel([
                'attachment_id' => $attachment->id,
                'width'         => $attachment->width ?? null,
                'url'           => $attachment->url,
                'proxy_url'     => $attachment->proxy_url,
                'height'        => $attachment->height ?? null,
                'filename'      => $attachment->filename,
                'content_type'  => $attachment->content_type ?? null,
                'size'          => $attachment->size ?? null,
            ]);
        }, $message->attachments);

        $messageModel->attachments()->delete();
        $messageModel->attachments()->saveMany($attachmentsParsed);

        $messageModel->createEmojiCounts($emojis);

        if ($message->reactions->count() > 0) {
            /**
             * @var \Discord\Parts\Channel\Reaction $reaction
             */
            foreach ($message->reactions as $reaction) {


                $reaction->getUsers()->then(function ($users) use ($reaction) {
                    echo 'message.' . $reaction->message_id . PHP_EOL;
                    echo 'message.emoji.' . $reaction->emoji->name . PHP_EOL;
                    /**
                     * @var \Discord\Parts\User\User $user
                     */
                    foreach ($users as $user) {
                        echo 'user.' . $user->username . PHP_EOL;
                    }
                    echo PHP_EOL;

                });
            }
        }

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