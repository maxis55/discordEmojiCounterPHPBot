<?php

namespace Models;

/**
 * Class AttachmentModel
 *
 * @package Models
 */
class AttachmentModel extends \Illuminate\Database\Eloquent\Model
{
    public $incrementing = false;
    public $timestamps=false;
    protected $primaryKey = 'attachment_id';
    protected $table = 'message_attachments';
    protected $fillable
        = [
            'attachment_id',
            'width',
            'url',
            'proxy_url',
            'height',
            'filename',
            'content_type',
            'message_id',
            'size'
        ];

    public function message()
    {
        return $this->hasMany(MessageModel::class, 'message_id', 'message_id');
    }
}