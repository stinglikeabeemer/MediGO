<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaskesCommentController extends Controller
{
    public function store(Request $request, $faskes_id)
    {
        // 1. Validasi input: rating wajib 1-5, komentar opsional
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'body' => 'nullable|string',
        ]);

        // 2. Pastikan faskes-nya beneran ada di database
        $faskesExists = DB::table('faskes')->where('id', $faskes_id)->exists();
        if (!$faskesExists) {
            return response()->json(['message' => 'Fasilitas kesehatan tidak ditemukan'], 404);
        }

        $user_id = $request->user()->id;

        // 3. (Opsional tapi penting) Cek biar 1 user cuma bisa ngasih 1 review per faskes
        $existingReview = DB::table('faskes_comments')
            ->where('faskes_id', $faskes_id)
            ->where('user_id', $user_id)
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'Anda sudah memberikan ulasan untuk faskes ini'], 400);
        }

        // 4. Simpan rating dan review ke database
        $commentId = DB::table('faskes_comments')->insertGetId([
            'faskes_id' => $faskes_id,
            'user_id' => $user_id,
            'rating' => $request->input('rating'),
            'body' => $request->input('body'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Catat ke Log Aktivitas
        DB::table('activity_logs')->insert([
            'user_id' => $user_id,
            'action_type' => 'Beri Ulasan Faskes',
            'description' => 'Memberikan rating ' . $request->input('rating') . ' bintang untuk faskes ID ' . $faskes_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Ulasan dan rating berhasil ditambahkan',
            'review_id' => $commentId
        ], 201);
    }
}