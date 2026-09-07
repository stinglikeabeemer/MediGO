<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EnrichFaskesCoordinates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faskes:enrich
                            {--provinsi= : Filter by province name}
                            {--kota= : Filter by city/regency name}
                            {--limit=50 : Maximum number of records to process}
                            {--all : Force re-geocoding of all records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Geocode and populate accurate GPS coordinates and Google Maps links for faskes in the database with live feedback';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('======================================================================');
        $this->info('  🏥  MediGO Faskes Database Geocoder & Link Maps Live Updater  🏥');
        $this->info('======================================================================');

        $provinsi = $this->option('provinsi');
        $kota = $this->option('kota');
        $limit = (int) $this->option('limit');
        $forceAll = $this->option('all');

        $query = DB::table('faskes');

        if ($provinsi) {
            $query->where('provinsi', 'LIKE', "%{$provinsi}%");
        }
        if ($kota) {
            $query->where('kota_kabupaten', 'LIKE', "%{$kota}%");
        }

        if (!$forceAll) {
            // Target records where link_maps is missing, '-' or generic search, or coordinates are null/0
            $query->where(function ($q) {
                $q->whereNull('link_maps')
                  ->orWhere('link_maps', '=', '')
                  ->orWhere('link_maps', '=', '-')
                  ->orWhere('link_maps', 'LIKE', '%maps/search/?api=1%')
                  ->orWhereNull('latitude')
                  ->orWhereNull('longitude')
                  ->orWhere('latitude', '=', 0);
            });
        }

        $totalRecords = $query->count();
        $this->info("Ditemukan {$totalRecords} faskes yang perlu diperbarui koordinatnya.");

        if ($totalRecords === 0) {
            $this->info('Semua data faskes yang dipilih sudah memiliki koordinat dan link maps presisi!');
            return Command::SUCCESS;
        }

        $records = $query->limit($limit)->get();
        $this->info("Memulai proses update untuk " . $records->count() . " faskes...\n");

        $successCount = 0;
        $failedCount = 0;
        $currentIndex = 0;
        $totalBatch = $records->count();

        foreach ($records as $faskes) {
            $currentIndex++;
            $coords = $this->geocodeFaskes($faskes->nama_faskes, $faskes->kota_kabupaten, $faskes->provinsi, $faskes->alamat);

            if ($coords) {
                $lat = $coords['lat'];
                $lng = $coords['lng'];
                $matchedName = $coords['matched_name'] ?? $faskes->nama_faskes;
                $engine = $coords['engine'] ?? 'Photon OSM';
                $link = "https://www.google.com/maps?q={$lat},{$lng}";

                DB::table('faskes')
                    ->where('id', $faskes->id)
                    ->update([
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'link_maps' => $link,
                        'updated_at' => now(),
                    ]);

                $successCount++;
                
                // Tampilkan info faskes dan koordinat yang berhasil diupdate
                $this->line("<fg=green;options=bold>[{$currentIndex}/{$totalBatch}] [BERHASIL - {$engine}]</> <fg=white;options=bold>#{$faskes->id} {$faskes->nama_faskes}</> ({$faskes->kota_kabupaten})");
                $this->line("   📍 <fg=cyan;options=bold>Koordinat : {$lat}, {$lng}</>");
                $this->line("   🔗 <fg=gray>Google Maps: {$link}</>\n");
            } else {
                $failedCount++;
                $this->line("<fg=yellow>[{$currentIndex}/{$totalBatch}] [BELUM KETEMU]</> #{$faskes->id} {$faskes->nama_faskes} ({$faskes->kota_kabupaten})\n");
            }

            // Jeda 0.2 detik agar aman dari rate limit
            usleep(200000);
        }

        $this->info('======================================================================');
        $this->info('  🎉  Ringkasan Pembaruan Database MySQL MediGO');
        $this->info('======================================================================');
        $this->info("Total Faskes Diproses : {$totalBatch}");
        $this->info("Berhasil Diperbarui   : {$successCount} faskes (" . ($totalBatch > 0 ? round(($successCount / $totalBatch) * 100, 1) : 0) . "%)");
        if ($failedCount > 0) {
            $this->warn("Belum Ditemukan       : {$failedCount} faskes");
        }
        $this->info('======================================================================');

        return Command::SUCCESS;
    }

    /**
     * Clean hospital / clinic name for optimal geocoding
     */
    protected function cleanName(string $name): string
    {
        $n = trim($name);
        $n = preg_replace('/\s+/', ' ', $n);
        $replacements = [
            '/\bRS Umum Daerah\b/i' => 'RSUD',
            '/\bRS Umum\b/i' => 'RS',
            '/\bRS Ibu dan Anak\b/i' => 'RSIA',
            '/\bRS Khusus\b/i' => 'RSK',
            '/\bKab\.\b/i' => 'Kabupaten',
            '/\bKec\.\b/i' => 'Kecamatan',
        ];
        return preg_replace(array_keys($replacements), array_values($replacements), $n);
    }

    /**
     * Resolve GPS coordinates using multi-engine cascade
     */
    protected function geocodeFaskes(string $name, ?string $city, ?string $prov, ?string $address): ?array
    {
        $cleaned = $this->cleanName($name);

        $queries = [
            trim("{$name} {$city} {$prov}"),
            trim("{$cleaned} {$city}"),
            trim("{$name} {$city}"),
            trim($cleaned),
            trim($name)
        ];

        $stopwords = [
            'rs', 'rsu', 'rsud', 'rsia', 'rsk', 'rumah', 'sakit', 'umum', 'daerah', 'khusus',
            'klinik', 'pratama', 'utama', 'apotek', 'puskesmas', 'kab', 'kota', 'kabupaten',
            'kecamatan', 'dr', 'dokter', 'desa', 'kelurahan', 'medika', 'medical', 'medic',
            'center', 'centre', 'hospital', 'sehat', 'husada', 'bhakti', 'bakti', 'mitra',
            'graha', 'kasih', 'insani', 'care', 'klinika'
        ];

        // Extract distinctive tokens
        preg_match_all('/\b[a-zA-Z0-9]+\b/', strtolower($name), $matches);
        $targetTokens = array_filter($matches[0] ?? [], function ($w) use ($stopwords) {
            return strlen($w) >= 3 && !in_array($w, $stopwords);
        });

        // 1. Try Photon Engine
        foreach ($queries as $q) {
            try {
                $response = Http::timeout(4)->get('https://photon.komoot.io/api/', [
                    'q' => $q,
                    'limit' => 3,
                    'lat' => -2.5,
                    'lon' => 118.0
                ]);

                if ($response->successful()) {
                    $features = $response->json('features', []);
                    foreach ($features as $f) {
                        $candidateName = strtolower($f['properties']['name'] ?? '');
                        
                        // Verify distinctive brand tokens match
                        $isValid = true;
                        foreach ($targetTokens as $tok) {
                            if (strpos($candidateName, $tok) === false && similar_text($tok, $candidateName) < (strlen($tok) * 0.75)) {
                                $isValid = false;
                                break;
                            }
                        }

                        if (!$isValid && !empty($targetTokens)) {
                            continue;
                        }

                        $coords = $f['geometry']['coordinates'] ?? [];
                        if (count($coords) >= 2) {
                            $lng = (float) $coords[0];
                            $lat = (float) $coords[1];
                            if ($lat >= -11.5 && $lat <= 6.5 && $lng >= 94.5 && $lng <= 141.5) {
                                return [
                                    'lat' => round($lat, 8),
                                    'lng' => round($lng, 8),
                                    'matched_name' => $f['properties']['name'] ?? $name,
                                    'engine' => 'Photon OSM'
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue
            }
        }

        // 2. Try Nominatim OSM Fallback
        foreach (array_slice($queries, 0, 2) as $q) {
            try {
                $response = Http::withHeaders(['User-Agent' => 'MediGO-App/1.0'])
                    ->timeout(4)
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $q,
                        'format' => 'json',
                        'countrycodes' => 'id',
                        'limit' => 2
                    ]);

                if ($response->successful()) {
                    $items = $response->json();
                    if (!empty($items) && is_array($items)) {
                        $item = $items[0];
                        $lat = (float) ($item['lat'] ?? 0);
                        $lng = (float) ($item['lon'] ?? 0);
                        if ($lat >= -11.5 && $lat <= 6.5 && $lng >= 94.5 && $lng <= 141.5) {
                            return [
                                'lat' => round($lat, 8),
                                'lng' => round($lng, 8),
                                'matched_name' => $item['display_name'] ?? $name,
                                'engine' => 'Nominatim OSM'
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue
            }
        }

        // 3. Try Street Address Fallback
        if ($address && strlen($address) > 8) {
            $cleanAddr = preg_replace('/RT\.\d+\/RW\.\d+/i', '', $address);
            if (preg_match('/(Jl\.?|Jalan)\s+[^,]+/i', $cleanAddr, $m)) {
                $streetQuery = "{$m[0]}, {$city}, Indonesia";
                try {
                    $response = Http::timeout(4)->get('https://photon.komoot.io/api/', [
                        'q' => $streetQuery,
                        'limit' => 2,
                        'lat' => -2.5,
                        'lon' => 118.0
                    ]);
                    if ($response->successful()) {
                        $features = $response->json('features', []);
                        if (!empty($features)) {
                            $coords = $features[0]['geometry']['coordinates'] ?? [];
                            if (count($coords) >= 2) {
                                $lng = (float) $coords[0];
                                $lat = (float) $coords[1];
                                if ($lat >= -11.5 && $lat <= 6.5 && $lng >= 94.5 && $lng <= 141.5) {
                                    return [
                                        'lat' => round($lat, 8),
                                        'lng' => round($lng, 8),
                                        'matched_name' => "Alamat: {$m[0]}",
                                        'engine' => 'Street Address'
                                    ];
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Continue
                }
            }
        }

        return null;
    }
}
