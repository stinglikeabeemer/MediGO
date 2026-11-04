<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporanDiskusi extends Model
{
    use HasFactory;

    protected $table = 'laporan_diskusi';
    protected $primaryKey = 'id_laporan_diskusi';
    protected $guarded = [];

    public function diskusi()
    {
        return $this->belongsTo(Diskusi::class, 'id_diskusi', 'id_diskusi');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}