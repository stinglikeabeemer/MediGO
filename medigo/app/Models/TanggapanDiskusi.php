<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TanggapanDiskusi extends Model
{
    use HasFactory;

    // Kasih tau Laravel nama tabel dan primary key barunya
    protected $table = 'tanggapan_diskusi';
    protected $primaryKey = 'id_tanggapan';

    protected $guarded = [];
}