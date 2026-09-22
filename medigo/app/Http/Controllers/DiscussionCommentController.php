<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\DiscussionComment;

class DiscussionCommentController extends Controller
{
    public function store(Request $request, $discussion_id)
    {
        $request->validate([
            'body'      => 'required|string', 
            'image'     => 'nullable|image|max:5120', 
            'parent_id' => 'nullable|exists:discussion_comments,id',
        ]);

        $discussionExists = DB::table('discussions')
            ->where('id', $discussion_id)
            ->where('status', 'active')
            ->exists();
        
        if (!$discussionExists) {
            return response()->json(['message' => 'Diskusi tidak ditemukan atau telah ditutup'], 404);
        }

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('comments', 'public');
            $imageUrl = url('storage/' . $path); 
        }

        $commentId = DB::table('discussion_comments')->insertGetId([
            'discussion_id' => $discussion_id,
            'user_id'       => $request->user()->id,
            'body'          => $request->input('body'),
            'image_path'    => $imageUrl,
            'parent_id'     => $request->input('parent_id'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'message'    => 'Komentar berhasil ditambahkan',
            'comment_id' => $commentId
        ], 201);
    }

    public function report(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        $commentExists = DB::table('discussion_comments')->where('id', $id)->exists();
        
        if (!$commentExists) {
            return response()->json(['message' => 'Komentar tidak ditemukan'], 404);
        }

        DB::table('discussion_comment_reports')->insert([
            'comment_id' => $id,
            'user_id'    => $request->user()->id,
            'reason'     => $request->input('reason'),
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Laporan komentar berhasil dikirim dan akan segera ditinjau.'
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $comment = DiscussionComment::find($id);
        if (!$comment) {
            return response()->json(['status' => 'error', 'message' => 'Komentar tidak ditemukan'], 404);
        }

        $currentUser = $request->user();
        $isAdmin = method_exists($currentUser, 'isAdmin') && $currentUser->isAdmin();

        // [FIXED] Tambahkan casting (int) agar perbandingan string dan integer aman
        if ((int) $comment->user_id !== (int) $currentUser->id && !$isAdmin) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin untuk menghapus komentar ini'], 403);
        }

        if ($comment->image_path) {
            $path = str_replace(url('storage/'), '', $comment->image_path);
            Storage::disk('public')->delete($path);
        }

        $comment->delete();
        return response()->json(['status' => 'success', 'message' => 'Balasan berhasil dihapus']);
    }
}