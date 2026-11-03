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
    private function getTypeSlug($jenis_faskes)
    {
        $tipeLower = strtolower($jenis_faskes ?? '');
        if (str_contains($tipeLower, 'rumah sakit') || str_contains($tipeLower, 'rs')) {
            return 'hospital';
        } elseif (str_contains($tipeLower, 'puskesmas')) {
            return 'puskesmas';
        } elseif (str_contains($tipeLower, 'klinik') || str_contains($tipeLower, 'dokter') || str_contains($tipeLower, 'apotek')) {
            return 'clinic';
        }
        return 'other';
    }

    private function formatFaskesName($name, $typeSlug, $jenis_faskes = '')
    {
        $name = trim($name ?? '');
        $isPuskesmas = ($typeSlug === 'puskesmas') || str_contains(strtolower($jenis_faskes ?? ''), 'puskesmas') || str_contains(strtolower($name), 'puskesmas');
        if ($isPuskesmas && !preg_match('/^(puskesmas|pkm)\b/i', $name)) {
            return 'Puskesmas ' . $name;
        }
        return $name;
    }

    private function getGoogleMapsUrl($name, $lat = null, $lng = null, $kota = '', $alamat_faskes = '')
    {
        $parts = array_filter([trim($name), trim($alamat_faskes), trim($kota)]);
        $queryName = implode(', ', $parts);
        $encodedName = urlencode($queryName);

        if ($lat && $lng) {
            return "https://www.google.com/maps/search/{$encodedName}/@{$lat},{$lng},17z";
        }

        return "https://www.google.com/maps/search/?api=1&query={$encodedName}";
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
                    ->orWhere('faskes.alamat_faskes', 'like', "%{$search}%")
                    ->orWhere('faskes.kota_kabupaten', 'like', "%{$search}%")
                    ->orWhere('faskes.provinsi', 'like', "%{$search}%");
            });
        }

        // Filter BPJS (1 / 0 / true / false)
        if ($request->has('_support_bpjs') && $request->_support_bpjs !== null && $request->_support_bpjs !== '') {
            $bpjsVal = filter_var($request->_support_bpjs, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $query->where('faskes._support_bpjs', $bpjsVal);
        }

        // Filter Jenis / Tipe Faskes
        if ($request->filled('jenis_faskes') || $request->filled('tipe') || $request->filled('type')) {
            $typeInput = $request->input('jenis_faskes') ?? $request->input('tipe') ?? $request->input('type');
            if ($typeInput === 'hospital') {
                $query->where(function ($q) {
                    $q->where('faskes.jenis_faskes', 'like', '%Rumah Sakit%')
                        ->orWhere('faskes.jenis_faskes', 'like', '%RS%');
                });
            } elseif ($typeInput === 'clinic') {
                $query->where(function ($q) {
                    $q->where('faskes.jenis_faskes', 'like', '%Klinik%')
                        ->orWhere('faskes.jenis_faskes', 'like', '%Dokter%')
                        ->orWhere('faskes.jenis_faskes', 'like', '%Apotek%');
                });
            } elseif ($typeInput === 'puskesmas') {
                $query->where('faskes.jenis_faskes', 'like', '%Puskesmas%');
            } else {
                $query->where('faskes.jenis_faskes', 'like', "%{$typeInput}%");
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
                    ->orWhere('faskes.alamat_faskes', 'like', "%{$lokasi}%");
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
            $query->select('faskes.*')->orderBy('faskes.id_faskes', 'asc');
        }

        $limitCount = min((int) $request->input('limit', $request->input('per_page', 30)), 300);

        // All non-paginated
        if ($request->boolean('all') || $request->input('paginate') === 'false') {
            $faskes = $query->limit($limitCount)->get();

            $faskesIds = $faskes->pluck('id_faskes')->toArray();
            $comments = DB::table('feedback_faskes')
                ->whereIn('id_faskes', $faskesIds)
                ->select('id_faskes', DB::raw('0 as avg_rating'), DB::raw('COUNT(id_feedback_faskes) as total_reviews'))
                ->groupBy('id_faskes')
                ->get()
                ->keyBy('id_faskes');

            $faskes->transform(function ($item) use ($comments) {
                $item->type_slug = $this->getTypeSlug($item->jenis_faskes);
                $item->nama_faskes = $this->formatFaskesName($item->nama_faskes, $item->type_slug, $item->jenis_faskes);
                $hasComment = isset($comments[$item->id_faskes]) && $comments[$item->id_faskes]->total_reviews > 0;
                $item->avg_rating = $hasComment ? (string) $comments[$item->id_faskes]->avg_rating : null;
                $item->total_reviews = $hasComment ? (int) $comments[$item->id_faskes]->total_reviews : 0;
                $hasSpecific = !empty($item->link_maps) && !in_array($item->link_maps, ['-', 'TRUE', 'FALSE']) && str_starts_with($item->link_maps, 'http') && (str_contains($item->link_maps, '@') || str_contains($item->link_maps, 'place') || str_contains($item->link_maps, 'maps.app.goo.gl') || str_contains($item->link_maps, 'dir'));
                if (!$hasSpecific) {
                    $item->link_maps = $this->getGoogleMapsUrl($item->nama_faskes, $item->latitude ?? null, $item->longitude ?? null, $item->kota_kabupaten ?? '', $item->alamat_faskes ?? '');
                }
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

        $faskesIds = $paginated->getCollection()->pluck('id_faskes')->toArray();
        $comments = DB::table('feedback_faskes')
            ->whereIn('id_faskes', $faskesIds)
            ->select('id_faskes', DB::raw('0 as avg_rating'), DB::raw('COUNT(id_feedback_faskes) as total_reviews'))
            ->groupBy('id_faskes')
            ->get()
            ->keyBy('id_faskes');

        $paginated->getCollection()->transform(function ($item) use ($comments) {
            $item->type_slug = $this->getTypeSlug($item->jenis_faskes);
            $item->nama_faskes = $this->formatFaskesName($item->nama_faskes, $item->type_slug, $item->jenis_faskes);
            $hasComment = isset($comments[$item->id_faskes]) && $comments[$item->id_faskes]->total_reviews > 0;
            $item->avg_rating = $hasComment ? (string) $comments[$item->id_faskes]->avg_rating : null;
            $item->total_reviews = $hasComment ? (int) $comments[$item->id_faskes]->total_reviews : 0;
            $hasSpecific = !empty($item->link_maps) && !in_array($item->link_maps, ['-', 'TRUE', 'FALSE']) && str_starts_with($item->link_maps, 'http') && (str_contains($item->link_maps, '@') || str_contains($item->link_maps, 'place') || str_contains($item->link_maps, 'maps.app.goo.gl') || str_contains($item->link_maps, 'dir'));
            if (!$hasSpecific) {
                $item->link_maps = $this->getGoogleMapsUrl($item->nama_faskes, $item->latitude ?? null, $item->longitude ?? null, $item->kota_kabupaten ?? '', $item->alamat_faskes ?? '');
            }
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
        // 1. Ambil data utama faskesnya beserta agregasi feedback
        $faskes = DB::table('faskes')
            ->leftJoin('feedback_faskes', 'faskes.id_faskes', '=', 'feedback_faskes.id_faskes')
            ->where('faskes.id_faskes', $id)
            ->select(
                'faskes.*',
                DB::raw('0 as avg_rating'),
                DB::raw('COUNT(feedback_faskes.id_feedback_faskes) as total_reviews')
            )
            ->groupBy('faskes.id_faskes')
            ->first();

        if (!$faskes) {
            return response()->json(['message' => 'Fasilitas kesehatan tidak ditemukan'], 404);
        }

        $faskes->type_slug = $this->getTypeSlug($faskes->jenis_faskes);
        $faskes->nama_faskes = $this->formatFaskesName($faskes->nama_faskes, $faskes->type_slug, $faskes->jenis_faskes);
        if ($faskes->total_reviews == 0) {
            $faskes->avg_rating = null;
        } else {
            $faskes->avg_rating = (string) $faskes->avg_rating;
        }

        $hasSpecific = !empty($faskes->link_maps) && !in_array($faskes->link_maps, ['-', 'TRUE', 'FALSE']) && str_starts_with($faskes->link_maps, 'http') && (str_contains($faskes->link_maps, '@') || str_contains($faskes->link_maps, 'place') || str_contains($faskes->link_maps, 'maps.app.goo.gl') || str_contains($faskes->link_maps, 'dir'));
        if (!$hasSpecific) {
            $faskes->link_maps = $this->getGoogleMapsUrl($faskes->nama_faskes, $faskes->latitude ?? null, $faskes->longitude ?? null, $faskes->kota_kabupaten ?? '', $faskes->alamat_faskes ?? '');
        }

        // 2. Ambil semua ulasan yang terhubung ke faskes ini
        $reviews = DB::table('feedback_faskes')
            ->join('user', 'feedback_faskes.id_user', '=', 'user.id_user')
            ->where('feedback_faskes.id_faskes', $id)
            ->select('feedback_faskes.*', 'user.nama_user as reviewer_name', 'user.email_user as reviewer_email')
            ->orderBy('feedback_faskes.id_feedback_faskes', 'desc')
            ->get();

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
            'jenis_faskes' => 'required|string|max:100',
            'alamat_faskes' => 'required|string',
            'kota_kabupaten' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'kelas' => 'nullable|string|max:10',
            '_support_bpjs' => 'nullable',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'link_maps' => 'nullable|string',
        ]);

        $bpjsInput = $request->input('_support_bpjs');
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
        $lat = $request->filled('latitude') ? (float) $request->latitude : null;
        $lng = $request->filled('longitude') ? (float) $request->longitude : null;
        $linkMaps = trim($request->input('link_maps', '') ?? '');

        // Smart link & coordinate extraction
        if (!empty($linkMaps) && $linkMaps !== '-' && $linkMaps !== 'TRUE' && $linkMaps !== 'FALSE') {
            if ($lat === null || $lng === null) {
                if (preg_match('/[?&](?:q|destination)=([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $linkMaps, $m)) {
                    $lat = (float) $m[1];
                    $lng = (float) $m[2];
                } elseif (preg_match('/@([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $linkMaps, $m)) {
                    $lat = (float) $m[1];
                    $lng = (float) $m[2];
                }
            }
        } else {
            if ($lat !== null && $lng !== null) {
                $linkMaps = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
            } else {
                $linkMaps = "https://www.google.com/maps/search/?api=1&query=" . urlencode($namaFaskes . ' ' . $kotaKab);
            }
        }

        $id = DB::table('faskes')->insertGetId([
            'nama_faskes' => $namaFaskes,
            'jenis_faskes' => $request->jenis_faskes,
            'kelas' => $kelasInput,
            'provinsi' => $request->provinsi ?? 'DKI Jakarta',
            'kota_kabupaten' => $kotaKab,
            'kecamatan' => $request->kecamatan ?? null,
            'alamat_faskes' => $request->alamat_faskes,
            'link_maps' => $linkMaps,
            'latitude' => $lat,
            'longitude' => $lng,
            '_support_bpjs' => $isBpjs,
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
        $faskes = DB::table('faskes')->where('id_faskes', $id)->first();
        if (!$faskes) {
            return response()->json(['message' => 'Fasilitas kesehatan tidak ditemukan'], 404);
        }

        $request->validate([
            'nama_faskes' => 'sometimes|required|string|max:255',
            'jenis_faskes' => 'sometimes|required|string|max:100',
            'alamat_faskes' => 'sometimes|required|string',
            'kota_kabupaten' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'kelas' => 'nullable|string|max:50',
            '_support_bpjs' => 'nullable',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'link_maps' => 'nullable|string',
        ]);

        $updateData = [];

        if ($request->has('nama_faskes'))
            $updateData['nama_faskes'] = $request->nama_faskes;
        if ($request->has('jenis_faskes'))
            $updateData['jenis_faskes'] = $request->jenis_faskes;
        if ($request->has('kelas')) {
            $kelasVal = $request->kelas;
            if ($kelasVal === '' || $kelasVal === 'unknown' || $kelasVal === 'Tidak Diketahui') {
                $updateData['kelas'] = null;
            } else {
                $updateData['kelas'] = $kelasVal;
            }
        }
        if ($request->has('provinsi'))
            $updateData['provinsi'] = $request->provinsi;
        if ($request->has('kota_kabupaten'))
            $updateData['kota_kabupaten'] = $request->kota_kabupaten;
        if ($request->has('alamat_faskes'))
            $updateData['alamat_faskes'] = $request->alamat_faskes;

        $targetLat = $request->has('latitude') && $request->filled('latitude') ? (float) $request->latitude : ($faskes->latitude !== null ? (float) $faskes->latitude : null);
        $targetLng = $request->has('longitude') && $request->filled('longitude') ? (float) $request->longitude : ($faskes->longitude !== null ? (float) $faskes->longitude : null);

        if ($request->has('link_maps')) {
            $linkMaps = trim($request->input('link_maps', '') ?? '');
            if (!empty($linkMaps) && $linkMaps !== '-' && $linkMaps !== 'TRUE' && $linkMaps !== 'FALSE') {
                if (!$request->filled('latitude') || !$request->filled('longitude')) {
                    if (preg_match('/[?&](?:q|destination)=([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $linkMaps, $m)) {
                        $targetLat = (float) $m[1];
                        $targetLng = (float) $m[2];
                        $updateData['latitude'] = $targetLat;
                        $updateData['longitude'] = $targetLng;
                    } elseif (preg_match('/@([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $linkMaps, $m)) {
                        $targetLat = (float) $m[1];
                        $targetLng = (float) $m[2];
                        $updateData['latitude'] = $targetLat;
                        $updateData['longitude'] = $targetLng;
                    }
                }
                $updateData['link_maps'] = $linkMaps;
            } else {
                if ($targetLat !== null && $targetLng !== null) {
                    $updateData['link_maps'] = "https://www.google.com/maps/search/?api=1&query={$targetLat},{$targetLng}";
                } else {
                    $namaFaskes = $request->nama_faskes ?? $faskes->nama_faskes;
                    $kota = $request->kota_kabupaten ?? $faskes->kota_kabupaten;
                    $updateData['link_maps'] = "https://www.google.com/maps/search/?api=1&query=" . urlencode($namaFaskes . ' ' . $kota);
                }
            }
        }

        if ($request->has('latitude'))
            $updateData['latitude'] = $request->filled('latitude') ? (float) $request->latitude : null;
        if ($request->has('longitude'))
            $updateData['longitude'] = $request->filled('longitude') ? (float) $request->longitude : null;

        if ($request->has('_support_bpjs')) {
            $bpjsVal = $request->_support_bpjs;
            if ($bpjsVal === '' || $bpjsVal === 'unknown' || $bpjsVal === 'null' || $bpjsVal === null) {
                $updateData['_support_bpjs'] = null;
            } else {
                $updateData['_support_bpjs'] = filter_var($bpjsVal, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }

        DB::table('faskes')->where('id_faskes', $id)->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Fasilitas kesehatan berhasil diperbarui'
        ]);
    }

    // 5. Hapus Faskes (Admin)
    public function destroy($id)
    {
        $faskes = DB::table('faskes')->where('id_faskes', $id)->first();
        if (!$faskes) {
            return response()->json(['message' => 'Fasilitas kesehatan tidak ditemukan'], 404);
        }

        DB::table('faskes')->where('id_faskes', $id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Fasilitas kesehatan berhasil dihapus'
        ]);
    }

    // 6. Ambil Statistik Dashboard Admin
    public function getAdminStats()
    {
        $totalUsers = DB::table('user')->count();
        $totalFaskes = DB::table('faskes')->count();
        $totalDiscussions = DB::table('diskusi')->count();

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