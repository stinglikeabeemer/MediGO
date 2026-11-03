<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class FaskesFeedbackController extends Controller
{
    /**
     * Submit feedback for a health facility (faskes).
     * Open to authenticated users and guests.
     */
    public function store(Request $request, $faskes_id)
    {
        $faskes = DB::table('faskes')->where('id_faskes', $faskes_id)->first();
        if (!$faskes) {
            return response()->json(['status' => 'error', 'message' => 'Fasilitas kesehatan tidak ditemukan.'], 404);
        }

        $request->validate([
            'category' => 'required|string|max:100',
            'notes' => 'required|string|max:2000',
            'suggested_correction' => 'nullable|string|max:1000',
            'user_name' => 'nullable|string|max:150',
            'user_email' => 'nullable|email|max:150',
        ]);

        $userId = null;
        $userName = $request->input('user_name');
        $userEmail = $request->input('user_email');

        // Check if user is authenticated via Sanctum token
        if ($request->user()) {
            $userId = $request->user()->id_user ?? $request->user()->id;
            $userName = $userName ?: ($request->user()->nama_user ?? $request->user()->name);
            $userEmail = $userEmail ?: ($request->user()->email_user ?? $request->user()->email);
        } elseif ($bearer = $request->bearerToken()) {
            $tokenObj = PersonalAccessToken::findToken($bearer);
            if ($tokenObj && $tokenObj->tokenable) {
                $user = $tokenObj->tokenable;
                $userId = $user->id_user ?? $user->id;
                $userName = $userName ?: ($user->nama_user ?? $user->name);
                $userEmail = $userEmail ?: ($user->email_user ?? $user->email);
            }
        }

        $feedbackId = DB::table('feedback_faskes')->insertGetId([
            'id_faskes' => $faskes_id,
            'id_user' => $userId,
            'nama_pengguna' => $userName ?: 'Pengguna Anonim',
            'email_pengguna' => $userEmail,
            'kategori' => $request->input('category'),
            'catatan' => $request->input('notes'),
            'saran_koreksi' => $request->input('suggested_correction'),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $feedback = DB::table('feedback_faskes')->where('id_feedback_faskes', $feedbackId)->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Terima kasih! Masukan Anda mengenai data fasilitas kesehatan ini berhasil dikirim dan akan segera ditinjau oleh tim admin.',
            'data' => $feedback
        ], 201);
    }

    /**
     * Admin: List all faskes feedbacks with facility details
     */
    public function indexAdmin(Request $request)
    {
        $feedbacks = DB::table('feedback_faskes')
            ->join('faskes', 'feedback_faskes.id_faskes', '=', 'faskes.id_faskes')
            ->leftJoin('user', 'feedback_faskes.id_user', '=', 'user.id_user')
            ->select(
                'feedback_faskes.*',
                'faskes.nama_faskes',
                'faskes.jenis_faskes as faskes_tipe',
                'faskes.kota_kabupaten',
                'faskes.alamat_faskes as faskes_alamat',
                'faskes.link_maps as faskes_link_maps',
                'faskes._support_bpjs as faskes_bpjs',
                'user.nama_user as registered_user_name',
                'user.email_user as registered_user_email'
            )
            ->orderBy('feedback_faskes.created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $feedbacks
        ]);
    }

    /**
     * Admin: Update status of a feedback (resolved, dismissed)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,resolved,dismissed',
            'catatan_admin' => 'nullable|string|max:1000'
        ]);

        $feedback = DB::table('feedback_faskes')->where('id_feedback_faskes', $id)->first();
        if (!$feedback) {
            return response()->json(['status' => 'error', 'message' => 'Feedback tidak ditemukan.'], 404);
        }

        $updateData = [
            'status' => $request->input('status'),
            'updated_at' => now(),
        ];

        if ($request->has('catatan_admin')) {
            $updateData['catatan_admin'] = $request->input('catatan_admin');
        }

        DB::table('feedback_faskes')->where('id_feedback_faskes', $id)->update($updateData);

        $updatedFeedback = DB::table('feedback_faskes')->where('id_feedback_faskes', $id)->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Status masukan data faskes berhasil diperbarui.',
            'data' => $updatedFeedback
        ]);
    }
}