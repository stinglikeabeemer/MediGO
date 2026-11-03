<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // 1. Kasih tau Laravel kalau tabelnya bernama 'user', bukan 'users'
    protected $table = 'user';

    // 2. Kasih tau Laravel kalau primary key-nya 'id_user', bukan 'id'
    protected $primaryKey = 'id_user';

    // 3. Matikan timestamps karena di file migration lu kemarin lu hapus
    public $timestamps = false;

    // 4. Kolom apa aja yang boleh diisi
    protected $fillable = [
        'nama_user',
        'email_user',
        'password_user',
        'role_user',
    ];

    // 5. Sembunyikan password saat data user dipanggil API
    protected $hidden = [
        'password_user',
        'remember_token',
    ];

    // 6. Override sistem Auth Laravel agar ngecek 'password_user' pas login
    public function getAuthPassword()
    {
        return $this->password_user;
    }

    // 7. Casts bawaan Laravel lu yang udah disesuaikan
    protected function casts(): array
    {
        return [
            'password_user' => 'hashed',
        ];
    }
}