<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\RouterBwStatisticLog;
use App\Models\RouterBwStatistic;

class RouterBWStatisticLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        RouterBwStatisticLog::on('pgsql')->truncate();
        $stats = RouterBwStatistic::all();
        $logsToInsert = [];
        $logPoints = 30;

        foreach ($stats as $stat) {
            $isHighTrafficWAN = $stat->category === 'WAN' && $stat->utilization_mb > 500;
            $router = $stat->router; // Get router to check status

            for ($i = 0; $i < $logPoints; $i++) {
                $collectedAt = now()->subMinutes(15 * ($logPoints - $i));
                $currentBaseUtil = $stat->utilization_mb;

                // Only fluctuate active routers
                if ($router->status === 'active') {
                    // Normal fluctuation: +/- 15%
                    $fluctuation = $currentBaseUtil * (rand(-15, 15) / 100);
                    $currentUtil = $currentBaseUtil + $fluctuation;

                    // Simulate a traffic spike for high-traffic WAN links
                    if ($isHighTrafficWAN && $i > ($logPoints * 0.7) && rand(0, 1)) {
                        $spike = $stat->policer * 0.2 * (rand(50, 100) / 100);
                        $currentUtil += $spike;
                    }

                    // Ensure utilization is non-negative and capped by policer, with 2 decimal places
                    $utilizationLog = round(max(0, min($currentUtil, $stat->policer)), 2);

                } else {
                    // Inactive routers have zero or near-zero utilization logs
                    $utilizationLog = (rand(0, 20) === 0) ? round(rand(0, 10) / 100, 2) : 0.00;
                }

                $logsToInsert[] = [
                    'vendor_id' => $stat->vendor_id,
                    'router_id' => $stat->router_id,
                    'host_name' => $stat->host_name,
                    'interface' => $stat->interface,
                    'category' => (string) $stat->category,
                    'category_type' => (string) $stat->category_type,
                    'interface_description' => $stat->interface_description,
                    'assigned_capacity' => $stat->assigned_capacity,
                    'policer' => $stat->policer,
                    'utilization_mb' => $utilizationLog, // DECIMAL(12, 2)
                    'collected_at' => $collectedAt,
                ];
            }
        }

        RouterBwStatisticLog::on('pgsql')->insert($logsToInsert);
    }
}
