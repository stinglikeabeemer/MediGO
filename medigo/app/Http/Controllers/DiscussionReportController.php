<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscussionReportController extends Controller
{
    public function store(Request $request, $discussionId)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        // Pastikan diskusi ada dan belum di takedown
        $discussion = DB::table('diskusi')
            ->where('id_diskusi', $discussionId)
            ->where('status_diskusi', 'active')
            ->first();

        if (!$discussion) {
            return response()->json(['message' => 'Diskusi tidak ditemukan atau sudah dihapus'], 404);
        }

        $userId = $request->user()->id_user ?? $request->user()->id;

        DB::table('laporan_diskusi')->insert([
            'id_diskusi' => $discussionId,
            'id_user' => $userId,
            'alasan_laporan' => $request->reason,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['message' => 'Laporan berhasil dikirim'], 201);
    }

    public function indexAdmin()
    {
        // Ambil semua laporan beserta detail diskusi dan user pelapor
        $reports = DB::table('laporan_diskusi')
            ->join('user', 'laporan_diskusi.id_user', '=', 'user.id_user')
            ->join('diskusi', 'laporan_diskusi.id_diskusi', '=', 'diskusi.id_diskusi')
            ->select(
                'laporan_diskusi.*',
                'user.nama_user as reporter_name',
                'diskusi.judul_diskusi as discussion_title',
                'diskusi.status_diskusi as discussion_status'
            )
            ->orderBy('laporan_diskusi.created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil daftar laporan',
            'data' => $reports
        ]);
    }

    public function takedown(Request $request, $discussionId)
    {
        $discussion = DB::table('diskusi')
            ->where('id_diskusi', $discussionId)
            ->first();

        if (!$discussion) {
            return response()->json(['message' => 'Diskusi tidak ditemukan'], 404);
        }

        // Ubah status jadi 'deleted' agar seragam dengan fungsi destroy di DiscussionController
        DB::table('diskusi')
            ->where('id_diskusi', $discussionId)
            ->update([
                'status_diskusi' => 'deleted',
                'updated_at' => now()
            ]);

        $adminId = $request->user()->id_user ?? $request->user()->id;

        // Catat ke Log Aktivitas (Admin)
        DB::table('log_aktivitas')->insert([
            'id_user' => $adminId,
            'tipe_aksi' => 'Takedown Diskusi',
            'deskripsi' => 'Admin menghapus (takedown) diskusi dengan ID: ' . $discussionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Diskusi berhasil di-takedown']);
    }
}