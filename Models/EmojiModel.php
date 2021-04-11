<?php


namespace Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class EmojiModel
 *
 * @property string $name
 * @property string $emoji_disco_id
 *
 * @package Models
 */
class EmojiModel extends Model
{
    const EMOJI_REGEX = '/\<\:.*\:\d*\>/';
    const EMOJI_NAME_REGEX = '/\:(.*?)\:/';

    public $incrementing = false;
    protected $table = 'emojis';
    protected $primaryKey = 'emoji_id';
    protected $fillable = ['name', 'emoji_id', 'guild_id', 'animated'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emojiUsed()
    {
        return $this->hasMany(EmojiUsedModel::class, 'emoji_id', 'emoji_id');
    }

    /**
     * @param string $emojiName
     * @param string $emojiId
     *
     * @return string
     */
    public static function combineEmoji(string $emojiName, string $emojiId): string
    {
        $emojiIdClean = preg_replace("/[^0-9]/", "", $emojiId);

        return '<:' . $emojiName . ':' . $emojiIdClean . '>';
    }
}