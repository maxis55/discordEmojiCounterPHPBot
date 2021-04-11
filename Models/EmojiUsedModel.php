<?php


namespace Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EmojiUsedModel extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $table = 'emoji_used';
    protected $primaryKey = 'id';

    protected $fillable
        = [
            'guild_id',
            'channel_id',
            'author_id',
            'message_id',
            'emoji_id',
            'is_reaction'
        ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function emoji()
    {
        return $this->belongsTo(EmojiModel::class, 'emoji_id', 'emoji_id');
    }

    /**
     * @param string $guildId
     *
     * @return mixed
     */
    public static function EmojisByGuild(string $guildId)
    {
        return self::selectRaw(
            'emojis.emoji_id, emojis.name, COUNT(emoji_used.id) as emoji_count'
        )
            ->join('emojis', function ($query) {
                $query->on('emojis.emoji_id', 'emoji_used.emoji_id');
            })
            ->where('emoji_used.guild_id', $guildId)
            ->groupBy(['emojis.emoji_id']);
    }
}