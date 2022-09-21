<?php


namespace Models;


use Illuminate\Database\Eloquent\Model;

/**
 * Class AuthorModel
 *
 * @property string $author_id
 * @property string $username
 * @property string $avatar
 * @property string $discriminator
 * @property bool   $bot
 * @property string $system
 * @property string $mfa_enabled
 * @property string $locale
 * @property string $verified
 * @property string $email
 * @property string $flags
 * @property string $premium_type
 * @property string $public_flags
 *
 *
 * @package Models
 */
class AuthorModel extends Model
{
    public $incrementing = false;
    public $timestamps=false;
    protected $primaryKey = 'author_id';
    protected $table = 'authors';
    protected $fillable
        = [
            'author_id',
            'username',
            'avatar',
            'discriminator',
            'bot',
            'system',
            'mfa_enabled',
            'locale',
            'verified',
            'email',
            'flags',
            'premium_type',
            'public_flags'
        ];
}