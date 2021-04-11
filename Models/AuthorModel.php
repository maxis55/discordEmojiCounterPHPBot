<?php


namespace Models;


use Illuminate\Database\Eloquent\Model;

class AuthorModel extends Model
{
    public $incrementing = false;
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