<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackFaskes extends Model
{
    use HasFactory;

    protected $table = 'feedback_faskes';
    protected $primaryKey = 'id_feedback_faskes';
    protected $guarded = [];

    public function faskes()
    {
        return $this->belongsTo(Faskes::class, 'id_faskes', 'id_faskes');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}