<?php

namespace Models;

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

    public function createEmojiCounts(Collection &$emojisModels = null)
    {
        if (is_null($emojisModels)) {
            $emojisModels = new Collection();
        }

        $emojis = [];
        if (false != preg_match_all(EmojiModel::EMOJI_REGEX, $this->content,
                $emojis)
        ) {
            $this->emojisUsed()->delete();
            foreach (reset($emojis) as $emoji) {

                $singleEmojiMatches = [];
                preg_match(EmojiModel::EMOJI_NAME_REGEX, $emoji, $singleEmojiMatches);
                $emojiName = $singleEmojiMatches[1];

                //remove "<",">" and emote "name" to get id
                $emojiDiscoId
                    = preg_replace("/[^0-9]/", "",
                    str_replace($singleEmojiMatches[0], '', $emoji));

                $emojiModel = $emojisModels->get($emojiDiscoId);

                if (is_null($emojiModel)) {

                    $emojiModel = EmojiModel::updateOrCreate(
                        ['emoji_id' => $emojiDiscoId],
                        ['name' => $emojiName]
                    );
                    $emojisModels->put($emojiDiscoId, $emojiModel);
                }
                if ($this->message_id) {
                    $this->emojisUsed()->create([
                        'emoji_id'    => $emojiDiscoId,
                        'channel_id'  => $this->channel_id,
                        'author_id'   => $this->author_id,
                        'guild_id'    => $this->guild_id,
                        'is_reaction' => false
                    ]);
                } else {
                    var_dump('missing message id for some reason');
                }

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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attachments()
    {
        return $this->hasMany(AttachmentModel::class, 'message_id',
            'message_id');
    }
}