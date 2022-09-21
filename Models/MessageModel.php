<?php

namespace Models;

use Discord\Parts\Channel\Message;
use Helpers\Helper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Class MessageModel
 *
 * @property string $message_id
 * @property string $channel_id
 * @property string $guild_id
 * @property string $author_id
 * @property string $content
 *
 * @package Models
 */
class MessageModel extends Model
{
    public $incrementing = false;
    public $timestamps=false;
    protected $primaryKey = 'message_id';
    protected $table = 'messages';
    protected $fillable
        = [
            'message_id',
            'content',
            'type',
            'author_id',
            'channel_id',
            'guild_id',
            'edited_timestamp',
            'timestamp',
            'flags'
        ];

    /**
     * @param Message|null    $message
     * @param Collection|null $emojisModels
     * @param Collection|null $authors
     */
    public function createEmojiCounts(
        Message $message,
        Collection $emojisModels = null,
        Collection $authors = null
    ) {
        if (is_null($emojisModels)) {
            $emojisModels = new Collection();
        }
        $emojis = [];
        if (false != preg_match_all(
                EmojiModel::EMOJI_REGEX,
                $message->content,
                $emojis
            )
        ) {
            $this->emojisUsed()->delete();
            foreach (reset($emojis) as $emoji) {

                $singleEmojiMatches = [];
                preg_match(
                    EmojiModel::EMOJI_NAME_REGEX,
                    $emoji,
                    $singleEmojiMatches
                );
                $emojiName = $singleEmojiMatches[1];

                //remove "<",">" and emote "name" to get id
                $emojiDiscoId
                    = preg_replace("/[^0-9]/", "",
                    str_replace($singleEmojiMatches[0], '', $emoji));

                $emojiModel = $emojisModels->get($emojiDiscoId);

                if (is_null($emojiModel)) {
                    if (is_null($emojiDiscoId)) {
                        $emojiDiscoId = md5($emojiName);
                    }
                    $emojiModel = EmojiModel::updateOrCreate(
                        ['emoji_id' => $emojiDiscoId],
                        ['name' => $emojiName]
                    );
                    $emojisModels->put($emojiDiscoId, $emojiModel);
                }
                $this->emojisUsed()->create([
                    'emoji_id'    => $emojiDiscoId,
                    'channel_id'  => $this->channel_id,
                    'author_id'   => $this->author_id,
                    'guild_id'    => $this->guild_id,
                    'is_reaction' => false
                ]);
            }
        }

        if ($message->reactions->count() > 0) {
            /**
             * @var \Discord\Parts\Channel\Reaction $reaction
             */
            foreach ($message->reactions as $reaction) {
                $emojiModel = $emojisModels->get($reaction->emoji->id);

                if (is_null($emojiModel)) {
                    $emojiDiscoId=$reaction->emoji->id;
                    if (empty($emojiDiscoId)) {
                        $emojiDiscoId = md5($reaction->emoji->name);
                    }
                    $emojiModel = EmojiModel::updateOrCreate(
                        ['emoji_id' => $emojiDiscoId],
                        ['name' => $reaction->emoji->name]
                    );
                    $emojisModels->put($reaction->emoji->id, $emojiModel);
                }

                $reaction->getUsers()->then(function ($users) use ($reaction, $authors) {
                    /**
                     * @var \Discord\Parts\User\User $user
                     */
                    foreach ($users as $user) {
                        Helper::createAuthorIfNotExists($user, $authors);
                        $this->emojisUsed()->create([
                            'emoji_id'    => $reaction->emoji->id,
                            'channel_id'  => $this->channel_id,
                            'author_id'   => $this->author_id,
                            'guild_id'    => $this->guild_id,
                            'is_reaction' => true
                        ]);

                    }
                    echo PHP_EOL;

                });
            }
        }

    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emojisUsed()
    {
        return $this->hasMany(EmojiUsedModel::class, 'message_id',
            'message_id');
    }

    /**
     * @param Message $message
     */
    public function saveAttachments(Message $message)
    {
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

        $this->attachments()->delete();
        $this->attachments()->saveMany($attachmentsParsed);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attachments()
    {
        return $this->hasMany(AttachmentModel::class, 'message_id',
            'message_id');
    }
}