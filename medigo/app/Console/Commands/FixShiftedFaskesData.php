<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixShiftedFaskesData extends Command
{
    protected $signature = 'faskes:fix-shifted';
    protected $description = 'Fix CSV delimiter shifted rows where name contains commas, pushing columns into tipe, provinsi, etc.';

    public function handle()
    {
        $this->info('Starting scan and fix for shifted CSV faskes data...');

        $shiftedRows = DB::table('faskes')
            ->whereIn('link_maps', ['TRUE', 'FALSE'])
            ->orWhere('provinsi', 'like', '%Rumah Sakit%')
            ->orWhere('provinsi', 'like', '%Klinik%')
            ->orWhere('provinsi', 'like', '%Puskesmas%')
            ->orWhere('provinsi', 'like', '%Dokter%')
            ->get();

        $fixedCount = 0;

        foreach ($shiftedRows as $row) {
            // Check if provinsi contains a faskes type (e.g., 'Rumah Sakit Umum', 'Klinik Utama', 'Klinik Pratama', 'Puskesmas', 'Dokter Gigi')
            $faskesTypes = ['Rumah Sakit Umum', 'Rumah Sakit Khusus', 'Klinik Utama', 'Klinik Pratama', 'Puskesmas', 'Dokter Gigi', 'Apotek'];
            $isProvinsiType = false;
            foreach ($faskesTypes as $ft) {
                if (stripos($row->provinsi, $ft) !== false) {
                    $isProvinsiType = true;
                    break;
                }
            }

            if ($isProvinsiType || in_array($row->link_maps, ['TRUE', 'FALSE'])) {
                // Construct real full name
                $realNama = trim($row->nama_faskes . ',' . $row->tipe);
                $realTipe = trim($row->provinsi);
                $realProvinsi = trim($row->kota_kabupaten);
                $realKota = trim($row->alamat);
                $realAlamat = $realKota ? ($realKota . ', ' . $realProvinsi) : $realProvinsi;
                $isBpjs = ($row->link_maps === 'TRUE') ? 1 : (($row->link_maps === 'FALSE') ? 0 : $row->is_support_bpjs);
                $realLinkMaps = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($realNama . ' ' . $realKota);

                DB::table('faskes')->where('id', $row->id)->update([
                    'nama_faskes' => $realNama,
                    'tipe' => $realTipe,
                    'provinsi' => $realProvinsi,
                    'kota_kabupaten' => $realKota,
                    'alamat' => $realAlamat,
                    'is_support_bpjs' => $isBpjs,
                    'link_maps' => $realLinkMaps,
                ]);

                $fixedCount++;
            }
        }

        $this->info("Successfully fixed {$fixedCount} shifted faskes rows!");

        return Command::SUCCESS;
    }
}
