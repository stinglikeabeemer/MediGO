<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DiscussionReportController extends Controller
{
    public function store(Request $request, $discussionId)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        // Pastikan diskusi ada dan belum di takedown
        $discussion = \Illuminate\Support\Facades\DB::table('discussions')
            ->where('id', $discussionId)
            ->where('status', 'active')
            ->first();

        if (!$discussion) {
            return response()->json(['message' => 'Diskusi tidak ditemukan atau sudah dihapus'], 404);
        }

        \App\Models\DiscussionReport::create([
            'discussion_id' => $discussionId,
            'user_id' => $request->user()->id,
            'reason' => $request->reason
        ]);

        return response()->json(['message' => 'Laporan berhasil dikirim'], 201);
    }

    public function indexAdmin()
    {
        // Ambil semua laporan beserta detail diskusi dan user pelapor
        $reports = \Illuminate\Support\Facades\DB::table('discussion_reports')
            ->join('users', 'discussion_reports.user_id', '=', 'users.id')
            ->join('discussions', 'discussion_reports.discussion_id', '=', 'discussions.id')
            ->select('discussion_reports.*', 'users.name as reporter_name', 'discussions.title as discussion_title', 'discussions.status as discussion_status')
            ->orderBy('discussion_reports.created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil daftar laporan',
            'data' => $reports
        ]);
    }

    public function takedown(Request $request, $discussionId)
    {
        $discussion = \Illuminate\Support\Facades\DB::table('discussions')
            ->where('id', $discussionId)
            ->first();

        if (!$discussion) {
            return response()->json(['message' => 'Diskusi tidak ditemukan'], 404);
        }

        \Illuminate\Support\Facades\DB::table('discussions')
            ->where('id', $discussionId)
            ->update(['status' => 'takedown', 'updated_at' => now()]);

        // Catat ke Log Aktivitas (Admin)
        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id, // Asumsi admin login
            'action_type' => 'Takedown Diskusi',
            'description' => 'Admin menghapus (takedown) diskusi dengan ID: ' . $discussionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Diskusi berhasil di-takedown']);
    }
}
