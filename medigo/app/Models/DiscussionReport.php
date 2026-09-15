<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscussionReport extends Model
{
    protected $guarded = [];

    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
