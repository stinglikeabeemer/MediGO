<?php

namespace App\Http\Controllers;

use App\Models\Faskes;
use App\Models\FaskesFeedback;
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
        $faskes = Faskes::find($faskes_id);
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
            $userId = $request->user()->id;
            $userName = $userName ?: $request->user()->name;
            $userEmail = $userEmail ?: $request->user()->email;
        } elseif ($bearer = $request->bearerToken()) {
            $tokenObj = PersonalAccessToken::findToken($bearer);
            if ($tokenObj && $tokenObj->tokenable) {
                $user = $tokenObj->tokenable;
                $userId = $user->id;
                $userName = $userName ?: $user->name;
                $userEmail = $userEmail ?: $user->email;
            }
        }

        $feedback = FaskesFeedback::create([
            'faskes_id' => $faskes_id,
            'user_id' => $userId,
            'user_name' => $userName ?: 'Pengguna Anonim',
            'user_email' => $userEmail,
            'category' => $request->input('category'),
            'notes' => $request->input('notes'),
            'suggested_correction' => $request->input('suggested_correction'),
            'status' => 'pending',
        ]);

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
        $feedbacks = DB::table('faskes_feedbacks')
            ->join('faskes', 'faskes_feedbacks.faskes_id', '=', 'faskes.id')
            ->leftJoin('users', 'faskes_feedbacks.user_id', '=', 'users.id')
            ->select(
                'faskes_feedbacks.*',
                'faskes.nama_faskes',
                'faskes.tipe as faskes_tipe',
                'faskes.kota_kabupaten',
                'faskes.alamat as faskes_alamat',
                'faskes.link_maps as faskes_link_maps',
                'faskes.is_support_bpjs as faskes_bpjs',
                'users.name as registered_user_name',
                'users.email as registered_user_email'
            )
            ->orderBy('faskes_feedbacks.created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $feedbacks
        ]);
    }

    /**
     * Admin: Update status of a feedback (reviewed, applied, rejected)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,reviewed,applied,rejected',
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        $feedback = FaskesFeedback::find($id);
        if (!$feedback) {
            return response()->json(['status' => 'error', 'message' => 'Feedback tidak ditemukan.'], 404);
        }

        $feedback->status = $request->input('status');
        if ($request->has('admin_notes')) {
            $feedback->admin_notes = $request->input('admin_notes');
        }
        $feedback->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status masukan data faskes berhasil diperbarui.',
            'data' => $feedback
        ]);
    }
}
