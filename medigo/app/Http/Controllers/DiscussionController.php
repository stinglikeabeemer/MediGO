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
        $query = DB::table('diskusi')
            ->join('user', 'diskusi.id_user', '=', 'user.id_user')
            ->where('diskusi.status_diskusi', 'active')
            ->select(
                'diskusi.*',
                'user.nama_user as author_name',
                'user.email_user as author_email',
                DB::raw('(SELECT COUNT(*) FROM tanggapan_diskusi WHERE tanggapan_diskusi.id_diskusi = diskusi.id_diskusi) as comments_count')
            )
            ->orderBy('diskusi.created_at', 'desc');

        // 2. Cek apakah ada request filter kategori
        if ($request->has('category') && !empty($request->query('category'))) {
            $cat = $request->query('category');
            if ($cat === 'BPJS') {
                $query->whereIn('diskusi.kategori', ['BPJS', 'BPJS & Asuransi', 'Tanya BPJS']);
            } elseif ($cat === 'Rekomendasi RS') {
                $query->whereIn('diskusi.kategori', ['Rekomendasi RS', 'Rekomendasi', 'Rekomendasi Faskes']);
            } elseif ($cat === 'Tips Kesehatan') {
                $query->whereIn('diskusi.kategori', ['Tips Kesehatan', 'Tips & Informasi']);
            } else {
                $query->where('diskusi.kategori', $cat);
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

        $user_id = $request->user()->id_user ?? $request->user()->id;

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

        $discussionId = DB::table('diskusi')->insertGetId([
            'id_user' => $user_id,
            'judul_diskusi' => $request->input('title'),
            'isi_diskusi' => $request->input('content'),
            'kategori' => $request->input('category', 'Umum'),
            'gambar_diskusi' => $firstImage,
            'images' => $imagesJson,
            'status_diskusi' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Catat ke Log Aktivitas
        DB::table('log_aktivitas')->insert([
            'id_user' => $user_id,
            'tipe_aksi' => 'Buat Diskusi',
            'deskripsi' => 'Membuat diskusi baru dengan judul: ' . $request->input('title'),
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
        $currentUserId = $currentUser ? ($currentUser->id_user ?? $currentUser->id) : null;

        $discussion = DB::table('diskusi')
            ->join('user', 'diskusi.id_user', '=', 'user.id_user')
            ->where('diskusi.id_diskusi', $id)
            ->select(
                'diskusi.id_diskusi as id',
                'diskusi.id_user as user_id',
                'diskusi.judul_diskusi as title',
                'diskusi.isi_diskusi as body',
                'diskusi.kategori as category',
                'diskusi.gambar_diskusi as image_path',
                'diskusi.images',
                'diskusi.status_diskusi as status',
                'diskusi.created_at',
                'user.nama_user as author_name',
                'user.email_user as author_email'
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

        $comments = DB::table('tanggapan_diskusi')
            ->join('user', 'tanggapan_diskusi.id_user', '=', 'user.id_user')
            ->where('tanggapan_diskusi.id_diskusi', $id)
            ->select(
                'tanggapan_diskusi.id_tanggapan as id',
                'tanggapan_diskusi.id_user as user_id',
                'tanggapan_diskusi.id_diskusi as discussion_id',
                'tanggapan_diskusi.id_parent as parent_id',
                'tanggapan_diskusi.isi_tanggapan as body',
                'tanggapan_diskusi.gambar_tanggapan as image_path',
                'tanggapan_diskusi.created_at',
                'user.nama_user as commentator_name',
                'user.email_user as commentator_email'
            )
            ->orderBy('tanggapan_diskusi.created_at', 'asc')
            ->get();

        return response()->json([
            'message' => 'Berhasil',
            'data' => [
                'discussion' => $discussion,
                'comments' => $comments,
                'logged_in_user_id' => $currentUserId
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
        $discussionExists = DB::table('diskusi')->where('id_diskusi', $id)->exists();

        if (!$discussionExists) {
            return response()->json(['message' => 'Diskusi tidak ditemukan'], 404);
        }

        $user_id = $request->user()->id_user ?? $request->user()->id;

        // Simpan laporan ke database
        DB::table('laporan_diskusi')->insert([
            'id_diskusi' => $id,
            'id_user' => $user_id, // Asumsi pakai auth sanctum
            'alasan_laporan' => $request->input('reason'),
            'status' => 'pending', // Status awal: menunggu tinjauan admin
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Laporan berhasil dikirim dan akan segera ditinjau oleh tim kami.'
        ], 201);
    }

    // 5. Mengambil riwayat diskusi milik user yang sedang login
    public function myDiscussions(Request $request)
    {
        $userId = $request->user()->id_user ?? $request->user()->id;

        $discussions = DB::table('diskusi')
            ->join('user', 'diskusi.id_user', '=', 'user.id_user')
            ->where('diskusi.id_user', $userId)
            ->select('diskusi.*', 'user.nama_user as author_name')
            ->orderBy('diskusi.created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil daftar diskusi saya',
            'data' => $discussions
        ]);
    }

    // 6. Mengambil riwayat tanggapan/komentar milik user yang sedang login
    public function myComments(Request $request)
    {
        $userId = $request->user()->id_user ?? $request->user()->id;

        $comments = DB::table('tanggapan_diskusi')
            ->join('diskusi', 'tanggapan_diskusi.id_diskusi', '=', 'diskusi.id_diskusi')
            ->where('tanggapan_diskusi.id_user', $userId)
            ->select(
                'tanggapan_diskusi.*',
                'diskusi.judul_diskusi as discussion_title'
            )
            ->orderBy('tanggapan_diskusi.created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil daftar tanggapan saya',
            'data' => $comments
        ]);
    }

    // 7. Hapus diskusi milik user sendiri
    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id_user ?? $request->user()->id;

        $discussion = DB::table('diskusi')->where('id_diskusi', $id)->first();

        if (!$discussion) {
            return response()->json(['status' => 'error', 'message' => 'Diskusi tidak ditemukan'], 404);
        }

        $userRole = $request->user()->role_user ?? $request->user()->role ?? '';
        $isAdmin = method_exists($request->user(), 'isAdmin') ? $request->user()->isAdmin() : ($userRole === 'admin');

        // Gunakan casting (int) untuk pencegahan mismatch tipe data
        if ((int) $discussion->id_user !== (int) $userId && !$isAdmin) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki izin untuk menghapus diskusi ini'], 403);
        }

        DB::table('diskusi')->where('id_diskusi', $id)->update([
            'status_diskusi' => 'deleted',
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Diskusi berhasil dihapus']);
    }
}