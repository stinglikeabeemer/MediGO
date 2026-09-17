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
            'body'      => 'required|string', 
            'image'     => 'nullable|image|max:5120', 
            'parent_id' => 'nullable|exists:discussion_comments,id', // Validasi jika ini adalah balasan dari komentar lain
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
            $path = $request->file('image')->store('comments', 'public');
            $imageUrl = url('storage/' . $path); 
        }

        // 4. Masukkan ke database
        $commentId = DB::table('discussion_comments')->insertGetId([
            'discussion_id' => $discussion_id,
            'user_id'       => $request->user()->id,
            'body'          => $request->input('body'),
            'image_path'    => $imageUrl,
            'parent_id'     => $request->input('parent_id'), // Menyimpan ID komentar induk jika ini adalah balasan
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'message'    => 'Komentar berhasil ditambahkan',
            'comment_id' => $commentId
        ], 201);
    }

    // Fungsi untuk Melaporkan Komentar
    public function report(Request $request, $id)
    {
        // Validasi alasan pelaporan
        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        // Cek apakah komentar yang dilaporkan itu ada
        $commentExists = DB::table('discussion_comments')->where('id', $id)->exists();
        
        if (!$commentExists) {
            return response()->json(['message' => 'Komentar tidak ditemukan'], 404);
        }

        // Simpan laporan komentar ke database
        DB::table('discussion_comment_reports')->insert([
            'comment_id' => $id,
            'user_id'    => $request->user()->id, // ID user yang melaporkan
            'reason'     => $request->input('reason'),
            'status'     => 'pending', // Menunggu tinjauan
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Laporan komentar berhasil dikirim dan akan segera ditinjau.'
        ], 201);
    }
}