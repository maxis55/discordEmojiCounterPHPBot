<?php

namespace Models;

/**
 * Class GuildModel
 *
 * @package Models
 */
class GuildModel extends \Illuminate\Database\Eloquent\Model
{
    public $incrementing = false;
    protected $primaryKey = 'guild_id';
    protected $table = 'guilds';
    protected $fillable
        = [
            'guild_id',
            'name',
            'owner_id',
            'joined_at',
            'system_channel_id',
            'icon',
            'region',
            'member_count'
        ];

    public function channels()
    {
        return $this->hasMany(ChannelModel::class, 'guild_id', 'guild_id');
    }
}