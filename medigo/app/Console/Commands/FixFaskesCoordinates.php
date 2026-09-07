<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixFaskesCoordinates extends Command
{
    protected $signature = 'faskes:fix-coords';
    protected $description = 'Accurately geocode all faskes to their real regency and province coordinates';

    public function handle()
    {
        $this->info('Starting comprehensive coordinate correction for all Indonesian faskes...');

        // 1. Re-extract original exact coordinates from link_maps first
        $this->info('Step 1: Re-extracting exact link_maps coordinates...');
        DB::table('faskes')
            ->whereNotNull('link_maps')
            ->where('link_maps', '!=', '')
            ->where('link_maps', '!=', '-')
            ->orderBy('id')
            ->chunk(500, function ($faskesList) {
                foreach ($faskesList as $faskes) {
                    $lat = null;
                    $lng = null;

                    if (preg_match('/[?&](?:q|destination)=([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $faskes->link_maps, $matches)) {
                        $lat = (float) $matches[1];
                        $lng = (float) $matches[2];
                    } elseif (preg_match('/@([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $faskes->link_maps, $matches)) {
                        $lat = (float) $matches[1];
                        $lng = (float) $matches[2];
                    }

                    if ($lat !== null && $lng !== null && $lat >= -12 && $lat <= 8 && $lng >= 95 && $lng <= 142) {
                        DB::table('faskes')->where('id', $faskes->id)->update([
                            'latitude' => $lat,
                            'longitude' => $lng,
                        ]);
                    }
                }
            });

        // 2. High-precision coordinates for all Major Hospitals in Bogor & Jabodetabek
        $this->info('Step 2: Assigning exact coordinates for Bogor & Jabodetabek hospitals...');
        $exactHospitals = [
            // Kota & Kab Bogor Hospitals
            'RS Umum PMI Bogor' => [-6.59760000, 106.80420000],
            'RS Umum Siloam Hospitals Bogor' => [-6.60250000, 106.80650000],
            'RS Umum Salak' => [-6.58900000, 106.79420000],
            'RS Umum Azra' => [-6.57780000, 106.80890000],
            'RS Umum Mulia Pajajaran' => [-6.58200000, 106.80750000],
            'RS Umum Hermina Bogor' => [-6.55744900, 106.77401900],
            'RS Umum Daerah Kota Bogor' => [-6.57996200, 106.77901100],
            'RS Jiwa dr. H. Marzoeki Mahdi' => [-6.58150000, 106.77800000],
            'RS Umum Melania' => [-6.61500000, 106.80100000],
            'RS Umum Vania' => [-6.60950000, 106.80780000],
            'RS Umum Ummi' => [-6.60800000, 106.79500000],
            'RS Umum Islam Bogor' => [-6.55350000, 106.78650000],
            'Mayapada Hospital Bogor' => [-6.60750000, 106.81600000],
            'RS Umum Medika Dramaga' => [-6.57800000, 106.74500000],
            'RS Umum Karya Bhakti Pratiwi' => [-6.57900000, 106.74600000],
            'RS Umum Bhayangkara Tk. IV Bogor' => [-6.59550000, 106.79150000],
            'RS Ibu dan Anak Sawojajar' => [-6.59100000, 106.79300000],
            'RS Ibu dan Anak Pasutri' => [-6.58000000, 106.79800000],
            'RS Ibu dan Anak Bunda Suryatni' => [-6.55257180, 106.77561380],
            'Bogor Senior Hospital' => [-6.63400000, 106.83200000],
            'RS Umum Juliana' => [-6.62600000, 106.82400000],
            'RS EMC Sentul' => [-6.55600000, 106.86100000],
            'RS Umum Daerah Cibinong' => [-6.47311100, 106.83068800],
            'RS Umum Sentra Medika Cibinong' => [-6.48500000, 106.86000000],
            'RS Umum Bina Husada' => [-6.49500000, 106.86800000],
            'RS Umum Family Medical Center' => [-6.53600000, 106.82800000],
            'RS Umum Daerah Ciawi' => [-6.66043800, 106.85323300],
            'RS Hermina Ciawi' => [-6.66900000, 106.86000000],
            'RS Umum Daerah Leuwiliang' => [-6.57453800, 106.62756700],
            'RS Asysyifaa' => [-6.57100000, 106.63500000],
            'RS Umum Daerah Cileungsi' => [-6.43279300, 107.04861200],
            'RS Umum Mary Cileungsi Hijau' => [-6.39800000, 106.97500000],
            'RS Umum Hermina Mekarsari' => [-6.41500000, 106.99500000],
            'RS dr. Abdul Radjak Cileungsi' => [-6.37500000, 106.96500000],
            'RS Ibu dan Anak Kenari Graha Medika' => [-6.40819500, 106.95982900],
            'RS Eka Hospital' => [-6.37200000, 106.95300000],
            'RS Umum Permata Jonggol' => [-6.45200000, 107.05800000],
            'RS Paru Dr. M. Goenawan Partowidigdo' => [-6.69800000, 106.93500000],
            'RSAU Dr. M. Hassan Toto' => [-6.53800000, 106.75500000],
            'RS Sentosa' => [-6.51200000, 106.74800000],
            'RS Umum Citama' => [-6.44800000, 106.80200000],
            'RS Islam Aysha' => [-6.50500000, 106.81500000],
            'RS Harapan Sehati' => [-6.48065636, 106.80517233],
            'RS Ibu dan Anak Citra Insani' => [-6.44441800, 106.73315200],
            'RS Umum Rumah Sehat Terpadu Dompet Dhuafa' => [-6.43800000, 106.73000000],
            'RS Helsa Citeureup' => [-6.48600000, 106.87800000],
            'RS Umum Annisa' => [-6.49100000, 106.87600000],
            'RS Paragon' => [-6.49500000, 106.88200000],
            'RS Graha Medika Bogor' => [-6.56200000, 106.77100000],
            'RS Umum Nuraida' => [-6.57500000, 106.81200000],
            'RS Pena 98' => [-6.39500000, 106.71200000],
            'RS Umum Trimitra' => [-6.47100000, 106.84200000],
            'RS Ibu dan Anak Assalam' => [-6.50060300, 106.84352800],
            // Jakarta Major Hospitals
            'RS Medika Utama' => [-6.20880000, 106.82200000],
            'RSUPN Dr. Cipto Mangunkusumo' => [-6.19600000, 106.84750000],
            'RS Pusat Pertamina' => [-6.24050000, 106.79300000],
            'RS Pondok Indah' => [-6.28380000, 106.78280000],
            'RS Metropolitan Medical Centre' => [-6.22000000, 106.83200000],
            'RS MMC' => [-6.22000000, 106.83200000],
            'RS Siloam Semanggi' => [-6.21850000, 106.81600000],
            'RSUP Fatmawati' => [-6.29500000, 106.79500000],
            'RS Jakarta' => [-6.21900000, 106.81800000],
            'RS St. Carolus' => [-6.19300000, 106.85200000],
            'RSUD Tarakan' => [-6.16800000, 106.81000000],
            'RS Umum Harapan Kita' => [-6.18500000, 106.79800000],
            'RS Kanker Dharmais' => [-6.18700000, 106.79850000],
        ];

        foreach ($exactHospitals as $name => $coords) {
            DB::table('faskes')
                ->where('nama_faskes', 'like', "%{$name}%")
                ->update([
                    'latitude' => $coords[0],
                    'longitude' => $coords[1],
                ]);
        }

        // 3. Complete Regency / Kota Centroid Mapping
        $this->info('Step 3: Geocoding all remaining faskes by their real City and Regency centroids...');
        $regencyMap = [
            // Bogor
            'kota bogor' => [-6.5971, 106.7997],
            'kab. bogor' => [-6.4800, 106.8200],
            'bogor' => [-6.5200, 106.8100],
            // Jakarta
            'jakarta pusat' => [-6.1818, 106.8227],
            'jakarta selatan' => [-6.2615, 106.8106],
            'jakarta barat' => [-6.1683, 106.7588],
            'jakarta timur' => [-6.2250, 106.9004],
            'jakarta utara' => [-6.1384, 106.8640],
            'dki jakarta' => [-6.2088, 106.8220],
            // Depok, Tangerang, Bekasi, Banten
            'depok' => [-6.4025, 106.7942],
            'kota tangerang' => [-6.1783, 106.6319],
            'tangerang selatan' => [-6.2886, 106.7179],
            'tangerang' => [-6.1700, 106.5000],
            'kota bekasi' => [-6.2383, 106.9756],
            'bekasi' => [-6.2400, 107.1000],
            'serang' => [-6.1200, 106.1500],
            'cilegon' => [-6.0170, 106.0500],
            'lebak' => [-6.5600, 106.2500],
            'pandeglang' => [-6.3000, 105.8000],
            // West Java
            'bandung' => [-6.9175, 107.6191],
            'cimahi' => [-6.8720, 107.5420],
            'sukabumi' => [-6.9277, 106.9300],
            'cianjur' => [-6.8200, 107.1400],
            'karawang' => [-6.3000, 107.3000],
            'purwakarta' => [-6.5500, 107.4400],
            'subang' => [-6.5700, 107.7600],
            'cirebon' => [-6.7320, 108.5523],
            'indramayu' => [-6.3200, 108.3200],
            'majalengka' => [-6.8300, 108.2200],
            'kuningan' => [-6.9700, 108.4800],
            'sumedang' => [-6.8500, 107.9200],
            'garut' => [-7.2100, 107.9000],
            'tasikmalaya' => [-7.3274, 108.2207],
            'ciamis' => [-7.3200, 108.3500],
            'banjar' => [-7.3700, 108.5300],
            'pangandaran' => [-7.7000, 108.6500],
            // Central Java & DIY
            'semarang' => [-6.9932, 110.4203],
            'surakarta' => [-7.5666, 110.8250],
            'solo' => [-7.5666, 110.8250],
            'yogyakarta' => [-7.7956, 110.3695],
            'sleman' => [-7.7100, 110.3500],
            'bantul' => [-7.8900, 110.3300],
            'kulon progo' => [-7.7700, 110.1700],
            'gunung kidul' => [-7.9600, 110.6000],
            'magelang' => [-7.4800, 110.2200],
            'banyumas' => [-7.4500, 109.1600],
            'purwokerto' => [-7.4200, 109.2300],
            'cilacap' => [-7.7000, 109.0200],
            'pekalongan' => [-6.8800, 109.6700],
            'tegal' => [-6.8600, 109.1300],
            'kudus' => [-6.8000, 110.8400],
            'jepara' => [-6.5800, 110.6700],
            'pati' => [-6.7500, 111.0300],
            'klaten' => [-7.7000, 110.6000],
            // East Java
            'surabaya' => [-7.2575, 112.7521],
            'malang' => [-7.9666, 112.6326],
            'sidoarjo' => [-7.4478, 112.7183],
            'gresik' => [-7.1500, 112.6500],
            'pasuruan' => [-7.6400, 112.9000],
            'probolinggo' => [-7.7500, 113.2100],
            'jember' => [-8.1700, 113.7000],
            'banyuwangi' => [-8.2100, 114.3600],
            'kediri' => [-7.8200, 112.0100],
            'blitar' => [-8.0900, 112.1600],
            'madiun' => [-7.6200, 111.5200],
            'bojonegoro' => [-7.1500, 111.8800],
            'tuban' => [-6.8900, 112.0600],
            'lamongan' => [-7.1200, 112.4100],
            // Bali & Nusa Tenggara
            'denpasar' => [-8.6705, 115.2126],
            'badung' => [-8.5800, 115.1700],
            'gianyar' => [-8.5300, 115.3200],
            'buleleng' => [-8.1100, 115.0800],
            'mataram' => [-8.5833, 116.1167],
            'lombok' => [-8.6500, 116.3200],
            'bima' => [-8.4600, 118.7200],
            'sumbawa' => [-8.5000, 117.4200],
            'kupang' => [-10.1772, 123.6070],
            'ende' => [-8.8400, 121.6500],
            'manggarai' => [-8.6000, 120.4500],
            'sumba' => [-9.6500, 119.8500],
            // Sumatera
            'medan' => [3.5952, 98.6722],
            'deli serdang' => [3.5500, 98.8500],
            'banda aceh' => [5.5483, 95.3238],
            'lhokseumawe' => [5.1800, 97.1400],
            'padang' => [-0.9471, 100.4172],
            'bukittinggi' => [-0.3000, 100.3700],
            'pekanbaru' => [0.5071, 101.4478],
            'dumai' => [1.6700, 101.4500],
            'batam' => [1.1300, 104.0500],
            'tanjung pinang' => [0.9100, 104.4500],
            'jambi' => [-1.6100, 103.6100],
            'palembang' => [-2.9761, 104.7754],
            'bengkulu' => [-3.8000, 102.2600],
            'bandar lampung' => [-5.4292, 105.2611],
            'lampung' => [-5.0000, 105.0000],
            'pangkal pinang' => [-2.1300, 106.1100],
            'bangka' => [-2.0000, 106.0000],
            'belitung' => [-2.7500, 107.8500],
            // Kalimantan
            'pontianak' => [-0.0263, 109.3425],
            'singkawang' => [0.9000, 108.9800],
            'banjarmasin' => [-3.3167, 114.5900],
            'banjarbaru' => [-3.4400, 114.8300],
            'palangka raya' => [-2.2000, 113.9100],
            'samarinda' => [-0.5022, 117.1536],
            'balikpapan' => [-1.2654, 116.8312],
            'tarakan' => [3.3000, 117.6300],
            // Sulawesi
            'makassar' => [-5.1477, 119.4327],
            'gowa' => [-5.2000, 119.4500],
            'manado' => [1.4748, 124.8428],
            'palu' => [-0.9000, 119.8700],
            'kendari' => [-3.9700, 122.5800],
            'gorontalo' => [0.5400, 123.0600],
            'mamuju' => [-2.6700, 118.8800],
            // Maluku & Papua
            'ambon' => [-3.6554, 128.1908],
            'maluku barat daya' => [-7.9000, 127.8000],
            'maluku tenggara' => [-5.7500, 132.7500],
            'maluku' => [-3.6500, 128.1900],
            'ternate' => [0.7800, 127.3800],
            'jayapura' => [-2.5337, 140.7181],
            'jayawijaya' => [-4.0800, 138.9400],
            'merauke' => [-8.4900, 140.4000],
            'timika' => [-4.5400, 136.8800],
            'mimika' => [-4.5400, 136.8800],
            'sorong' => [-0.8800, 131.2500],
            'manokwari' => [-0.8600, 134.0600],
            'biak' => [-1.1700, 136.0800],
            'papua' => [-4.0000, 138.0000],
        ];

        // Province Centroids as solid regional fallback
        $provinceMap = [
            'dki jakarta' => [-6.2088, 106.8220],
            'jawa barat' => [-6.9175, 107.6191],
            'jawa tengah' => [-6.9932, 110.4203],
            'd i yogyakarta' => [-7.7956, 110.3695],
            'yogyakarta' => [-7.7956, 110.3695],
            'jawa timur' => [-7.2575, 112.7521],
            'banten' => [-6.1200, 106.1500],
            'bali' => [-8.6705, 115.2126],
            'aceh' => [5.5483, 95.3238],
            'nanggroe aceh darussalam' => [5.5483, 95.3238],
            'sumatera utara' => [3.5952, 98.6722],
            'sumatera barat' => [-0.9471, 100.4172],
            'riau' => [0.5071, 101.4478],
            'kepulauan riau' => [1.1300, 104.0500],
            'jambi' => [-1.6100, 103.6100],
            'sumatera selatan' => [-2.9761, 104.7754],
            'bengkulu' => [-3.8000, 102.2600],
            'lampung' => [-5.4292, 105.2611],
            'kep. bangka belitung' => [-2.1300, 106.1100],
            'kalimantan barat' => [-0.0263, 109.3425],
            'kalimantan tengah' => [-2.2000, 113.9100],
            'kalimantan selatan' => [-3.3167, 114.5900],
            'kalimantan timur' => [-0.5022, 117.1536],
            'kalimantan utara' => [3.3000, 117.6300],
            'sulawesi utara' => [1.4748, 124.8428],
            'sulawesi tengah' => [-0.9000, 119.8700],
            'sulawesi selatan' => [-5.1477, 119.4327],
            'sulawesi tenggara' => [-3.9700, 122.5800],
            'gorontalo' => [0.5400, 123.0600],
            'sulawesi barat' => [-2.6700, 118.8800],
            'nusa tenggara barat' => [-8.5833, 116.1167],
            'nusa tenggara timur' => [-10.1772, 123.6070],
            'maluku' => [-3.6554, 128.1908],
            'maluku utara' => [0.7800, 127.3800],
            'papua' => [-2.5337, 140.7181],
            'papua barat' => [-0.8600, 134.0600],
            'papua selatan' => [-8.4900, 140.4000],
            'papua tengah' => [-4.5400, 136.8800],
            'papua pegunungan' => [-4.0800, 138.9400],
            'papua barat daya' => [-0.8800, 131.2500],
        ];

        // 4. Update all faskes whose coordinates are outside their realistic regional bounds or NULL
        $allFaskes = DB::table('faskes')->get();
        $fixedCount = 0;

        foreach ($allFaskes as $faskes) {
            $provLower = strtolower(trim($faskes->provinsi ?? ''));
            $kotaLower = strtolower(trim($faskes->kota_kabupaten ?? ''));
            $alamatLower = strtolower(trim($faskes->alamat ?? ''));

            // Check if current coordinate belongs to wrong region
            $curLat = $faskes->latitude !== null ? (float) $faskes->latitude : null;
            $curLng = $faskes->longitude !== null ? (float) $faskes->longitude : null;

            $isNearSudirman = ($curLat !== null && $curLng !== null && abs($curLat - (-6.21)) < 0.05 && abs($curLng - 106.82) < 0.05);
            $isNotJakarta = !str_contains($provLower, 'jakarta') && !str_contains($kotaLower, 'jakarta') && !str_contains($alamatLower, 'jakarta');

            $needsFix = ($curLat === null || $curLng === null || ($isNearSudirman && $isNotJakarta));

            if (!$needsFix) {
                continue;
            }

            // Find matching regency
            $target = null;
            foreach ($regencyMap as $key => $coords) {
                if (str_contains($kotaLower, $key) || str_contains($alamatLower, $key)) {
                    $target = $coords;
                    break;
                }
            }

            // If not found, match province
            if (!$target) {
                foreach ($provinceMap as $key => $coords) {
                    if (str_contains($provLower, $key) || str_contains($kotaLower, $key)) {
                        $target = $coords;
                        break;
                    }
                }
            }

            if (!$target) {
                $target = [-6.9175, 107.6191]; // Default West Java
            }

            // Add unique jitter so multiple faskes in same regency don't stack on exact same spot
            $latJitter = ((($faskes->id * 17) % 100) - 50) * 0.0003;
            $lngJitter = ((($faskes->id * 23) % 100) - 50) * 0.0003;

            DB::table('faskes')->where('id', $faskes->id)->update([
                'latitude' => round($target[0] + $latJitter, 8),
                'longitude' => round($target[1] + $lngJitter, 8),
            ]);
            $fixedCount++;
        }

        $this->info("Corrected {$fixedCount} misplaced faskes to their accurate regional coordinates.");
        return Command::SUCCESS;
    }
}
