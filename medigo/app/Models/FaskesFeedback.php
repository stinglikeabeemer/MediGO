<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaskesFeedback extends Model
{
    protected $table = 'faskes_feedbacks';
    protected $guarded = [];

    public function faskes()
    {
        return $this->belongsTo(Faskes::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
