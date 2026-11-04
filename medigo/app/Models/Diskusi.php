<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diskusi extends Model
{
    use HasFactory;

    // 1. Kasih tau Laravel nama tabel dan primary key barunya
    protected $table = 'diskusi';
    protected $primaryKey = 'id_diskusi';

    protected $guarded = [];

    // 2. Sesuaikan relasi ke tabel user pakai foreign key 'id_user'
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    // 3. Sesuaikan relasi ke tabel laporan pakai Model dan foreign key yang baru
    public function reports()
    {
        return $this->hasMany(LaporanDiskusi::class, 'id_diskusi', 'id_diskusi');
    }
}