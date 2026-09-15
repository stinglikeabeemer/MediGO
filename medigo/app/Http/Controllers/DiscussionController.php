<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscussionController extends Controller
{
    // 1. Tampilkan semua diskusi (diurutkan dari yang paling baru)
    public function index(Request $request)
    {
        // 1. Siapkan kerangka query dasar
        $query = DB::table('discussions')
            ->join('users', 'discussions.user_id', '=', 'users.id')
            ->where('discussions.status', 'active')
            ->select('discussions.*', 'users.name as author_name')
            ->orderBy('discussions.created_at', 'desc');

        // 2. Cek apakah ada request filter kategori dari frontend/Postman
        if ($request->has('category')) {
            $query->where('discussions.category', $request->query('category'));
        }

        // 3. Eksekusi pencariannya (Ubah bagian ini)
        $discussions = $query->paginate(10);

        return response()->json([
            'message' => 'Berhasil mengambil daftar diskusi',
            'data' => $discussions
        ]);
    }

    // 2. Bikin topik diskusi baru
    public function store(Request $request)
    {
        // Tambahin validasi buat kategori
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string', // Kategori opsional
        ]);

        $user_id = $request->user()->id;

        // Simpan ke database dengan kategori
        $discussionId = DB::table('discussions')->insertGetId([
            'user_id' => $user_id,
            'title' => $request->input('title'),
            'body' => $request->input('content'),
            'category' => $request->input('category', 'Umum'), // Kalau dikosongin, otomatis masuk kategori 'Umum'
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Catat ke Log Aktivitas
        DB::table('activity_logs')->insert([
            'user_id' => $user_id,
            'action_type' => 'Buat Diskusi',
            'description' => 'Membuat diskusi baru dengan judul: ' . $request->input('title'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Diskusi berhasil dibuat',
            'discussion_id' => $discussionId
        ], 201);
    }

    // 3. Tampilkan detail spesifik satu diskusi
    public function show($id)
    {
        // 1. Ambil data diskusi utamanya
        $discussion = DB::table('discussions')
            ->join('users', 'discussions.user_id', '=', 'users.id')
            ->where('discussions.id', $id)
            ->where('discussions.status', 'active')
            ->select('discussions.*', 'users.name as author_name')
            ->first();

        if (!$discussion) {
            return response()->json(['message' => 'Diskusi tidak ditemukan'], 404);
        }

        // 2. Ambil semua komentar yang nyambung sama diskusi ini
        $comments = DB::table('discussion_comments')
            ->join('users', 'discussion_comments.user_id', '=', 'users.id')
            ->where('discussion_comments.discussion_id', $id)
            ->select('discussion_comments.*', 'users.name as commentator_name')
            ->orderBy('discussion_comments.created_at', 'asc') // Urutkan dari yang paling lama ke baru
            ->get();

        // 3. Gabungin dan kirim ke frontend
        return response()->json([
            'message' => 'Berhasil mengambil detail diskusi',
            'data' => [
                'discussion' => $discussion,
                'comments' => $comments
            ]
        ]);
    }
}

