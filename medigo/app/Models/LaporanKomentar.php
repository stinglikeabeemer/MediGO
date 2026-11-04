<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporanKomentar extends Model
{
    use HasFactory;

    protected $table = 'laporan_komentar';
    protected $primaryKey = 'id_laporan_komentar';
    protected $guarded = [];

    public function komentar()
    {
        // Arahkan ke model Komentar yang baru pakai foreign key id_komentar
        return $this->belongsTo(Komentar::class, 'id_komentar', 'id_komentar');
    }

    public function faskes()
    {
        return $this->belongsTo(Faskes::class, 'id_faskes', 'id_faskes');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
