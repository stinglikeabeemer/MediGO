<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

use App\Models\Faskes;
use Illuminate\Http\Request;

class FaskesController extends Controller
{
    // 1. Ambil data Faskes (dilengkapi Pencarian & Filter)
    public function index(Request $request)
    {
        $query = Faskes::query();

        // Sesuaikan dengan nama kolom 'nama_faskes'
        if ($request->has('search')) {
            $query->where('nama_faskes', 'like', '%' . $request->search . '%');
        }

        // Sesuaikan dengan nama kolom 'is_support_bpjs'
        if ($request->has('bpjs')) {
            $query->where('is_support_bpjs', $request->bpjs);
        }

        // Sesuaikan dengan nama kolom 'tipe'
        if ($request->has('jenis')) {
            $query->where('tipe', $request->jenis);
        }

        $faskes = $query->paginate(15);

        return response()->json([
            'message' => 'Berhasil mengambil data faskes',
            'data' => $faskes
        ]);
    }

    // 2. Ambil detail spesifik satu Faskes (buat halaman detail)
    public function show($id)
    {
        // 1. Ambil data utama faskesnya
        $faskes = DB::table('faskes')->where('id', $id)->first();

        if (!$faskes) {
            return response()->json(['message' => 'Fasilitas kesehatan tidak ditemukan'], 404);
        }

        // 2. Ambil semua ulasan dan rating yang nyambung ke faskes ini
        $reviews = DB::table('faskes_comments')
            ->join('users', 'faskes_comments.user_id', '=', 'users.id')
            ->where('faskes_comments.faskes_id', $id)
            ->select('faskes_comments.*', 'users.name as reviewer_name')
            ->orderBy('faskes_comments.created_at', 'desc') // Urutkan dari ulasan terbaru
            ->get();

        // 3. Gabungin data faskes dan ulasannya
        return response()->json([
            'message' => 'Berhasil mengambil detail faskes',
            'data' => [
                'faskes' => $faskes,
                'reviews' => $reviews
            ]
        ]);
    }}