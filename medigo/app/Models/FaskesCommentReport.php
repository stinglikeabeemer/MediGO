<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaskesCommentReport extends Model
{
    protected $table = 'faskes_comment_reports';
    protected $guarded = [];

    public function comment()
    {
        return $this->belongsTo(FaskesComment::class, 'comment_id');
    }

    public function faskes()
    {
        return $this->belongsTo(Faskes::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
