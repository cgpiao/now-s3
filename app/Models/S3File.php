<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class S3File extends Model
{
    protected $fillable = [
        'user_uuid',
        'filename',
        'file_size',
        'mime_type',
        'extension',
        's3_path',
        's3_url',
    ];
}
