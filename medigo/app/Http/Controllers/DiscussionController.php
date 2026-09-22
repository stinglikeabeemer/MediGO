<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscussionController extends Controller
{
    // 1. Tampilkan semua diskusi (diurutkan dari yang paling baru)
    public function index(Request $request)
    {
        // 1. Siapkan kerangka query dasar dengan penghitungan real comments_count dan email author
        $query = DB::table('discussions')
            ->join('users', 'discussions.user_id', '=', 'users.id')
            ->where('discussions.status', 'active')
            ->select(
                'discussions.*',
                'users.name as author_name',
                'users.email as author_email',
                DB::raw('(SELECT COUNT(*) FROM discussion_comments WHERE discussion_comments.discussion_id = discussions.id) as comments_count')
            )
            ->orderBy('discussions.created_at', 'desc');

        // 2. Cek apakah ada request filter kategori
        if ($request->has('category') && !empty($request->query('category'))) {
            $cat = $request->query('category');
            if ($cat === 'BPJS') {
                $query->whereIn('discussions.category', ['BPJS', 'BPJS & Asuransi', 'Tanya BPJS']);
            } elseif ($cat === 'Rekomendasi RS') {
                $query->whereIn('discussions.category', ['Rekomendasi RS', 'Rekomendasi', 'Rekomendasi Faskes']);
            } elseif ($cat === 'Tips Kesehatan') {
                $query->whereIn('discussions.category', ['Tips Kesehatan', 'Tips & Informasi']);
            } else {
                $query->where('discussions.category', $cat);
            }
        }

        // 3. Eksekusi pencarian dengan paginasi
        $discussions = $query->paginate(10);

        return response()->json([
            'message' => 'Berhasil mengambil daftar diskusi',
            'data' => $discussions
        ]);
    }

    // 2. Bikin topik diskusi baru dengan lampiran gambar (hingga 3 foto)
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string',
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|max:5120',
            'image' => 'nullable|image|max:5120',
        ]);

        $user_id = $request->user()->id;

        $imageUrls = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('discussions', 'public');
                    $imageUrls[] = url('storage/' . $path);
                }
            }
        } elseif ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file && $file->isValid()) {
                $path = $file->store('discussions', 'public');
                $imageUrls[] = url('storage/' . $path);
            }
        }

        $firstImage = !empty($imageUrls) ? $imageUrls[0] : null;
        $imagesJson = !empty($imageUrls) ? json_encode($imageUrls) : null;

        $discussionId = DB::table('discussions')->insertGetId([
            'user_id' => $user_id,
            'title' => $request->input('title'),
            'body' => $request->input('content'),
            'category' => $request->input('category', 'Umum'),
            'image_path' => $firstImage,
            'images' => $imagesJson,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Catat ke Log Aktivitas
        DB::table('activity_logs')->insert([
            'user_id' => $user_id,
            'action_type' => 'Buat Diskusi',
            'description' => 'Membuat diskusi baru dengan judul: ' . $request->input('title'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Diskusi berhasil dibuat',
            'discussion_id' => $discussionId
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $currentUser = $request->user(); 

        $discussion = DB::table('discussions')
            ->join('users', 'discussions.user_id', '=', 'users.id')
            ->where('discussions.id', $id)
            ->select(
                'discussions.id as id', 
                'discussions.user_id as user_id', 
                'discussions.title', 
                'discussions.body', 
                'discussions.category', 
                'discussions.image_path',
                'discussions.images',
                'discussions.status', 
                'discussions.created_at',
                'users.name as author_name',
                'users.email as author_email'
            ) 
            ->first();

        if (!$discussion) {
            return response()->json(['message' => 'Diskusi tidak ditemukan'], 404);
        }

        // Sediakan daftar array gambar untuk kenyamanan rendering di frontend
        $imagesList = [];
        if (!empty($discussion->images)) {
            $decoded = json_decode($discussion->images, true);
            if (is_array($decoded)) {
                $imagesList = $decoded;
            }
        }
        if (empty($imagesList) && !empty($discussion->image_path)) {
            $imagesList = [$discussion->image_path];
        }
        $discussion->images_list = $imagesList;

        $comments = DB::table('discussion_comments')
        ->join('users', 'discussion_comments.user_id', '=', 'users.id')
        ->where('discussion_comments.discussion_id', $id)
        ->select(
            'discussion_comments.id as id',
            'discussion_comments.user_id as user_id',
            'discussion_comments.discussion_id',
            'discussion_comments.parent_id',
            'discussion_comments.body',
            'discussion_comments.image_path',
            'discussion_comments.created_at',
            'users.name as commentator_name',
            'users.email as commentator_email'
        )
        ->orderBy('discussion_comments.created_at', 'asc')
        ->get();

        return response()->json([
            'message' => 'Berhasil',
            'data' => [
                'discussion' => $discussion,
                'comments' => $comments,
                'logged_in_user_id' => $currentUser ? $currentUser->id : null 
            ]
        ]);
    }

    // 4. Laporkan diskusi (Report)
    public function report(Request $request, $id)
    {
        // Validasi alasan pelaporan
        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        // Cek apakah diskusinya ada
        $discussionExists = DB::table('discussions')->where('id', $id)->exists();
        
        if (!$discussionExists) {
            return response()->json(['message' => 'Diskusi tidak ditemukan'], 404);
        }

        // Simpan laporan ke database
        DB::table('discussion_reports')->insert([
            'discussion_id' => $id,
            'user_id'       => $request->user()->id, // Asumsi pakai auth sanctum
            'reason'        => $request->input('reason'),
            'status'        => 'pending', // Status awal: menunggu tinjauan admin
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'message' => 'Laporan berhasil dikirim dan akan segera ditinjau oleh tim kami.'
        ], 201);
    }

    // 5. Mengambil riwayat diskusi milik user yang sedang login
    public function myDiscussions(Request $request)
    {
        $userId = $request->user()->id;

        $discussions = DB::table('discussions')
            ->join('users', 'discussions.user_id', '=', 'users.id')
            ->where('discussions.user_id', $userId)
            ->select('discussions.*', 'users.name as author_name')
            ->orderBy('discussions.created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil daftar diskusi saya',
            'data' => $discussions
        ]);
    }

    // 6. Mengambil riwayat tanggapan/komentar milik user yang sedang login
    public function myComments(Request $request)
    {
        $userId = $request->user()->id;

        $comments = DB::table('discussion_comments')
            ->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
            ->where('discussion_comments.user_id', $userId)
            ->select(
                'discussion_comments.*',
                'discussions.title as discussion_title'
            )
            ->orderBy('discussion_comments.created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil daftar tanggapan saya',
            'data' => $comments
        ]);
    }

    // 7. Hapus diskusi milik user sendiri
    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;

        $discussion = DB::table('discussions')->where('id', $id)->first();

        if (!$discussion) {
            return response()->json(['status' => 'error', 'message' => 'Diskusi tidak ditemukan'], 404);
        }

        $isAdmin = method_exists($request->user(), 'isAdmin') && $request->user()->isAdmin();
        
        // [FIXED] Gunakan casting (int) untuk pencegahan mismatch tipe data
        if ((int) $discussion->user_id !== (int) $userId && !$isAdmin) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin untuk menghapus diskusi ini'], 403);
        }

        DB::table('discussions')->where('id', $id)->update([
            'status'     => 'deleted',
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Diskusi berhasil dihapus']);
    }
}

