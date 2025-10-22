<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\RouterBwStatistic;
use App\Models\Router;

class RouterBWStatisticSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        RouterBwStatistic::on('pgsql')->truncate();
        $routers = Router::all();
        $statisticsToInsert = [];
        $now = now();

        $interfaceTypes = [
            'WAN' => ['GigabitEthernet0/1', 'ge-0/0/0', 'TenGigE1/1', 'Et1'],
            'LAN' => ['FastEthernet0/2', 'fe-0/1/0', 'GigabitEthernet0/3', 'Eth2'],
            'Mgmt' => ['mgmt0', 'fxp0', 'Vlan1'],
        ];

        // Define the categories we can randomly select from
        $categories = array_keys($interfaceTypes);

        foreach ($routers as $router) {

            // Base utilization ranges based on status
            if ($router->status === 'inactive') {
                $utilRangeMin = 0;
                $utilRangeMax = 5;
            } else {
                $utilRangeMin = 10;
                $utilRangeMax = 80;
            }

            // Generate multiple interfaces per router (2 to 4)
            $numInterfaces = rand(2, 4);

            for ($i = 0; $i < $numInterfaces; $i++) {
                // Select category: Ensure at least one WAN for the first iteration, then randomize
                $category = ($i === 0) ? 'WAN' : $categories[array_rand($categories)];

                $interface = $interfaceTypes[$category][array_rand($interfaceTypes[$category])];
                $categoryType = ($category === 'WAN') ? 'primary' : 'secondary';

                // WAN/Primary capacity is highly variable. LAN/Mgmt is often 1000Mbps
                $assignedCapacity = ($category === 'WAN') ? rand(500, 5000) : 1000;

                // Policer calculation (90% of capacity, cast to INT)
                $policer = (int) round($assignedCapacity * 0.9);

                // Utilization calculation (DECIMAL)
                $utilPercent = rand($utilRangeMin, $utilRangeMax);

                // If inactive, force utilization to 0 or near 0
                if ($router->status === 'inactive') {
                     $utilization = (rand(0, 10) === 0) ? 0.00 : round(rand(0, 5) / 100 * $policer, 2);
                } else {
                    // Utilization is a percentage of the policer or assigned capacity
                    $utilization = round(rand(10, 80) / 100 * $policer, 2);
                }

                $statisticsToInsert[] = [
                    'vendor_id' => $router->vendor_id,
                    'router_id' => $router->id,
                    'host_name' => $router->host_name,
                    'interface' => $interface,
                    'category' => (string) $category,
                    'category_type' => (string) $categoryType,
                    'interface_description' => $category . ' Link - ' . $router->location,
                    'assigned_capacity' => $assignedCapacity,
                    'policer' => $policer,
                    'utilization_mb' => $utilization, // DECIMAL(12, 2)
                    'collected_at' => $now,
                ];
            }
        }

        RouterBwStatistic::on('pgsql')->insert($statisticsToInsert);
    }
}
