<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\TanggapanDiskusi;

class DiscussionCommentController extends Controller
{
    public function store(Request $request, $discussion_id)
    {
        $request->validate([
            'body' => 'required|string',
            'image' => 'nullable|image|max:5120',
            'parent_id' => 'nullable|exists:tanggapan_diskusi,id_tanggapan',
        ]);

        $discussionExists = DB::table('diskusi')
            ->where('id_diskusi', $discussion_id)
            ->where('status_diskusi', 'active') // Asumsi pakai 'status_diskusi' di tabel baru
            ->exists();

        if (!$discussionExists) {
            return response()->json(['message' => 'Diskusi tidak ditemukan atau telah ditutup'], 404);
        }

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('comments', 'public');
            $imageUrl = url('storage/' . $path);
        }

        $currentUserId = $request->user()->id_user ?? $request->user()->id;

        $commentId = DB::table('tanggapan_diskusi')->insertGetId([
            'id_diskusi' => $discussion_id,
            'id_user' => $currentUserId,
            'isi_tanggapan' => $request->input('body'),
            'gambar_tanggapan' => $imageUrl, // Asumsi nama kolomnya 'gambar_tanggapan'
            'id_parent' => $request->input('parent_id'), // Asumsi nama kolomnya 'id_parent' buat reply
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Komentar berhasil ditambahkan',
            'comment_id' => $commentId
        ], 201);
    }

    public function report(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        $commentExists = DB::table('tanggapan_diskusi')->where('id_tanggapan', $id)->exists();

        if (!$commentExists) {
            return response()->json(['message' => 'Komentar tidak ditemukan'], 404);
        }

        $currentUserId = $request->user()->id_user ?? $request->user()->id;

        DB::table('laporan_tanggapan')->insert([
            'id_tanggapan' => $id,
            'id_user' => $currentUserId,
            'alasan_laporan' => $request->input('reason'),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Laporan komentar berhasil dikirim dan akan segera ditinjau.'
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $comment = TanggapanDiskusi::find($id); // Modelnya wajib disesuaikan dengan tabel tanggapan_diskusi
        if (!$comment) {
            return response()->json(['status' => 'error', 'message' => 'Komentar tidak ditemukan'], 404);
        }

        $currentUser = $request->user();
        $isAdmin = method_exists($currentUser, 'isAdmin') ? $currentUser->isAdmin() : (($currentUser->role_user ?? $currentUser->role ?? '') === 'admin');

        $currentUserId = $currentUser->id_user ?? $currentUser->id;

        if ((int) $comment->id_user !== (int) $currentUserId && !$isAdmin) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin untuk menghapus komentar ini'], 403);
        }

        if ($comment->gambar_tanggapan) {
            $path = str_replace(url('storage/'), '', $comment->gambar_tanggapan);
            Storage::disk('public')->delete($path);
        }

        $comment->delete();
        return response()->json(['status' => 'success', 'message' => 'Balasan berhasil dihapus']);
    }
}