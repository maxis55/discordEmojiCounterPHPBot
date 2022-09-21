<?php


namespace Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ChannelModel
 *
 * @package Models
 */
class ChannelModel extends Model
{
    public $incrementing = false;
    public $timestamps=false;
    protected $primaryKey = 'channel_id';
    protected $table = 'channels';
    protected $fillable
        = [
            'channel_id',
            'name',
            'type',
            'guild_id',
            'position',
            'is_private',
            'nsfw',
            'owner_id',
            'application_id',
            'parent_id'
        ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function guild()
    {
        return $this->belongsTo(GuildModel::class, 'guild_id', 'guild_id');
    }
}