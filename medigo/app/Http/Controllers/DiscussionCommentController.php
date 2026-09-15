<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DiscussionCommentController extends Controller
{
    public function store(Request $request, $discussion_id)
    {
        // 1. Validasi input
        $request->validate([
            'body'  => 'required|string', 
            'image' => 'nullable|image|max:5120', // Validasi file gambar maks 5MB
        ]);

        // 2. Cek eksistensi diskusi
        $discussionExists = DB::table('discussions')
            ->where('id', $discussion_id)
            ->where('status', 'active')
            ->exists();
        
        if (!$discussionExists) {
            return response()->json(['message' => 'Diskusi tidak ditemukan atau telah ditutup'], 404);
        }

        // 3. Proses upload gambar (jika ada)
        $imageUrl = null;
        if ($request->hasFile('image')) {
            // Simpan gambar ke folder storage/app/public/comments
            $path = $request->file('image')->store('comments', 'public');
            $imageUrl = url('storage/' . $path); 
        }

        // 4. Masukkan ke database (MENYESUAIKAN STRUKTUR DATABASE-MU)
        $commentId = DB::table('discussion_comments')->insertGetId([
            'discussion_id' => $discussion_id,
            'user_id'       => $request->user()->id,
            'body'          => $request->input('body'), // Sesuai kolom database
            'image_path'    => $imageUrl,               // Kolom baru yang ditambahkan di Langkah 1
            'parent_id'     => null,                    // Dibiarkan null untuk komentar utama
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'message' => 'Komentar berhasil ditambahkan',
            'comment_id' => $commentId
        ], 201);
    }
}