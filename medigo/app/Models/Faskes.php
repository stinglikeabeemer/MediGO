<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faskes extends Model
{
    use HasFactory;

    protected $table = 'faskes';
    protected $primaryKey = 'id_faskes';
    protected $guarded = [];

    // Matikan timestamps jika di tabel faskes lu gak pakai timestamps
    public $timestamps = false;
}