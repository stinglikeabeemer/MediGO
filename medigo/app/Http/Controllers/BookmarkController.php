<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookmarkController extends Controller
{
    // 1. Tampilkan semua faskes yang disimpen sama user yang lagi login
    public function index(Request $request)
    {
        $user_id = $request->user()->id;

        // Tarik data faskes yang berelasi dengan user ini
        $bookmarks = DB::table('faskes_user_bookmarks')
            ->join('faskes', 'faskes_user_bookmarks.faskes_id', '=', 'faskes.id')
            ->where('faskes_user_bookmarks.user_id', $user_id)
            ->select('faskes.*')
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil data bookmark',
            'data' => $bookmarks
        ]);
    }

    // 2. Simpan atau Hapus Bookmark (Toggle)
    public function toggleBookmark(Request $request, $faskes_id)
    {
        $user_id = $request->user()->id;

        // Cek apakah faskes ini udah dibookmark sebelumnya
        $exists = DB::table('faskes_user_bookmarks')
            ->where('user_id', $user_id)
            ->where('faskes_id', $faskes_id)
            ->first();

        if ($exists) {
            // Kalau udah ada, berarti dihapus (Unbookmark)
            DB::table('faskes_user_bookmarks')
                ->where('user_id', $user_id)
                ->where('faskes_id', $faskes_id)
                ->delete();

            return response()->json(['message' => 'Faskes dihapus dari bookmark']);
        } else {
            // Kalau belum ada, tambahin baru
            DB::table('faskes_user_bookmarks')->insert([
                'user_id' => $user_id,
                'faskes_id' => $faskes_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Faskes berhasil dibookmark'], 201);
        }
    }
}