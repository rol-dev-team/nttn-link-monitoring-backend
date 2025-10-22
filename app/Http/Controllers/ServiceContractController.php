<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceContractController extends Controller
{
    public function getContracts()
    {

        //        return 'here';
        $results = DB::connection('mysql_second')->select("
            SELECT sc.name, sc.code, sc.party_id, sc.service_package_id,sp.product_id, sp.quantity, sp.connectivity_status,
                   sp.inactive, sp.service_package_id,p.name as product_name, p.item_id, p.code,pa.full_name, pa.code as party_code
            FROM service_contracts sc
            JOIN service_contract_products sp ON sc.id = sp.service_contract_id
            JOIN products p ON sp.product_id = p.id
            JOIN parties pa ON sc.party_id = pa.id
            WHERE p.item_id = 2
            AND sp.inactive = 0
            AND pa.full_name = 'MM Internet Technology'
            ORDER BY pa.full_name
        ");

        return response()->json($results);
    }



    public function combinedContractsRouterData(Request $request)
    {
        try {
            
            $routerIds = $request->input('router_ids', []);
            $vendorIds = $request->input('vendor_ids', []);
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $categoryStatus = $request->input('category_status', 'all');

            
            if (is_string($routerIds)) {
                $routerIds = explode(',', $routerIds);
            }
            $routerIds = array_filter($routerIds);

            
            if (is_string($vendorIds)) {
                $vendorIds = explode(',', $vendorIds);
            }
            $vendorIds = array_filter($vendorIds);

            
            $contracts = collect(DB::connection('mysql_second')->select("
            SELECT sc.name as client_name, sc.code, sc.party_id, sc.service_package_id,
                   sp.product_id, sp.quantity, sp.connectivity_status,
                   sp.inactive, sp.service_package_id, p.name as product_name,
                   p.item_id, p.code as product_code, pa.full_name, pa.code as party_code
            FROM service_contracts sc
            JOIN service_contract_products sp ON sc.id = sp.service_contract_id
            JOIN products p ON sp.product_id = p.id
            JOIN parties pa ON sc.party_id = pa.id
            WHERE p.item_id = 2
              AND sp.inactive = 0
            ORDER BY pa.full_name
        "));

            
            // $tableName = (
            //     !empty($routerIds) ||
            //     !empty($vendorIds) ||
            //     !empty($startDate) ||
            //     !empty($endDate)
            // ) ? 'router_bw_statistics_logs' : 'router_bw_statistics';

            $tableName = (
                !empty($startDate) || !empty($endDate)
            ) ? 'router_bw_statistics_logs' : 'router_bw_statistics';


            
            $routerQuery = "
                SELECT DISTINCT ON (r.category)
                    r.router_id, v.vendor_name, v.id as vendor_id, r.host_name, r.interface, r.category, r.category_type,
                    r.int_description, r.assigned_capacity, r.policer, r.utilization_mb, r.collected_at
                FROM {$tableName} r
                JOIN vendors v ON r.vendor_id = v.id
                WHERE 1=1
            ";
            $routerParams = [];

            
            if (!empty($routerIds)) {
                $placeholders = implode(',', array_fill(0, count($routerIds), '?'));
                $routerQuery .= " AND r.router_id IN ($placeholders)";
                $routerParams = array_merge($routerParams, $routerIds);
            }

            
            if (!empty($vendorIds)) {
                $placeholders = implode(',', array_fill(0, count($vendorIds), '?'));
                $routerQuery .= " AND v.id IN ($placeholders)";
                $routerParams = array_merge($routerParams, $vendorIds);
            }

            
            if (!empty($startDate)) {
                $routerQuery .= " AND DATE(r.collected_at) >= ?";
                $routerParams[] = $startDate;
            }

            if (!empty($endDate)) {
                $routerQuery .= " AND DATE(r.collected_at) <= ?";
                $routerParams[] = $endDate;
            }

            
            $routerQuery .= " ORDER BY r.category, r.utilization_mb DESC";

            $routers = collect(DB::connection('pgsql')->select($routerQuery, $routerParams));

           
            $contractCategories = $contracts->pluck('client_name');
            $routerCategories = $routers->pluck('category');

            $allCategories = $contractCategories
                ->merge($routerCategories)
                ->unique()
                ->values();

            $commonCategories = $contractCategories->intersect($routerCategories)->values();

            
            switch ($categoryStatus) {
                case 'missing_in_erp':
                    
                    $filteredCategories = $routerCategories->diff($contractCategories)->values();
                    break;

                case 'missing_in_routers':
                    
                    $filteredCategories = $contractCategories->diff($routerCategories)->values();
                    break;

                case 'matched':
                    
                    $filteredCategories = $commonCategories;
                    break;

                case 'all':
                default:
                    
                    $filteredCategories = $allCategories;
                    break;
            }

            
            $merged = $filteredCategories->map(function ($key) use ($contracts, $routers) {
                $contract = $contracts->firstWhere('client_name', $key);
                $router = $routers->firstWhere('category', $key);

                $contractQuantity = $contract->quantity ?? 0;
                $assignedCapacity = $router->assigned_capacity ?? 0;
                $quantityDifference = floatval($assignedCapacity) - floatval($contractQuantity);

                $utilizationMb = $router->utilization_mb ?? 0;
                $overUtilization = floatval($utilizationMb) - floatval($assignedCapacity);

                return [
                    
                    'client_name' => $contract->client_name ?? null,
                    'code' => $contract->code ?? null,
                    'party_id' => $contract->party_id ?? null,
                    'service_package_id' => $contract->service_package_id ?? null,
                    'product_id' => $contract->product_id ?? null,
                    'quantity' => $contract->quantity ?? null,
                    'connectivity_status' => $contract->connectivity_status ?? null,
                    'inactive' => $contract->inactive ?? null,
                    'product_name' => $contract->product_name ?? null,
                    'item_id' => $contract->item_id ?? null,
                    'product_code' => $contract->product_code ?? null,
                    'full_name' => $contract->full_name ?? null,
                    'party_code' => $contract->party_code ?? null,

                    
                    'vendor_name' => $router->vendor_name ?? null,
                    'vendor_id' => $router->vendor_id ?? null,
                    'host_name' => $router->host_name ?? null,
                    'interface' => $router->interface ?? null,
                    'category' => $router->category ?? null,
                    'category_type' => $router->category_type ?? null,
                    'int_description' => $router->int_description ?? null,
                    'assigned_capacity' => $router->assigned_capacity ?? null,
                    'policer' => $router->policer ?? null,
                    'utilization_mb' => $router->utilization_mb ?? null,
                    'collected_at' => $router->collected_at ?? null,

                   
                    'quantity_difference' => $quantityDifference,
                    'over_utilization' => $overUtilization,
                ];
            });

            return response()->json([
                'status' => 'success',
                'count' => $merged->count(),
                'data' => $merged,
                'filters' => [
                    'router_ids' => $routerIds,
                    'vendor_ids' => $vendorIds,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'category_status' => $categoryStatus,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }



    public function combinedSummary()
    {
        try {
            $routerUtilization = DB::connection('pgsql')->select("
                SELECT AVG(r.utilization_mb) AS average_utilization_mb
                FROM router_bw_statistics r
                WHERE r.category_type = 'primary'
            ");

            // $routerCapacity = DB::connection('pgsql')->select("
            //     SELECT 
            //         SUM(r.assigned_capacity) AS total_assigned_capacity
            //     FROM router_bw_statistics r
            //     WHERE r.category_type = 'primary'
            //     AND (r.router_id, r.collected_at) IN (
            //         SELECT 
            //             cor.router_id,
            //             MAX(cor.collected_at)
            //         FROM router_bw_statistics cor
            //         WHERE cor.category_type = 'primary'
            //         GROUP BY cor.router_id
            //     )
            // ");

            $routerCapacity = DB::connection('pgsql')->select("
                SELECT 
                    SUM(r.assigned_capacity) AS total_assigned_capacity
                FROM (
                    SELECT DISTINCT category, assigned_capacity
                    FROM router_bw_statistics
                    WHERE assigned_capacity > 0
                    and category_type = 'primary'
                ) AS r
            ");

            $routerUtilizationData = $routerUtilization[0] ?? (object)['average_utilization_mb' => 0];
            $routerCapacityData = $routerCapacity[0] ?? (object)['total_assigned_capacity' => 0];

            $contractSummary = DB::connection('mysql_second')->select("
                SELECT
                    SUM(sp.quantity) AS total_quantity
                FROM service_contracts sc
                JOIN service_contract_products sp ON sc.id = sp.service_contract_id
                JOIN products p ON sp.product_id = p.id
                JOIN parties pa ON sc.party_id = pa.id
                WHERE p.item_id = 2
                AND sp.inactive = 0
            ");

            $contractData = $contractSummary[0] ?? (object)['total_quantity' => 0];

            
            $routerCapacityGB = round(($routerCapacityData->total_assigned_capacity ?? 0) / 1024, 2);
            $prismCapacityGB = round(($contractData->total_quantity ?? 0) / 1024, 2);
            $avgUtilizationMb = round(($routerUtilizationData->average_utilization_mb ?? 0), 2);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_assigned_capacity_router' => $routerCapacityGB . ' GB',
                    'average_utilization_mb' => $avgUtilizationMb . ' Mb/s',
                    'total_assigned_capacity_prism' => $prismCapacityGB . ' GB',
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }




    public function getOfflineOnlineRouters()
    {
        try {
            $q1 = collect(DB::connection('pgsql')->select("
            SELECT DISTINCT v.vendor_name, r.ip_address, r.router_name, r.location, r.status, r.id
            FROM routers r
            JOIN vendors v ON r.vendor_id = v.id
            WHERE r.status = 'active'
        "));

            $q2 = collect(DB::connection('pgsql')->select("
            SELECT DISTINCT v.vendor_name, r.ip_address, r.router_name, r.location, r.status, r.id
            FROM routers r
            JOIN vendors v ON r.vendor_id = v.id
            JOIN router_bw_statistics rs ON r.id = rs.router_id
            WHERE r.status = 'active'
            
        "));


            $difference = $q1->reject(function ($item) use ($q2) {
                return $q2->contains(function ($q2item) use ($item) {
                    return $q2item->ip_address === $item->ip_address;
                });
            })->values();


            $offlineIds = $difference->pluck('id')->values();


            $activeRouters = collect(DB::connection('pgsql')->select("
                SELECT DISTINCT v.vendor_name, r.ip_address, r.router_name,r.location, r.status, r.id
                FROM routers r
                JOIN vendors v ON r.vendor_id = v.id
                WHERE r.status = 'active'
            "));

            $onlineIds = $activeRouters->pluck('id')->values();

            return response()->json([
                'status' => 'success',
                'active_count' => $activeRouters->count(),
                'active_data' => $activeRouters,
                'active_ids' => $onlineIds,
                'offline_count' => $difference->count(),
                'offline_data' => $difference,
                'offline_ids' => $offlineIds,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function routersOverUtilized()
    {
        try {
            $contracts = collect(DB::connection('mysql_second')->select("
            SELECT sc.name as client_name, sc.code, sc.party_id, sc.service_package_id,
                   sp.product_id, sp.quantity, sp.connectivity_status,
                   sp.inactive, sp.service_package_id, p.name as product_name,
                   p.item_id, p.code as product_code, pa.full_name, pa.code as party_code
            FROM service_contracts sc
            JOIN service_contract_products sp ON sc.id = sp.service_contract_id
            JOIN products p ON sp.product_id = p.id
            JOIN parties pa ON sc.party_id = pa.id
            WHERE p.item_id = 2
              AND sp.inactive = 0
            ORDER BY pa.full_name
        "));

            $routers = collect(DB::connection('pgsql')->select("
            SELECT DISTINCT ON (r.category)
                   r.router_id, v.vendor_name, v.id as vendor_id, r.host_name, r.interface, r.category, r.category_type,
                   r.int_description, r.assigned_capacity, r.policer, r.utilization_mb, r.collected_at
            FROM router_bw_statistics r
            JOIN vendors v ON r.vendor_id = v.id
            ORDER BY r.category, r.utilization_mb DESC
        "));

            $merged = collect();

            foreach ($routers->pluck('category')->unique() as $category) {
                $contract = $contracts->firstWhere('client_name', $category);
                $router = $routers->firstWhere('category', $category);

                $contractQuantity = $contract->quantity ?? 0;
                $assignedCapacity = $router->assigned_capacity ?? 0;
                $utilizationMb = $router->utilization_mb ?? 0;

                $overUtilization = floatval($utilizationMb) - (floatval($assignedCapacity) + 10);

                // $overUtilization = floatval($utilizationMb) - (floatval($assignedCapacity) + ($assignedCapacity > 0 ? 10 : 0));


                if ($overUtilization > 0) {
                    $merged->push([
                    'vendor_name' => $router->vendor_name ?? null,
                    'host_name' => $router->host_name ?? null,
                    'client_name' => $contract->client_name ?? null,
                    'full_name' => $contract->full_name ?? null,
                    'product_name' => $contract->product_name ?? null,
                    'category' => $router->category ?? null,
                    'assigned_capacity' => $assignedCapacity,
                    'quantity' => $contract->quantity ?? null,
                    'utilization_mb' => $utilizationMb,
                    'over_utilization' => $overUtilization,
                    'utilization_percent' => $assignedCapacity == 0 
                        ? $utilizationMb 
                        : round(($utilizationMb / $assignedCapacity) * 100, 2),
                    'collected_at' => $router->collected_at ?? null,
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'count' => $merged->count(),
                'data' => $merged,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function routersUnderUtilized()
    {
        try {
            $contracts = collect(DB::connection('mysql_second')->select("
            SELECT sc.name as client_name, sc.code, sc.party_id, sc.service_package_id,
                   sp.product_id, sp.quantity, sp.connectivity_status,
                   sp.inactive, sp.service_package_id, p.name as product_name,
                   p.item_id, p.code as product_code, pa.full_name, pa.code as party_code
            FROM service_contracts sc
            JOIN service_contract_products sp ON sc.id = sp.service_contract_id
            JOIN products p ON sp.product_id = p.id
            JOIN parties pa ON sc.party_id = pa.id
            WHERE p.item_id = 2
              AND sp.inactive = 0
            ORDER BY pa.full_name
        "));

            $routers = collect(DB::connection('pgsql')->select("
            SELECT DISTINCT ON (r.category)
                   r.router_id, v.vendor_name, v.id as vendor_id, r.host_name, r.interface, r.category, r.category_type,
                   r.int_description, r.assigned_capacity, r.policer, r.utilization_mb, r.collected_at
            FROM router_bw_statistics r
            JOIN vendors v ON r.vendor_id = v.id
            ORDER BY r.category, r.utilization_mb DESC
        "));

            $merged = collect();

            foreach ($routers->pluck('category')->unique() as $category) {
                $contract = $contracts->firstWhere('client_name', $category);
                $router = $routers->firstWhere('category', $category);

                $contractQuantity = $contract->quantity ?? 0;
                $assignedCapacity = $router->assigned_capacity ?? 0;
                $utilizationMb = $router->utilization_mb ?? 0;

                $overUtilization = floatval($utilizationMb) - floatval($assignedCapacity);

                if ($overUtilization < 0) {
                    $merged->push([
                        'vendor_name' => $router->vendor_name ?? null,
                        'host_name' => $router->host_name ?? null,
                        'client_name' => $contract->client_name ?? null,
                        'full_name' => $contract->full_name ?? null,
                        'product_name' => $contract->product_name ?? null,
                        'category' => $router->category ?? null,
                        'assigned_capacity' => $assignedCapacity,
                        'quantity' => $contract->quantity ?? null,
                        'utilization_mb' => $utilizationMb,
                        'over_utilization' => $overUtilization,
                        'utilization_percent' => $assignedCapacity == 0 
                            ? $utilizationMb 
                            : round(($utilizationMb / $assignedCapacity) * 100, 2),
                        'collected_at' => $router->collected_at ?? null,
                        ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'count' => $merged->count(),
                'data' => $merged,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function routersUtilizationCount()
    {
        try {
            $contracts = collect(DB::connection('mysql_second')->select("
            SELECT sc.name as client_name, sc.code, sc.party_id, sc.service_package_id,
                   sp.product_id, sp.quantity
            FROM service_contracts sc
            JOIN service_contract_products sp ON sc.id = sp.service_contract_id
            JOIN products p ON sp.product_id = p.id
            WHERE p.item_id = 2
              AND sp.inactive = 0
        "));

            $routers = collect(DB::connection('pgsql')->select("
            SELECT DISTINCT ON (r.category)
                   r.router_id, r.category, r.assigned_capacity, r.utilization_mb
            FROM router_bw_statistics r
            ORDER BY r.category, r.utilization_mb DESC
        "));

            $overCount = 0;
            $underCount = 0;

            foreach ($routers->pluck('category')->unique() as $category) {
                $contract = $contracts->firstWhere('client_name', $category);
                $router = $routers->firstWhere('category', $category);

                $assignedCapacity = $router->assigned_capacity ?? 0;
                $utilizationMb = $router->utilization_mb ?? 0;

                $overUtilization = floatval($utilizationMb) - floatval($assignedCapacity);

                if ($overUtilization > 0) $overCount++;
                if ($overUtilization < 0) $underCount++;
            }

            return response()->json([
                'status' => 'success',
                'over_utilization_count' => $overCount,
                'under_utilization_count' => $underCount,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function routersMissMatchCapacity()
    {
        try {
            $contracts = collect(DB::connection('mysql_second')->select("
                SELECT sc.name as client_name, sc.code, sc.party_id, sc.service_package_id,
                    sp.product_id, sp.quantity, sp.connectivity_status,
                    sp.inactive, sp.service_package_id, p.name as product_name,
                    p.item_id, p.code as product_code, pa.full_name, pa.code as party_code
                FROM service_contracts sc
                JOIN service_contract_products sp ON sc.id = sp.service_contract_id
                JOIN products p ON sp.product_id = p.id
                JOIN parties pa ON sc.party_id = pa.id
                WHERE p.item_id = 2
                AND sp.inactive = 0
                ORDER BY pa.full_name
            "));

            $routers = collect(DB::connection('pgsql')->select("
                SELECT DISTINCT ON (r.category)
                    r.router_id, v.vendor_name, v.id as vendor_id, r.host_name, r.interface, r.category, r.category_type,
                    r.int_description, r.assigned_capacity, r.policer, r.utilization_mb, r.collected_at
                FROM router_bw_statistics r
                JOIN vendors v ON r.vendor_id = v.id
                ORDER BY r.category, r.utilization_mb DESC
            "));

            $merged = collect();

            foreach ($routers->pluck('category')->unique() as $category) {
                $contract = $contracts->firstWhere('client_name', $category);
                $router = $routers->firstWhere('category', $category);

                $contractQuantity = floatval($contract->quantity ?? 0);
                $assignedCapacity = floatval($router->assigned_capacity ?? 0);
                $utilizationMb = floatval($router->utilization_mb ?? 0);

                // New condition — check capacity vs contract quantity
                $quantityDifference = $assignedCapacity - $contractQuantity;

                if ($quantityDifference > 0) {
                    $merged->push([
                        'vendor_name' => $router->vendor_name ?? null,
                        'host_name' => $router->host_name ?? null,
                        'client_name' => $contract->client_name ?? null,
                        'full_name' => $contract->full_name ?? null,
                        'product_name' => $contract->product_name ?? null,
                        'category' => $router->category ?? null,
                        'assigned_capacity' => $assignedCapacity,
                        'quantity' => $contractQuantity,
                        'quantity_difference' => $quantityDifference,
                        'utilization_mb' => $utilizationMb,
                        'utilization_percent' => $assignedCapacity == 0 
                            ? $utilizationMb 
                            : round(($utilizationMb / $assignedCapacity) * 100, 2),
                        'collected_at' => $router->collected_at ?? null,
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'count' => $merged->count(),
                'data' => $merged,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function topFiveUtilization()
    {
        try {

            $top = DB::connection('pgsql')->select("
                SELECT router_id, vendor_name, vendor_id, host_name, interface,
                    category, category_type, int_description,
                    assigned_capacity, policer, utilization_mb, collected_at
                FROM (
                    SELECT r.router_id,v.vendor_name,v.id  AS vendor_id,r.host_name,r.interface,
                        r.category,r.category_type,r.int_description,r.assigned_capacity,r.policer,
                        r.utilization_mb,r.collected_at,
                        ROW_NUMBER() OVER (PARTITION BY r.router_id, r.interface
                        ORDER BY r.collected_at DESC) AS rn
                    FROM router_bw_statistics r
                    JOIN vendors v ON v.id = r.vendor_id
                ) AS latest
                WHERE latest.rn = 1
                ORDER BY latest.utilization_mb DESC
                LIMIT 5
            ");

            return response()->json([
                'status' => 'success',
                'count'  => count($top),
                'data'   => $top
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
