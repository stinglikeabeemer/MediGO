<?php

namespace App\Http\Controllers;

use App\Models\Faskes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaskesController extends Controller
{
    /**
     * Map faskes type to a frontend-friendly slug.
     */
    private function getTypeSlug($tipe)
    {
        $tipeLower = strtolower($tipe ?? '');
        if (str_contains($tipeLower, 'rumah sakit') || str_contains($tipeLower, 'rs')) {
            return 'hospital';
        } elseif (str_contains($tipeLower, 'puskesmas')) {
            return 'puskesmas';
        } elseif (str_contains($tipeLower, 'klinik') || str_contains($tipeLower, 'dokter') || str_contains($tipeLower, 'apotek')) {
            return 'clinic';
        }
        return 'other';
    }

    // 1. Ambil data Faskes (dilengkapi Pencarian, GPS, Rating & Filter)
    public function index(Request $request)
    {
        $query = DB::table('faskes');

        // Pencarian (Nama / Alamat / Kota / Provinsi)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('faskes.nama_faskes', 'like', "%{$search}%")
                  ->orWhere('faskes.alamat', 'like', "%{$search}%")
                  ->orWhere('faskes.kota_kabupaten', 'like', "%{$search}%")
                  ->orWhere('faskes.provinsi', 'like', "%{$search}%");
            });
        }

        // Filter BPJS (1 / 0 / true / false)
        if ($request->has('bpjs') && $request->bpjs !== null && $request->bpjs !== '') {
            $bpjsVal = filter_var($request->bpjs, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $query->where('faskes.is_support_bpjs', $bpjsVal);
        }

        // Filter Jenis / Tipe Faskes
        if ($request->filled('jenis') || $request->filled('tipe') || $request->filled('type')) {
            $typeInput = $request->input('jenis') ?? $request->input('tipe') ?? $request->input('type');
            if ($typeInput === 'hospital') {
                $query->where(function ($q) {
                    $q->where('faskes.tipe', 'like', '%Rumah Sakit%')
                      ->orWhere('faskes.tipe', 'like', '%RS%');
                });
            } elseif ($typeInput === 'clinic') {
                $query->where(function ($q) {
                    $q->where('faskes.tipe', 'like', '%Klinik%')
                      ->orWhere('faskes.tipe', 'like', '%Dokter%')
                      ->orWhere('faskes.tipe', 'like', '%Apotek%');
                });
            } elseif ($typeInput === 'puskesmas') {
                $query->where('faskes.tipe', 'like', '%Puskesmas%');
            } else {
                $query->where('faskes.tipe', 'like', "%{$typeInput}%");
            }
        }

        // Filter Provinsi
        if ($request->filled('provinsi')) {
            $query->where('faskes.provinsi', 'like', "%{$request->provinsi}%");
        }

        // Filter Kota / Kabupaten
        if ($request->filled('kota') || $request->filled('kota_kabupaten')) {
            $kota = $request->input('kota') ?? $request->input('kota_kabupaten');
            $query->where('faskes.kota_kabupaten', 'like', "%{$kota}%");
        }

        // Filter Lokasi Umum (Kota / Kabupaten / Provinsi / Alamat)
        if ($request->filled('lokasi')) {
            $lokasi = trim($request->lokasi);
            $query->where(function ($q) use ($lokasi) {
                $q->where('faskes.kota_kabupaten', 'like', "%{$lokasi}%")
                  ->orWhere('faskes.provinsi', 'like', "%{$lokasi}%")
                  ->orWhere('faskes.alamat', 'like', "%{$lokasi}%");
            });
        }

        // Filter Hanya yang memiliki koordinat GPS
        if ($request->boolean('with_coords') || $request->input('with_coords') == '1') {
            $query->whereNotNull('faskes.latitude')
                  ->whereNotNull('faskes.longitude');
        }

        // Filter & Urutkan Berdasarkan Jarak GPS jika lat & lng dikirimkan
        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;

            // Haversine formula (km)
            $haversine = "(6371 * acos(cos(radians({$lat})) * cos(radians(faskes.latitude)) * cos(radians(faskes.longitude) - radians({$lng})) + sin(radians({$lat})) * sin(radians(faskes.latitude))))";
            
            $query->select('faskes.*')
                  ->selectRaw("{$haversine} AS distance_km")
                  ->whereNotNull('faskes.latitude')
                  ->whereNotNull('faskes.longitude');

            // Filter radius jika diberikan
            if ($request->filled('radius')) {
                $radius = (float) $request->radius;
                $query->having('distance_km', '<=', $radius);
            }

            $query->orderBy('distance_km', 'asc');
        } else {
            $query->select('faskes.*')->orderBy('faskes.id', 'asc');
        }

        $limitCount = min((int) $request->input('limit', $request->input('per_page', 30)), 300);

        // All non-paginated
        if ($request->boolean('all') || $request->input('paginate') === 'false') {
            $faskes = $query->limit($limitCount)->get();

            $faskesIds = $faskes->pluck('id')->toArray();
            $comments = DB::table('faskes_comments')
                ->whereIn('faskes_id', $faskesIds)
                ->select('faskes_id', DB::raw('ROUND(AVG(rating), 1) as avg_rating'), DB::raw('COUNT(id) as total_reviews'))
                ->groupBy('faskes_id')
                ->get()
                ->keyBy('faskes_id');

            $faskes->transform(function ($item) use ($comments) {
                $item->type_slug = $this->getTypeSlug($item->tipe);
                $item->avg_rating = isset($comments[$item->id]) ? (string)$comments[$item->id]->avg_rating : '0.0';
                $item->total_reviews = isset($comments[$item->id]) ? (int)$comments[$item->id]->total_reviews : 0;
                return $item;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil mengambil data faskes',
                'total' => $faskes->count(),
                'data' => $faskes
            ]);
        }

        $paginated = $query->paginate($limitCount);

        $faskesIds = $paginated->getCollection()->pluck('id')->toArray();
        $comments = DB::table('faskes_comments')
            ->whereIn('faskes_id', $faskesIds)
            ->select('faskes_id', DB::raw('ROUND(AVG(rating), 1) as avg_rating'), DB::raw('COUNT(id) as total_reviews'))
            ->groupBy('faskes_id')
            ->get()
            ->keyBy('faskes_id');

        $paginated->getCollection()->transform(function ($item) use ($comments) {
            $item->type_slug = $this->getTypeSlug($item->tipe);
            $item->avg_rating = isset($comments[$item->id]) ? (string)$comments[$item->id]->avg_rating : '0.0';
            $item->total_reviews = isset($comments[$item->id]) ? (int)$comments[$item->id]->total_reviews : 0;
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil data faskes',
            'data' => $paginated
        ]);
    }

    // 2. Ambil detail spesifik satu Faskes (buat halaman detail)
    public function show($id)
    {
        // 1. Ambil data utama faskesnya beserta agregasi rating
        $faskes = DB::table('faskes')
            ->leftJoin('faskes_comments', 'faskes.id', '=', 'faskes_comments.faskes_id')
            ->where('faskes.id', $id)
            ->select(
                'faskes.*',
                DB::raw('COALESCE(ROUND(AVG(faskes_comments.rating), 1), 0) as avg_rating'),
                DB::raw('COUNT(faskes_comments.id) as total_reviews')
            )
            ->groupBy('faskes.id')
            ->first();

        if (!$faskes) {
            return response()->json(['message' => 'Fasilitas kesehatan tidak ditemukan'], 404);
        }

        $faskes->type_slug = $this->getTypeSlug($faskes->tipe);

        // 2. Ambil semua ulasan dan rating yang terhubung ke faskes ini
        $reviews = DB::table('faskes_comments')
            ->join('users', 'faskes_comments.user_id', '=', 'users.id')
            ->where('faskes_comments.faskes_id', $id)
            ->select('faskes_comments.*', 'users.name as reviewer_name')
            ->orderBy('faskes_comments.created_at', 'desc')
            ->get();

        // 3. Gabungkan data faskes dan ulasannya
        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil detail faskes',
            'data' => [
                'faskes' => $faskes,
                'reviews' => $reviews
            ]
        ]);
    }

    // 3. Tambah Faskes Baru (Admin)
    public function store(Request $request)
    {
        $request->validate([
            'nama_faskes' => 'required|string|max:255',
            'tipe' => 'required|string|max:100',
            'alamat' => 'required|string',
            'kota_kabupaten' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'kelas' => 'nullable|string|max:10',
            'is_support_bpjs' => 'nullable',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'link_maps' => 'nullable|string',
        ]);

        $bpjsInput = $request->input('is_support_bpjs');
        $isBpjs = null;
        if ($bpjsInput !== null && $bpjsInput !== '' && $bpjsInput !== 'unknown') {
            $isBpjs = filter_var($bpjsInput, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        $kelasInput = $request->input('kelas');
        if ($kelasInput === '' || $kelasInput === 'unknown' || $kelasInput === 'Tidak Diketahui') {
            $kelasInput = null;
        }

        $namaFaskes = trim($request->nama_faskes);
        $kotaKab = $request->kota_kabupaten ?? 'Jakarta Selatan';
        $lat = $request->filled('latitude') ? (float)$request->latitude : null;
        $lng = $request->filled('longitude') ? (float)$request->longitude : null;
        $linkMaps = trim($request->input('link_maps', '') ?? '');

        // Smart link & coordinate extraction
        if (!empty($linkMaps) && $linkMaps !== '-' && $linkMaps !== 'TRUE' && $linkMaps !== 'FALSE') {
            // If coordinates not provided manually, extract from link_maps
            if ($lat === null || $lng === null) {
                if (preg_match('/[?&](?:q|destination)=([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $linkMaps, $m)) {
                    $lat = (float)$m[1];
                    $lng = (float)$m[2];
                } elseif (preg_match('/@([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $linkMaps, $m)) {
                    $lat = (float)$m[1];
                    $lng = (float)$m[2];
                }
            }
        } else {
            // Generate link_maps automatically based on coordinates or name
            if ($lat !== null && $lng !== null) {
                $linkMaps = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
            } else {
                $linkMaps = "https://www.google.com/maps/search/?api=1&query=" . urlencode($namaFaskes . ' ' . $kotaKab);
            }
        }

        $id = DB::table('faskes')->insertGetId([
            'nama_faskes' => $namaFaskes,
            'tipe' => $request->tipe,
            'kelas' => $kelasInput,
            'provinsi' => $request->provinsi ?? 'DKI Jakarta',
            'kota_kabupaten' => $kotaKab,
            'kecamatan' => $request->kecamatan ?? null,
            'alamat' => $request->alamat,
            'link_maps' => $linkMaps,
            'latitude' => $lat,
            'longitude' => $lng,
            'is_support_bpjs' => $isBpjs,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Fasilitas kesehatan berhasil ditambahkan',
            'data' => ['id' => $id]
        ], 201);
    }

    // 4. Update Data Faskes (Admin)
    public function update(Request $request, $id)
    {
        $faskes = DB::table('faskes')->where('id', $id)->first();
        if (!$faskes) {
            return response()->json(['message' => 'Fasilitas kesehatan tidak ditemukan'], 404);
        }

        $request->validate([
            'nama_faskes' => 'sometimes|required|string|max:255',
            'tipe' => 'sometimes|required|string|max:100',
            'alamat' => 'sometimes|required|string',
            'kota_kabupaten' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'kelas' => 'nullable|string|max:50',
            'is_support_bpjs' => 'nullable',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'link_maps' => 'nullable|string',
        ]);

        $updateData = [
            'updated_at' => now(),
        ];

        if ($request->has('nama_faskes')) $updateData['nama_faskes'] = $request->nama_faskes;
        if ($request->has('tipe')) $updateData['tipe'] = $request->tipe;
        if ($request->has('kelas')) {
            $kelasVal = $request->kelas;
            if ($kelasVal === '' || $kelasVal === 'unknown' || $kelasVal === 'Tidak Diketahui') {
                $updateData['kelas'] = null;
            } else {
                $updateData['kelas'] = $kelasVal;
            }
        }
        if ($request->has('provinsi')) $updateData['provinsi'] = $request->provinsi;
        if ($request->has('kota_kabupaten')) $updateData['kota_kabupaten'] = $request->kota_kabupaten;
        if ($request->has('alamat')) $updateData['alamat'] = $request->alamat;

        $targetLat = $request->has('latitude') && $request->filled('latitude') ? (float)$request->latitude : ($faskes->latitude !== null ? (float)$faskes->latitude : null);
        $targetLng = $request->has('longitude') && $request->filled('longitude') ? (float)$request->longitude : ($faskes->longitude !== null ? (float)$faskes->longitude : null);

        if ($request->has('link_maps')) {
            $linkMaps = trim($request->input('link_maps', '') ?? '');
            if (!empty($linkMaps) && $linkMaps !== '-' && $linkMaps !== 'TRUE' && $linkMaps !== 'FALSE') {
                // If coordinates in link, extract if lat/lng not manually provided
                if (!$request->filled('latitude') || !$request->filled('longitude')) {
                    if (preg_match('/[?&](?:q|destination)=([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $linkMaps, $m)) {
                        $targetLat = (float)$m[1];
                        $targetLng = (float)$m[2];
                        $updateData['latitude'] = $targetLat;
                        $updateData['longitude'] = $targetLng;
                    } elseif (preg_match('/@([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $linkMaps, $m)) {
                        $targetLat = (float)$m[1];
                        $targetLng = (float)$m[2];
                        $updateData['latitude'] = $targetLat;
                        $updateData['longitude'] = $targetLng;
                    }
                }
                $updateData['link_maps'] = $linkMaps;
            } else {
                // Auto-generate link based on coordinates if link_maps emptied
                if ($targetLat !== null && $targetLng !== null) {
                    $updateData['link_maps'] = "https://www.google.com/maps/search/?api=1&query={$targetLat},{$targetLng}";
                } else {
                    $namaFaskes = $request->nama_faskes ?? $faskes->nama_faskes;
                    $kota = $request->kota_kabupaten ?? $faskes->kota_kabupaten;
                    $updateData['link_maps'] = "https://www.google.com/maps/search/?api=1&query=" . urlencode($namaFaskes . ' ' . $kota);
                }
            }
        }

        if ($request->has('latitude')) $updateData['latitude'] = $request->filled('latitude') ? (float)$request->latitude : null;
        if ($request->has('longitude')) $updateData['longitude'] = $request->filled('longitude') ? (float)$request->longitude : null;

        if ($request->has('is_support_bpjs')) {
            $bpjsVal = $request->is_support_bpjs;
            if ($bpjsVal === '' || $bpjsVal === 'unknown' || $bpjsVal === 'null' || $bpjsVal === null) {
                $updateData['is_support_bpjs'] = null;
            } else {
                $updateData['is_support_bpjs'] = filter_var($bpjsVal, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }

        DB::table('faskes')->where('id', $id)->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Fasilitas kesehatan berhasil diperbarui'
        ]);
    }

    // 5. Hapus Faskes (Admin)
    public function destroy($id)
    {
        $faskes = DB::table('faskes')->where('id', $id)->first();
        if (!$faskes) {
            return response()->json(['message' => 'Fasilitas kesehatan tidak ditemukan'], 404);
        }

        DB::table('faskes')->where('id', $id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Fasilitas kesehatan berhasil dihapus'
        ]);
    }

    // 6. Ambil Statistik Dashboard Admin
    public function getAdminStats()
    {
        $totalUsers = DB::table('users')->count();
        $totalFaskes = DB::table('faskes')->count();
        $totalDiscussions = DB::table('discussions')->count();

        // Sample recent activity logs
        $activities = [
            [
                'id' => 1,
                'type' => 'forum',
                'icon' => 'bi-chat-left-text',
                'title' => "Topik baru: 'Tips Menjaga Kesehatan Jantung' diposting di Forum Kesehatan.",
                'time' => '3 jam yang lalu'
            ],
            [
                'id' => 2,
                'type' => 'user',
                'icon' => 'bi-person-plus',
                'title' => "Pengguna baru @budi_santoso telah bergabung dengan Medigo.",
                'time' => 'Kemarin'
            ],
            [
                'id' => 3,
                'type' => 'forum',
                'icon' => 'bi-chat-left-text',
                'title' => "Topik baru: 'Tips Menjaga Kesehatan Anak' diposting di Forum Kesehatan.",
                'time' => '3 jam yang lalu'
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_users' => $totalUsers > 0 ? $totalUsers : 12450,
                'total_faskes' => $totalFaskes > 0 ? $totalFaskes : 84,
                'total_discussions' => $totalDiscussions > 0 ? $totalDiscussions : 1200,
                'activities' => $activities
            ]
        ]);
    }
}