<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Router;
use App\Models\Vendor;

class RouterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Router::on('pgsql')->truncate();

        $vendors = Vendor::all();

        // Allowed statuses from the migration ENUM
        // We will use 'inactive' to represent both 'offline' and 'maintenance' scenarios
        $statuses = ['active', 'active', 'active', 'inactive', 'inactive', 'active'];

        $locations = [
            'Data Center A', 'Data Center B', 'Head Office', 'Branch Office 1 (Inactive)', 'Branch Office 2', 'Warehouse'
        ];

        $routersToInsert = [];
        $ipBase = 10;
        $subnetCounter = 10;
        $hostCounter = 1;

        foreach ($vendors as $vendor) {
            $numRouters = rand(3, 5);
            $vendorPrefix = str_replace([' ', '(', ')'], ['-', '', ''], $vendor->vendor_name);

            for ($i = 0; $i < $numRouters; $i++) {
                $hostName = $vendorPrefix . '-R' . $hostCounter++;
                $location = $locations[array_rand($locations)];
                $status = $statuses[array_rand($statuses)];

                // IP Address generation logic
                if ($hostCounter % 250 === 0) {
                    $subnetCounter++;
                    $hostCounter = 1;
                }
                $ipAddress = "{$ipBase}.10.{$subnetCounter}.{$hostCounter}";

                $routersToInsert[] = [
                    'vendor_id' => $vendor->id,
                    'host_name' => $hostName,
                    'ip_address' => $ipAddress,
                    'location' => $location,
                    'status' => $status,
                ];
            }
            $subnetCounter++;
        }

        Router::on('pgsql')->insert($routersToInsert);
    }
}
