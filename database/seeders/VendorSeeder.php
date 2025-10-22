<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vendor;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use insert for efficiency with multiple records
        Vendor::on('pgsql')->truncate();
        Vendor::on('pgsql')->insert([
            ['vendor_name' => 'Cisco', 'status' => 'active'],
            ['vendor_name' => 'Juniper', 'status' => 'active'],
            ['vendor_name' => 'Arista', 'status' => 'active'],
            ['vendor_name' => 'HP Enterprise (HPE)', 'status' => 'active'],
            ['vendor_name' => 'Huawei', 'status' => 'active'],
            ['vendor_name' => 'Nokia', 'status' => 'active'],
            ['vendor_name' => 'Extreme Networks', 'status' => 'active'],
            ['vendor_name' => 'MikroTik', 'status' => 'active'],
            ['vendor_name' => 'Fortinet', 'status' => 'active'],
            ['vendor_name' => 'Palo Alto Networks', 'status' => 'active'],
        ]);
    }
}
