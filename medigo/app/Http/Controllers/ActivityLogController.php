<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        // Narik semua data log dan gabungin sama nama user
        $logs = DB::table('log_aktivitas')
            ->join('user', 'log_aktivitas.id_user', '=', 'user.id_user')
            ->select('log_aktivitas.*', 'user.nama_user as user_name')
            ->orderBy('log_aktivitas.created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'message' => 'Berhasil mengambil data aktivitas log',
            'data' => $logs
        ]);
    }
}