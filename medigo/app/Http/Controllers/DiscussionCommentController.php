<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscussionCommentController extends Controller
{
    public function store(Request $request, $discussion_id)
    {
        // Validasi input dari user
        $request->validate([
            'body' => 'required|string',
        ]);

        // Cek dulu apakah diskusi yang mau dikomenin itu beneran ada
        $discussionExists = DB::table('discussions')->where('id', $discussion_id)->exists();
        
        if (!$discussionExists) {
            return response()->json(['message' => 'Diskusi tidak ditemukan'], 404);
        }

        // Masukin komentar ke database
        $commentId = DB::table('discussion_comments')->insertGetId([
            'discussion_id' => $discussion_id,
            'user_id' => $request->user()->id,
            'body' => $request->input('body'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Komentar berhasil ditambahkan',
            'comment_id' => $commentId
        ], 201);
    }
}