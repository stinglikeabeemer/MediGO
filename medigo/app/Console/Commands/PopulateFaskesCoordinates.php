<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateFaskesCoordinates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faskes:extract-coords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract and populate latitude and longitude from link_maps column for all faskes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting extraction of GPS coordinates from link_maps...');

        $totalUpdated = 0;
        $batchSize = 500;

        DB::table('faskes')
            ->whereNotNull('link_maps')
            ->where('link_maps', '!=', '')
            ->where('link_maps', '!=', '-')
            ->orderBy('id')
            ->chunk($batchSize, function ($faskesList) use (&$totalUpdated) {
                foreach ($faskesList as $faskes) {
                    $lat = null;
                    $lng = null;

                    // Match ?q=lat,lng or ?destination=lat,lng
                    if (preg_match('/[?&](?:q|destination)=([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $faskes->link_maps, $matches)) {
                        $lat = (float) $matches[1];
                        $lng = (float) $matches[2];
                    }
                    // Match /@lat,lng
                    elseif (preg_match('/@([-+]?\d{1,3}(?:\.\d+)?),([-+]?\d{1,3}(?:\.\d+)?)/i', $faskes->link_maps, $matches)) {
                        $lat = (float) $matches[1];
                        $lng = (float) $matches[2];
                    }

                    // Validate coordinates ranges (Indonesia roughly lat: -11 to 6, lng: 95 to 141)
                    if ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                        DB::table('faskes')
                            ->where('id', $faskes->id)
                            ->update([
                                'latitude' => $lat,
                                'longitude' => $lng,
                            ]);
                        $totalUpdated++;
                    }
                }
                $this->output->write('.');
            });

        $this->newLine();
        $this->info("Successfully updated coordinates for {$totalUpdated} faskes!");

        return Command::SUCCESS;
    }
}
