<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaskesCommentController extends Controller
{
    /**
     * Get paginated comments for a faskes.
     * Puts the current user's comment at the very top (pinned).
     */
    public function index(Request $request, $faskes_id)
    {
        $faskes = DB::table('faskes')->where('id', $faskes_id)->first();
        if (!$faskes) {
            return response()->json(['message' => 'Fasilitas kesehatan tidak ditemukan'], 404);
        }

        // Determine current user ID if token provided or passed as query
        $currentUserId = null;
        if ($request->user()) {
            $currentUserId = $request->user()->id;
        } elseif ($request->filled('current_user_id')) {
            $currentUserId = (int)$request->current_user_id;
        }

        $perPage = (int)$request->input('per_page', $request->input('limit', 5));
        $page = (int)$request->input('page', 1);

        // Fetch user's own comment if exists
        $myComment = null;
        if ($currentUserId) {
            $myRaw = DB::table('faskes_comments')
                ->join('users', 'faskes_comments.user_id', '=', 'users.id')
                ->where('faskes_comments.faskes_id', $faskes_id)
                ->where('faskes_comments.user_id', $currentUserId)
                ->select(
                    'faskes_comments.*',
                    'users.name as reviewer_name',
                    'users.email as reviewer_email'
                )
                ->first();

            if ($myRaw) {
                $myComment = [
                    'id' => $myRaw->id,
                    'faskes_id' => $myRaw->faskes_id,
                    'user_id' => $myRaw->user_id,
                    'reviewer_name' => $myRaw->reviewer_name,
                    'reviewer_email' => $myRaw->reviewer_email,
                    'rating' => (int)$myRaw->rating,
                    'body' => $myRaw->body,
                    'is_my_comment' => true,
                    'created_at' => $myRaw->created_at,
                    'updated_at' => $myRaw->updated_at,
                    'created_at_human' => \Carbon\Carbon::parse($myRaw->created_at)->diffForHumans(),
                ];
            }
        }

        // Query all other comments
        $query = DB::table('faskes_comments')
            ->join('users', 'faskes_comments.user_id', '=', 'users.id')
            ->where('faskes_comments.faskes_id', $faskes_id);

        if ($currentUserId && $myComment) {
            $query->where('faskes_comments.user_id', '!=', $currentUserId);
        }

        $totalOtherComments = $query->count();
        $totalComments = $totalOtherComments + ($myComment ? 1 : 0);

        $offset = ($page - 1) * $perPage;
        $otherCommentsRaw = $query->select(
                'faskes_comments.*',
                'users.name as reviewer_name',
                'users.email as reviewer_email'
            )
            ->orderBy('faskes_comments.created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $commentsList = [];

        // On first page, prepend current user's comment to the top
        if ($page === 1 && $myComment) {
            $commentsList[] = $myComment;
        }

        foreach ($otherCommentsRaw as $item) {
            $commentsList[] = [
                'id' => $item->id,
                'faskes_id' => $item->faskes_id,
                'user_id' => $item->user_id,
                'reviewer_name' => $item->reviewer_name,
                'reviewer_email' => $item->reviewer_email,
                'rating' => (int)$item->rating,
                'body' => $item->body,
                'is_my_comment' => false,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'created_at_human' => \Carbon\Carbon::parse($item->created_at)->diffForHumans(),
            ];
        }

        // Compute rating stats
        // Compute rating stats and percentage breakdown
        $breakdownStats = $this->getRatingBreakdownStats($faskes_id);
        $hasMore = ($offset + $perPage) < $totalOtherComments;

        return response()->json([
            'status' => 'success',
            'data' => [
                'comments' => $commentsList,
                'my_comment' => $myComment,
                'current_page' => $page,
                'per_page' => $perPage,
                'total_comments' => $totalComments,
                'has_more' => $hasMore,
                'avg_rating' => $breakdownStats['avg_rating'],
                'total_reviews' => $breakdownStats['total_reviews'],
                'rating_breakdown' => $breakdownStats['rating_breakdown'],
                'rating_percentages' => $breakdownStats['rating_percentages'],
            ]
        ]);
    }

    /**
     * Store new comment or update if user already reviewed
     */
    public function store(Request $request, $faskes_id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'body' => 'nullable|string',
        ]);

        $faskesExists = DB::table('faskes')->where('id', $faskes_id)->exists();
        if (!$faskesExists) {
            return response()->json(['message' => 'Fasilitas kesehatan tidak ditemukan'], 404);
        }

        $user_id = $request->user()->id;

        // Check if user already commented
        $existing = DB::table('faskes_comments')
            ->where('faskes_id', $faskes_id)
            ->where('user_id', $user_id)
            ->first();

        if ($existing) {
            // Update existing review
            DB::table('faskes_comments')
                ->where('id', $existing->id)
                ->update([
                    'rating' => $request->input('rating'),
                    'body' => $request->input('body'),
                    'updated_at' => now(),
                ]);

            $commentId = $existing->id;
            $message = 'Ulasan berhasil diperbarui';
        } else {
            // Insert new review
            $commentId = DB::table('faskes_comments')->insertGetId([
                'faskes_id' => $faskes_id,
                'user_id' => $user_id,
                'rating' => $request->input('rating'),
                'body' => $request->input('body'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $message = 'Ulasan dan rating berhasil ditambahkan';
        }

        // Log activity
        DB::table('activity_logs')->insert([
            'user_id' => $user_id,
            'action_type' => 'Beri Ulasan Faskes',
            'description' => 'Memberikan rating ' . $request->input('rating') . ' bintang untuk faskes ID ' . $faskes_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get updated rating stats and breakdown
        $breakdownStats = $this->getRatingBreakdownStats($faskes_id);

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'id' => $commentId,
                'faskes_id' => (int)$faskes_id,
                'rating' => (int)$request->input('rating'),
                'body' => $request->input('body'),
                'avg_rating' => $breakdownStats['avg_rating'],
                'total_reviews' => $breakdownStats['total_reviews'],
                'rating_breakdown' => $breakdownStats['rating_breakdown'],
                'rating_percentages' => $breakdownStats['rating_percentages'],
            ]
        ], 201);
    }

    /**
     * Update comment by ID
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'body' => 'nullable|string',
        ]);

        $comment = DB::table('faskes_comments')->where('id', $id)->first();
        if (!$comment) {
            return response()->json(['message' => 'Ulasan tidak ditemukan'], 404);
        }

        $userId = $request->user()->id;
        if ($comment->user_id != $userId && ($request->user()->role ?? '') !== 'admin') {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk mengubah ulasan ini'], 403);
        }

        DB::table('faskes_comments')->where('id', $id)->update([
            'rating' => $request->input('rating'),
            'body' => $request->input('body'),
            'updated_at' => now(),
        ]);

        // Recalculate stats
        $breakdownStats = $this->getRatingBreakdownStats($comment->faskes_id);

        return response()->json([
            'status' => 'success',
            'message' => 'Ulasan berhasil diperbarui',
            'data' => [
                'id' => (int)$id,
                'faskes_id' => (int)$comment->faskes_id,
                'rating' => (int)$request->input('rating'),
                'body' => $request->input('body'),
                'avg_rating' => $breakdownStats['avg_rating'],
                'total_reviews' => $breakdownStats['total_reviews'],
                'rating_breakdown' => $breakdownStats['rating_breakdown'],
                'rating_percentages' => $breakdownStats['rating_percentages'],
            ]
        ]);
    }

    /**
     * Delete comment by ID
     */
    public function destroy(Request $request, $id)
    {
        $comment = DB::table('faskes_comments')->where('id', $id)->first();
        if (!$comment) {
            return response()->json(['message' => 'Ulasan tidak ditemukan'], 404);
        }

        $userId = $request->user()->id;
        if ($comment->user_id != $userId && ($request->user()->role ?? '') !== 'admin') {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk menghapus ulasan ini'], 403);
        }

        $faskesId = $comment->faskes_id;
        DB::table('faskes_comments')->where('id', $id)->delete();

        // Recalculate stats
        $breakdownStats = $this->getRatingBreakdownStats($faskesId);

        return response()->json([
            'status' => 'success',
            'message' => 'Ulasan berhasil dihapus',
            'data' => [
                'faskes_id' => (int)$faskesId,
                'avg_rating' => $breakdownStats['avg_rating'],
                'total_reviews' => $breakdownStats['total_reviews'],
                'rating_breakdown' => $breakdownStats['rating_breakdown'],
                'rating_percentages' => $breakdownStats['rating_percentages'],
            ]
        ]);
    }

    /**
     * Calculate star counts and percentage breakdown
     */
    private function getRatingBreakdownStats($faskes_id)
    {
        $starCounts = DB::table('faskes_comments')
            ->where('faskes_id', $faskes_id)
            ->select('rating', DB::raw('COUNT(id) as count'))
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $percentages = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $total = array_sum($starCounts);

        $avg = null;
        if ($total > 0) {
            $sum = 0;
            foreach ($starCounts as $r => $cnt) {
                $sum += ($r * $cnt);
            }
            $avg = round($sum / $total, 1);
        }

        foreach ([5, 4, 3, 2, 1] as $star) {
            $count = $starCounts[$star] ?? 0;
            $breakdown[$star] = $count;
            $percentages[$star] = ($total > 0) ? (int)round(($count / $total) * 100) : 0;
        }

        return [
            'total_reviews' => $total,
            'avg_rating' => $avg !== null ? (string)$avg : null,
            'rating_breakdown' => $breakdown,
            'rating_percentages' => $percentages,
        ];
    }

    /**
     * Get all reviews created by the authenticated user
     */
    public function getUserReviews(Request $request)
    {
        $userId = $request->user()->id;
        $perPage = (int)$request->input('per_page', $request->input('limit', 4));
        $page = (int)$request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        $query = DB::table('faskes_comments')
            ->join('faskes', 'faskes_comments.faskes_id', '=', 'faskes.id')
            ->where('faskes_comments.user_id', $userId);

        $totalReviews = $query->count();

        $reviews = $query->select(
                'faskes_comments.*',
                'faskes.nama_faskes',
                'faskes.tipe as faskes_tipe',
                'faskes.alamat as faskes_alamat',
                'faskes.kota_kabupaten as faskes_kota'
            )
            ->orderBy('faskes_comments.created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $formatted = $reviews->map(function ($item) {
            $typeSlug = 'hospital';
            $tipeLower = strtolower($item->faskes_tipe ?? '');
            if (str_contains($tipeLower, 'puskesmas')) {
                $typeSlug = 'puskesmas';
            } elseif (str_contains($tipeLower, 'klinik') || str_contains($tipeLower, 'dokter')) {
                $typeSlug = 'clinic';
            }

            $nama = trim($item->nama_faskes ?? '');
            if ($typeSlug === 'puskesmas' && !preg_match('/^(puskesmas|pkm)\b/i', $nama)) {
                $nama = 'Puskesmas ' . $nama;
            }

            return [
                'id' => $item->id,
                'faskes_id' => $item->faskes_id,
                'nama_faskes' => $nama,
                'type_slug' => $typeSlug,
                'rating' => (int)$item->rating,
                'body' => $item->body,
                'created_at' => $item->created_at,
                'created_at_human' => \Carbon\Carbon::parse($item->created_at)->diffForHumans(),
                'faskes_url' => url('/faskes/' . $item->faskes_id),
            ];
        });

        $hasMore = ($offset + $perPage) < $totalReviews;

        return response()->json([
            'status' => 'success',
            'data' => [
                'reviews' => $formatted,
                'current_page' => $page,
                'per_page' => $perPage,
                'total_reviews' => $totalReviews,
                'has_more' => $hasMore,
            ]
        ]);
    }
}