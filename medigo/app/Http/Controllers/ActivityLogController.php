<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        // Narik semua data log dan gabungin sama nama user
        $logs = DB::table('activity_logs')
            ->join('users', 'activity_logs.user_id', '=', 'users.id')
            ->select('activity_logs.*', 'users.name as user_name')
            ->orderBy('activity_logs.created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'message' => 'Berhasil mengambil data aktivitas log',
            'data' => $logs
        ]);
    }
}