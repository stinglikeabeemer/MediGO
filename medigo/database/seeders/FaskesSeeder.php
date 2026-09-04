<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class FaskesSeeder extends Seeder
{
    public function run(): void
    {
        $csvFile = fopen(database_path('seeders/Dataset_Faskes_Final_with_Maps.csv'), 'r');
        
        $firstLine = true;


        while (($data = fgetcsv($csvFile, 2000, ";")) !== FALSE) {
            if (!$firstLine) {
                if (count($data) < 6) {
                    continue;
                }
                DB::table('faskes')->insert([
                    'nama_faskes'     => $data[0], // Kolom A di CSV
                    'tipe'            => $data[1], // Kolom B
                    'provinsi'        => $data[2], // Kolom C
                    'kota_kabupaten'  => $data[3], // Kolom D
                    'alamat'          => $data[4], // Kolom E
                    'is_support_bpjs' => filter_var($data[5], FILTER_VALIDATE_BOOLEAN), // Kolom F (True/False)
                    'link_maps'       => $data[6] ?? null, // Kolom G (Kalau kosong dibikin null)
                    'created_at'      => Carbon::now(),
                    'updated_at'      => Carbon::now(),
                ]);
            }
            $firstLine = false;
        }

        fclose($csvFile);
    }
}