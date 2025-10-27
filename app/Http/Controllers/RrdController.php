<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use DateTime;
use DateTimeZone;
use App\Models\NasCpuUsage;
use App\Models\NasRamUsage;
use App\Models\NasDiskUsage;
use App\Models\NasIcmpLatency;
use App\Models\NasIcmpTimeout;
use Carbon\Carbon;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class RrdController extends Controller
{

    const RRDTOOL_EXECUTABLE = '/usr/bin/rrdtool';

    const OCTETS_TO_MBPS = 8 / 1000000;

    protected function parseRrdFetchOutput(string $output): array
    {
        if (empty($output)) {
            return [];
        }
        $lines = explode("\n", trim($output));
        $header = array_shift($lines);
        $dataSources = preg_split('/\s+/', trim($header));
        $data = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = preg_split('/\s+/', $line, 2);
            $unixTimestamp = rtrim($parts[0], ':');
            $formattedDateTime = date('Y-m-d H:i:s', $unixTimestamp);
            $values = preg_split('/\s+/', trim($parts[1]));

            $row = [
                'timestamp_unix' => (int)$unixTimestamp,
                'timestamp_formatted' => $formattedDateTime
            ];

            foreach ($dataSources as $index => $dsName) {
                // Convert NaN/null values from RRD output to PHP null
                $row[$dsName] = is_numeric($values[$index]) ? (float)$values[$index] : null;
            }
            $data[] = $row;
        }
        return $data;
    }


    // public function getPortData(string $startDateString = null, string $endDateString = null): JsonResponse
    // {
    //     $rrdFileBaseDir = '/var/www/html/nttn-monitoring-application/partner-backend/storage/rrd/rrd/';
    //     $resolution = '-r 300';
    //     $timezone = 'Asia/Dhaka'; // Bangladesh timezone

    //     // Fetch NAS IPs
    //     $nasIps = DB::connection('pgsql')
    //         ->table('partner_activation_plans')
    //         ->pluck('nas_ip', 'id')
    //         ->filter()
    //         ->unique();

    //     if ($nasIps->isEmpty()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'No NAS IPs found in the database.'
    //         ], 404);
    //     }

    //     $hostsData = [];

    //     foreach ($nasIps as $activationPlanId => $host_ip) {
    //         $host_ip = trim($host_ip);
    //         if (empty($host_ip)) {
    //             $hostsData[$activationPlanId] = [
    //                 'status' => 'error',
    //                 'message' => 'Empty NAS IP found for activation plan ID ' . $activationPlanId
    //             ];
    //             continue;
    //         }

    //         // --- Determine Start/End Datetime Based on Last Record ---
    //         $latestCreatedAt = DB::connection('pgsql')
    //             ->table('nas_interface_utilizations as c')
    //             ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
    //             ->where('p.nas_ip', $host_ip)
    //             ->max('c.created_at');

    //         if ($latestCreatedAt) {
    //             $startDate = Carbon::parse($latestCreatedAt, $timezone)->addSecond();
    //         } else {
    //             $startDate = Carbon::now($timezone)->subMinutes(30);
    //         }
    //         $endDate = Carbon::now($timezone);

    //         // Format for display
    //         $startDateString = $startDate->format('Y-m-d H:i:s');
    //         $endDateString = $endDate->format('Y-m-d H:i:s');

    //         if ($startDate->gte($endDate)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'info',
    //                 'message' => "No new port data to process for host $host_ip. Last collected at: $latestCreatedAt",
    //                 'last_collected_at' => $latestCreatedAt,
    //             ];
    //             continue;
    //         }

    //         // Convert to UNIX timestamps for RRDTool (RRD expects UTC)
    //         $startUnix = $startDate->timestamp;
    //         $endUnix = $endDate->timestamp;
    //         $startTime = '-s ' . $startUnix;
    //         $endTime = '-e ' . $endUnix;

    //         // --- Process RRD files ---
    //         $portRrdDirectory = rtrim($rrdFileBaseDir, '/') . '/' . $host_ip;

    //         if (!is_dir($portRrdDirectory)) {
    //             Log::warning("RRD directory not found: '$portRrdDirectory'");
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "RRD directory for host $host_ip not found. Checked: '$portRrdDirectory'"
    //             ];
    //             continue;
    //         }

    //         $portFiles = glob($portRrdDirectory . '/port-id*.rrd');
    //         if (empty($portFiles)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "No port RRD files found for host $host_ip."
    //             ];
    //             continue;
    //         }

    //         $portsData = [];

    //         foreach ($portFiles as $rrdFilePath) {
    //             $filename = basename($rrdFilePath);
    //             $port_id = (int)preg_replace('/[^0-9]/', '', $filename);

    //             $commandAvg = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime";
    //             $commandMax = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MAX $resolution $startTime $endTime";

    //             $dataOutputAvg = shell_exec($commandAvg);
    //             $dataOutputMax = shell_exec($commandMax);

    //             if (empty($dataOutputAvg) || empty($dataOutputMax)) {
    //                 Log::warning("Empty RRD fetch output for file: $rrdFilePath");
    //                 continue;
    //             }

    //             $parsedDataAvg = $this->parseRrdFetchOutput($dataOutputAvg);
    //             $parsedDataMax = $this->parseRrdFetchOutput($dataOutputMax);
    //             $trafficSummary = $this->getTrafficSummary($parsedDataAvg, $parsedDataMax);

    //             if (!empty($trafficSummary['max_rate'])) {
    //                 $maxDownloadMbps = $trafficSummary['max_rate']['in_mbps'] ?? 0;
    //                 $maxUploadMbps = $trafficSummary['max_rate']['out_mbps'] ?? 0;
    //                 $maxDownloadTime = $trafficSummary['max_rate']['in_peak_time'] ?? now($timezone);
    //                 $maxUploadTime = $trafficSummary['max_rate']['out_peak_time'] ?? now($timezone);

    //                 try {
    //                     \App\Models\NasInterfaceUtilization::on('pgsql')->create([
    //                         'activation_plan_id' => $activationPlanId,
    //                         'interface_port' => $port_id,
    //                         'max_download_mbps' => $maxDownloadMbps,
    //                         'max_upload_mbps' => $maxUploadMbps,
    //                         'max_download_collected_at' => $maxDownloadTime,
    //                         'max_upload_collected_at' => $maxUploadTime,
    //                     ]);
    //                 } catch (\Exception $e) {
    //                     Log::error("Failed to insert utilization for port $port_id ($host_ip): " . $e->getMessage());
    //                     $hostsData[$host_ip]['errors'][] = "Failed to insert for port $port_id: " . $e->getMessage();
    //                     continue;
    //                 }

    //                 $portsData[] = [
    //                     'port_id' => $port_id,
    //                     'traffic_summary' => $trafficSummary,
    //                 ];
    //             }
    //         }

    //         $hostsData[$host_ip] = [
    //             'status' => 'success',
    //             'activation_plan_id' => $activationPlanId,
    //             'requested_range' => [
    //                 'start_datetime' => $startDateString,
    //                 'end_datetime' => $endDateString,
    //             ],
    //             'port_count' => count($portsData),
    //             'ports' => $portsData
    //         ];
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'hosts' => $hostsData,
    //     ]);
    // }

    // new 220

    // public function getPortData(string $startDateString = null, string $endDateString = null): JsonResponse
    // {
    //     $rrdFileBaseDir = '/var/www/html/nttn-monitoring-application/partner-backend/storage/rrd/rrd/';
    //     $resolution = '-r 300';
    //     $timezone = 'Asia/Dhaka'; // Bangladesh timezone

    //     // Fetch NAS IPs
    //     $nasIps = DB::connection('pgsql')
    //         ->table('partner_activation_plans')
    //         ->pluck('nas_ip', 'id')
    //         ->filter()
    //         ->unique();

    //     if ($nasIps->isEmpty()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'No NAS IPs found in the database.'
    //         ], 404);
    //     }

    //     $hostsData = [];

    //     foreach ($nasIps as $activationPlanId => $host_ip) {
    //         $host_ip = trim($host_ip);
    //         if (empty($host_ip)) {
    //             $hostsData[$activationPlanId] = [
    //                 'status' => 'error',
    //                 'message' => 'Empty NAS IP found for activation plan ID ' . $activationPlanId
    //             ];
    //             continue;
    //         }

    //         $portRrdDirectory = rtrim($rrdFileBaseDir, '/') . '/' . $host_ip;

    //         if (!is_dir($portRrdDirectory)) {
    //             Log::warning("RRD directory not found: '$portRrdDirectory'");
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "RRD directory for host $host_ip not found. Checked: '$portRrdDirectory'"
    //             ];
    //             continue;
    //         }

    //         $portFiles = glob($portRrdDirectory . '/port-id*.rrd');
    //         if (empty($portFiles)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "No port RRD files found for host $host_ip."
    //             ];
    //             continue;
    //         }

    //         $portsData = [];

    //         foreach ($portFiles as $rrdFilePath) {
    //             $filename = basename($rrdFilePath);
    //             $port_id = (int)preg_replace('/[^0-9]/', '', $filename);

    //             // --- 🧠 Find latest record per NAS IP + interface_port ---
    //             $latestCreatedAt = DB::connection('pgsql')
    //                 ->table('nas_interface_utilizations as c')
    //                 ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
    //                 ->where('p.nas_ip', $host_ip)
    //                 ->where('c.interface_port', $port_id)
    //                 ->max('c.created_at');

    //             if ($latestCreatedAt) {
    //                 // Start time = last record + 1 sec
    //                 $startDate = Carbon::parse($latestCreatedAt, $timezone)->addSecond();
    //             } else {
    //                 // If no data exists for this port, start 1 hour ago
    //                 $startDate = Carbon::now($timezone)->subHour();
    //             }

    //             $endDate = Carbon::now($timezone);

    //             // Format for display
    //             $startDateString = $startDate->format('Y-m-d H:i:s');
    //             $endDateString = $endDate->format('Y-m-d H:i:s');

    //             if ($startDate->gte($endDate)) {
    //                 $portsData[] = [
    //                     'port_id' => $port_id,
    //                     'status' => 'info',
    //                     'message' => "No new data to process for port $port_id. Last collected at: $latestCreatedAt",
    //                 ];
    //                 continue;
    //             }

    //             // Convert to UNIX timestamps for RRDTool
    //             $startUnix = $startDate->timestamp;
    //             $endUnix = $endDate->timestamp;
    //             $startTime = '-s ' . $startUnix;
    //             $endTime = '-e ' . $endUnix;

    //             $commandAvg = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime";
    //             $commandMax = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MAX $resolution $startTime $endTime";

    //             $dataOutputAvg = shell_exec($commandAvg);
    //             $dataOutputMax = shell_exec($commandMax);

    //             if (empty($dataOutputAvg) || empty($dataOutputMax)) {
    //                 Log::warning("Empty RRD fetch output for file: $rrdFilePath");
    //                 continue;
    //             }

    //             $parsedDataAvg = $this->parseRrdFetchOutput($dataOutputAvg);
    //             $parsedDataMax = $this->parseRrdFetchOutput($dataOutputMax);
    //             $trafficSummary = $this->getTrafficSummary($parsedDataAvg, $parsedDataMax);

    //             if (!empty($trafficSummary['max_rate'])) {
    //                 $maxDownloadMbps = $trafficSummary['max_rate']['in_mbps'] ?? 0;
    //                 $maxUploadMbps = $trafficSummary['max_rate']['out_mbps'] ?? 0;
    //                 $maxDownloadTime = $trafficSummary['max_rate']['in_peak_time'] ?? now($timezone);
    //                 $maxUploadTime = $trafficSummary['max_rate']['out_peak_time'] ?? now($timezone);

    //                 try {
    //                     \App\Models\NasInterfaceUtilization::on('pgsql')->create([
    //                         'activation_plan_id' => $activationPlanId,
    //                         'interface_port' => $port_id,
    //                         'max_download_mbps' => $maxDownloadMbps,
    //                         'max_upload_mbps' => $maxUploadMbps,
    //                         'max_download_collected_at' => $maxDownloadTime,
    //                         'max_upload_collected_at' => $maxUploadTime,
    //                     ]);
    //                 } catch (\Exception $e) {
    //                     Log::error("Failed to insert utilization for port $port_id ($host_ip): " . $e->getMessage());
    //                     $portsData[] = [
    //                         'port_id' => $port_id,
    //                         'status' => 'error',
    //                         'message' => $e->getMessage(),
    //                     ];
    //                     continue;
    //                 }

    //                 $portsData[] = [
    //                     'port_id' => $port_id,
    //                     'status' => 'success',
    //                     'requested_range' => [
    //                         'start_datetime' => $startDateString,
    //                         'end_datetime' => $endDateString,
    //                     ],
    //                     'traffic_summary' => $trafficSummary,
    //                 ];
    //             }
    //         }

    //         $hostsData[$host_ip] = [
    //             'status' => 'success',
    //             'activation_plan_id' => $activationPlanId,
    //             'port_count' => count($portsData),
    //             'ports' => $portsData
    //         ];
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'hosts' => $hostsData,
    //     ]);
    // }


    public function getPortDataMod(string $startDateString = null, string $endDateString = null): JsonResponse
    {
        $remoteRrdBaseDir = '/opt/librenms/rrd/';
        $remoteHost = '172.17.18.82';
        $remotePort = 65122;
        $remoteUser = 'linkmon';
        $remotePass = '!TB5731roL#';
        $resolution = '-r 300';
        $timezone = 'Asia/Dhaka'; // Bangladesh timezone

        // Connect to remote server via SFTP
        $sftp = new SFTP($remoteHost, $remotePort);
        if (!$sftp->login($remoteUser, $remotePass)) {
            return response()->json([
                'status' => 'error',
                'message' => 'SFTP login failed to remote RRD server.'
            ], 500);
        }

        // Fetch NAS IPs
        $nasIps = DB::connection('pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id')
            ->filter()
            ->unique();

        if ($nasIps->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No NAS IPs found in the database.'
            ], 404);
        }

        $hostsData = [];

        foreach ($nasIps as $activationPlanId => $host_ip) {
            $host_ip = trim($host_ip);
            if (empty($host_ip)) {
                $hostsData[$activationPlanId] = [
                    'status' => 'error',
                    'message' => 'Empty NAS IP found for activation plan ID ' . $activationPlanId
                ];
                continue;
            }

            $portRrdDirectory = rtrim($remoteRrdBaseDir, '/') . '/' . $host_ip;

            // Check if remote directory exists via SFTP
            $remoteDirListing = $sftp->nlist($portRrdDirectory);
            if ($remoteDirListing === false) {
                Log::warning("Remote RRD directory not found: '$portRrdDirectory' on $remoteHost");
                $hostsData[$host_ip] = [  // Changed back to $host_ip as key
                    'status' => 'error',
                    'message' => "RRD directory for host $host_ip not found on remote server. Checked: '$portRrdDirectory'"
                ];
                continue;
            }

            // Get port files list from remote server
            $portFiles = [];
            foreach ($remoteDirListing as $file) {
                if (str_starts_with($file, 'port-id') && str_ends_with($file, '.rrd')) {
                    $portFiles[] = $portRrdDirectory . '/' . $file;
                }
            }

            if (empty($portFiles)) {
                $hostsData[$host_ip] = [  // Changed back to $host_ip as key
                    'status' => 'error',
                    'message' => "No port RRD files found for host $host_ip on remote server."
                ];
                continue;
            }

            $portsData = [];

            foreach ($portFiles as $rrdFilePath) {
                $filename = basename($rrdFilePath);
                $port_id = (int)preg_replace('/[^0-9]/', '', $filename);

                // --- 🧠 Find latest record per NAS IP + interface_port ---
                $latestCreatedAt = DB::connection('pgsql')
                    ->table('nas_interface_utilizations as c')
                    ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
                    ->where('p.nas_ip', $host_ip)
                    ->where('c.interface_port', $port_id)
                    ->max('c.created_at');

                if ($latestCreatedAt) {
                    // Start time = last record + 1 sec
                    $startDate = Carbon::parse($latestCreatedAt, $timezone)->addSecond();
                } else {
                    // If no data exists for this port, start 1 hour ago
                    $startDate = Carbon::now($timezone)->subHour();
                }

                $endDate = Carbon::now($timezone);

                // Format for display
                $startDateString = $startDate->format('Y-m-d H:i:s');
                $endDateString = $endDate->format('Y-m-d H:i:s');

                if ($startDate->gte($endDate)) {
                    $portsData[] = [
                        'port_id' => $port_id,
                        'status' => 'info',
                        'message' => "No new data to process for port $port_id. Last collected at: $latestCreatedAt",
                    ];
                    continue;
                }

                // Convert to UNIX timestamps for RRDTool
                $startUnix = $startDate->timestamp;
                $endUnix = $endDate->timestamp;
                $startTime = '-s ' . $startUnix;
                $endTime = '-e ' . $endUnix;

                // Download RRD file to temporary location
                $tmpFile = tempnam(sys_get_temp_dir(), 'rrd_');
                $rrdFileContent = $sftp->get($rrdFilePath);

                if ($rrdFileContent === false) {
                    Log::warning("Failed to download RRD file from remote server: $rrdFilePath");
                    continue;
                }

                file_put_contents($tmpFile, $rrdFileContent);

                // Execute RRD commands locally on downloaded file
                $commandAvg = self::RRDTOOL_EXECUTABLE . " fetch \"$tmpFile\" AVERAGE $resolution $startTime $endTime";
                $commandMax = self::RRDTOOL_EXECUTABLE . " fetch \"$tmpFile\" MAX $resolution $startTime $endTime";

                $dataOutputAvg = shell_exec($commandAvg);
                $dataOutputMax = shell_exec($commandMax);

                // Clean up temporary file
                unlink($tmpFile);

                if (empty($dataOutputAvg) || empty($dataOutputMax)) {
                    Log::warning("Empty RRD fetch output for remote file: $rrdFilePath");
                    continue;
                }

                $parsedDataAvg = $this->parseRrdFetchOutput($dataOutputAvg);
                $parsedDataMax = $this->parseRrdFetchOutput($dataOutputMax);
                $trafficSummary = $this->getTrafficSummary($parsedDataAvg, $parsedDataMax);

                if (!empty($trafficSummary['max_rate'])) {
                    $maxDownloadMbps = $trafficSummary['max_rate']['in_mbps'] ?? 0;
                    $maxUploadMbps = $trafficSummary['max_rate']['out_mbps'] ?? 0;
                    $maxDownloadTime = $trafficSummary['max_rate']['in_peak_time'] ?? now($timezone);
                    $maxUploadTime = $trafficSummary['max_rate']['out_peak_time'] ?? now($timezone);

                    try {
                        \App\Models\NasInterfaceUtilization::on('pgsql')->create([
                            'activation_plan_id' => $activationPlanId,
                            'interface_port' => $port_id,
                            'max_download_mbps' => $maxDownloadMbps,
                            'max_upload_mbps' => $maxUploadMbps,
                            'max_download_collected_at' => $maxDownloadTime,
                            'max_upload_collected_at' => $maxUploadTime,
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Failed to insert utilization for port $port_id ($host_ip): " . $e->getMessage());
                        $portsData[] = [
                            'port_id' => $port_id,
                            'status' => 'error',
                            'message' => $e->getMessage(),
                        ];
                        continue;
                    }

                    $portsData[] = [
                        'port_id' => $port_id,
                        'status' => 'success',
                        'requested_range' => [
                            'start_datetime' => $startDateString,
                            'end_datetime' => $endDateString,
                        ],
                        'traffic_summary' => $trafficSummary,
                    ];
                }
            }

            $hostsData[$host_ip] = [  // This is the key change - using $host_ip as array key
                'status' => 'success',
                'activation_plan_id' => $activationPlanId,
                'port_count' => count($portsData),
                'ports' => $portsData
            ];
        }

        return response()->json([
            'status' => 'success',
            'hosts' => $hostsData,
        ]);
    }

    // librenms RRD fetch


    public function getPortData(string $startDateString = null, string $endDateString = null): JsonResponse
    {
        $remoteRrdBaseDir = '/opt/librenms/rrd/';
        $remoteHost = '172.17.18.82';
        $remotePort = 65122;
        $remoteUser = 'linkmon';
        $remotePass = '!TB5731roL#';
        $resolution = '-r 300';
        $timezone = 'Asia/Dhaka';

        // Connect to remote RRD server via SFTP
        $sftp = new \phpseclib3\Net\SFTP($remoteHost, $remotePort);
        if (!$sftp->login($remoteUser, $remotePass)) {
            return response()->json([
                'status' => 'error',
                'message' => 'SFTP login failed to remote RRD server.'
            ], 500);
        }

        // Fetch NAS IPs
        $nasIps = DB::connection('pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id')
            ->filter()
            ->unique();

        if ($nasIps->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No NAS IPs found in the database.'
            ], 404);
        }

        $hostsData = [];

        foreach ($nasIps as $activationPlanId => $host_ip) {
            $host_ip = trim($host_ip);
            if (empty($host_ip)) {
                $hostsData[$activationPlanId] = [
                    'status' => 'error',
                    'message' => 'Empty NAS IP found for activation plan ID ' . $activationPlanId
                ];
                continue;
            }

            $portRrdDirectory = rtrim($remoteRrdBaseDir, '/') . '/' . $host_ip;

            // --- Check remote directory existence ---
            $remoteDirListing = $sftp->nlist($portRrdDirectory);
            if ($remoteDirListing === false) {
                Log::warning("RRD directory not found: '$portRrdDirectory' on remote host");
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "RRD directory for host $host_ip not found. Checked: '$portRrdDirectory'"
                ];
                continue;
            }

            /**
             * STEP 1: Get allowed interfaces from your app DB
             */
            $allowedInterfaces = DB::connection('pgsql')
                ->table('partner_interface_configs')
                ->where('activation_plan_id', $activationPlanId)
                ->pluck('interface_name')
                ->toArray();

            if (empty($allowedInterfaces)) {
                $hostsData[$host_ip] = [
                    'status' => 'warning',
                    'message' => "No allowed interfaces found for activation plan ID $activationPlanId"
                ];
                continue;
            }

            /**
             * STEP 2: Get matching LibreNMS port IDs
             */
            $portInfo = DB::connection('librenms')
                ->table('devices as d')
                ->join('ports as p', 'd.device_id', '=', 'p.device_id')
                ->select('p.port_id', 'p.ifName')
                ->where('d.hostname', $host_ip)
                ->whereIn('p.ifName', $allowedInterfaces)
                ->get();

            $allowedPortIds = $portInfo->pluck('port_id')->toArray();

            if (empty($allowedPortIds)) {
                $hostsData[$host_ip] = [
                    'status' => 'warning',
                    'message' => "No matching port IDs found in LibreNMS for $host_ip and interfaces: " . implode(',', $allowedInterfaces)
                ];
                continue;
            }

            /**
             * STEP 3: Filter RRD files by allowed port IDs
             */
            $portFiles = [];
            foreach ($remoteDirListing as $file) {
                foreach ($allowedPortIds as $pid) {
                    if (str_starts_with($file, "port-id$pid") && str_ends_with($file, '.rrd')) {
                        $portFiles[] = $portRrdDirectory . '/' . $file;
                    }
                }
            }

            if (empty($portFiles)) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "No allowed port RRD files found for host $host_ip.",
                ];
                continue;
            }

            /**
             * STEP 4: Process RRD files as before
             */
            $portsData = [];

            foreach ($portFiles as $rrdFilePath) {
                $filename = basename($rrdFilePath);
                $port_id = (int)preg_replace('/[^0-9]/', '', $filename);

                $latestCreatedAt = DB::connection('pgsql')
                    ->table('nas_interface_utilizations as c')
                    ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
                    ->where('p.nas_ip', $host_ip)
                    ->where('c.interface_port', $port_id)
                    ->max('c.created_at');

                if ($latestCreatedAt) {
                    $startDate = Carbon::parse($latestCreatedAt, $timezone)->addSecond();
                } else {
                    $startDate = Carbon::now($timezone)->subHour();
                }

                $endDate = Carbon::now($timezone);

                if ($startDate->gte($endDate)) {
                    $portsData[] = [
                        'port_id' => $port_id,
                        'status' => 'info',
                        'message' => "No new data to process for port $port_id. Last collected at: $latestCreatedAt",
                    ];
                    continue;
                }

                $tmpFile = tempnam(sys_get_temp_dir(), 'rrd_');
                $rrdContent = $sftp->get($rrdFilePath);

                if ($rrdContent === false) {
                    Log::warning("Failed to fetch remote RRD file: $rrdFilePath");
                    continue;
                }

                file_put_contents($tmpFile, $rrdContent);

                $startUnix = $startDate->timestamp;
                $endUnix = $endDate->timestamp;

                $commandAvg = self::RRDTOOL_EXECUTABLE . " fetch \"$tmpFile\" AVERAGE $resolution -s $startUnix -e $endUnix";
                $commandMax = self::RRDTOOL_EXECUTABLE . " fetch \"$tmpFile\" MAX $resolution -s $startUnix -e $endUnix";

                $dataOutputAvg = shell_exec($commandAvg);
                $dataOutputMax = shell_exec($commandMax);

                unlink($tmpFile);

                if (empty($dataOutputAvg) || empty($dataOutputMax)) {
                    Log::warning("Empty RRD output for port $port_id ($host_ip)");
                    continue;
                }

                $parsedDataAvg = $this->parseRrdFetchOutput($dataOutputAvg);
                $parsedDataMax = $this->parseRrdFetchOutput($dataOutputMax);
                $trafficSummary = $this->getTrafficSummary($parsedDataAvg, $parsedDataMax);

                if (!empty($trafficSummary['max_rate'])) {
                    try {
                        \App\Models\NasInterfaceUtilization::on('pgsql')->create([
                            'activation_plan_id' => $activationPlanId,
                            'interface_port' => $port_id,
                            'max_download_mbps' => $trafficSummary['max_rate']['in_mbps'] ?? 0,
                            'max_upload_mbps' => $trafficSummary['max_rate']['out_mbps'] ?? 0,
                            'max_download_collected_at' => $trafficSummary['max_rate']['in_peak_time'] ?? now($timezone),
                            'max_upload_collected_at' => $trafficSummary['max_rate']['out_peak_time'] ?? now($timezone),
                        ]);

                        $portsData[] = [
                            'port_id' => $port_id,
                            'status' => 'success',
                            'traffic_summary' => $trafficSummary,
                        ];
                    } catch (\Exception $e) {
                        Log::error("Failed to insert utilization for port $port_id ($host_ip): " . $e->getMessage());
                    }
                }
            }

            $hostsData[$host_ip] = [
                'status' => 'success',
                'activation_plan_id' => $activationPlanId,
                'port_count' => count($portsData),
                'ports' => $portsData
            ];
        }

        return response()->json([
            'status' => 'success',
            'hosts' => $hostsData,
        ]);
    }



// public function getPortData(): JsonResponse
// {
//     $remoteRrdBaseDir = '/opt/librenms/rrd/';
//     $remoteHost = '172.17.18.82';
//     $remoteUser = 'itbilladmin';
//     $remotePass = 'hsr$8-2v%Qse-e@bS!g';
//     $resolution = '-r 300';
//     $timezone = 'Asia/Dhaka';

//     // Connect to remote server via SFTP
//     // $sftp = new SFTP($remoteHost, 65122); // adjust port if needed
//     // if (!$sftp->login($remoteUser, $remotePass)) {
//     //     return response()->json(['status' => 'error', 'message' => 'SFTP login failed.'], 500);
//     // }

//     // $sftp = new SSH2('172.17.18.82', 65122);
//     //     if (!$sftp->login('linkmon', '!TB5731roL#')) {
//     //         return response()->json(['status' => false, 'message' => 'SSH login failed'], 500);
//     //     }
//     $sftp = new SFTP('172.17.18.82', 65122);
//         if (!$sftp->login('linkmon', '!TB5731roL#')) {
//             return response()->json(['status' => false, 'message' => 'SFTP login failed'], 500);
//         }

//     // Fetch NAS IPs from LibreNMS DB
//     $nasIps = DB::connection('librenms')
//         ->table('devices')
//         ->pluck('hostname', 'device_id')
//         ->filter()
//         ->unique();

//     if ($nasIps->isEmpty()) {
//         return response()->json(['status' => 'error', 'message' => 'No NAS IPs found in LibreNMS.'], 404);
//     }

//     $hostsData = [];

//     foreach ($nasIps as $deviceId => $host_ip) {
//         $host_ip = trim($host_ip);
//         if (empty($host_ip)) continue;

//         $startDate = Carbon::now($timezone)->subMinutes(30);
//         $endDate   = Carbon::now($timezone);
//         $startUnix = $startDate->timestamp;
//         $endUnix   = $endDate->timestamp;
//         $startTime = '-s ' . $startUnix;
//         $endTime   = '-e ' . $endUnix;

//         // Remote RRD directory
//         $remoteDir = rtrim($remoteRrdBaseDir . $host_ip, '/') . '/';
//         $portFiles = $sftp->nlist($remoteDir);
//         if (!$portFiles) {
//             $hostsData[$host_ip] = ['status' => 'error', 'message' => "No port RRD files found on remote server"];
//             continue;
//         }

//         $portsData = [];

//         foreach ($portFiles as $file) {
//             if (!str_starts_with($file, 'port-id') || !str_ends_with($file, '.rrd')) continue;

//             $remoteFilePath = $remoteDir . $file;
//             $tmpFile = tempnam(sys_get_temp_dir(), 'rrd');
//             file_put_contents($tmpFile, $sftp->get($remoteFilePath));

//             // Fetch data using RRDTool locally
//             $commandAvg = self::RRDTOOL_EXECUTABLE . " fetch \"$tmpFile\" AVERAGE $resolution $startTime $endTime";
//             $commandMax = self::RRDTOOL_EXECUTABLE . " fetch \"$tmpFile\" MAX $resolution $startTime $endTime";

//             $dataOutputAvg = shell_exec($commandAvg);
//             $dataOutputMax = shell_exec($commandMax);

//             unlink($tmpFile); // cleanup

//             if (empty($dataOutputAvg) || empty($dataOutputMax)) continue;

//             $parsedDataAvg = $this->parseRrdFetchOutput($dataOutputAvg);
//             $parsedDataMax = $this->parseRrdFetchOutput($dataOutputMax);
//             $trafficSummary = $this->getTrafficSummary($parsedDataAvg, $parsedDataMax);

//             $port_id = (int)preg_replace('/[^0-9]/', '', $file);
//             $portsData[] = ['port_id' => $port_id, 'traffic_summary' => $trafficSummary];
//         }

//         $hostsData[$host_ip] = [
//             'status' => 'success',
//             'activation_plan_id' => $deviceId,
//             'requested_range' => [
//                 'start_datetime' => $startDate->format('Y-m-d H:i:s'),
//                 'end_datetime' => $endDate->format('Y-m-d H:i:s')
//             ],
//             'port_count' => count($portsData),
//             'ports' => $portsData
//         ];
//     }

//     return response()->json(['status' => 'success', 'hosts' => $hostsData]);
// }




    protected function getTrafficSummary(array $avgData, array $maxData)
    {
        if (empty($avgData)) {
            return [
                'total_in' => ['bytes' => 0.0, 'gigabytes' => 0.0],
                'total_out' => ['bytes' => 0.0, 'gigabytes' => 0.0],
                'max_rate' => ['in_mbps' => 0.0, 'out_mbps' => 0.0, 'in_peak_time' => null, 'out_peak_time' => null],
                'total_combined_gigabytes' => 0.0,
                'time_range' => ['from_time' => null, 'to_time' => null]
            ];
        }

        $totalInBytes = 0.0;
        $totalOutBytes = 0.0;
        $maxInRate = 0.0;
        $maxOutRate = 0.0;
        $maxInRateTime = null;
        $maxOutRateTime = null;
        $lastUnixTimestamp = null;

        $minTime = $avgData[0]['timestamp_formatted'] ?? null;
        $maxTime = end($avgData)['timestamp_formatted'] ?? null;


        foreach ($avgData as $row) {
            $currentUnixTimestamp = $row['timestamp_unix'];

            if ($lastUnixTimestamp !== null) {
                $timeElapsed = $currentUnixTimestamp - $lastUnixTimestamp;

                if (isset($row['INOCTETS']) && is_numeric($row['INOCTETS']) && $row['INOCTETS'] !== null) {
                    $totalInBytes += (float)$row['INOCTETS'] * $timeElapsed;
                }
                if (isset($row['OUTOCTETS']) && is_numeric($row['OUTOCTETS']) && $row['OUTOCTETS'] !== null) {
                    $totalOutBytes += (float)$row['OUTOCTETS'] * $timeElapsed;
                }
            }
            $lastUnixTimestamp = $currentUnixTimestamp;
        }


        foreach ($maxData as $row) {
            $currentTime = $row['timestamp_formatted'];


            if (isset($row['INOCTETS']) && is_numeric($row['INOCTETS']) && $row['INOCTETS'] !== null) {
                $currentInRate = (float)$row['INOCTETS'];
                if ($currentInRate > $maxInRate) {
                    $maxInRate = $currentInRate;
                    $maxInRateTime = $currentTime;
                }
            }


            if (isset($row['OUTOCTETS']) && is_numeric($row['OUTOCTETS']) && $row['OUTOCTETS'] !== null) {
                $currentOutRate = (float)$row['OUTOCTETS'];
                if ($currentOutRate > $maxOutRate) {
                    $maxOutRate = $currentOutRate;
                    $maxOutRateTime = $currentTime;
                }
            }
        }


        $BYTES_PER_GB = 1024 * 1024 * 1024;

        return [
            'total_in' => [
                'bytes' => round($totalInBytes, 2),
                'gigabytes' => round($totalInBytes / $BYTES_PER_GB, 4)
            ],
            'total_out' => [
                'bytes' => round($totalOutBytes, 2),
                'gigabytes' => round($totalOutBytes / $BYTES_PER_GB, 4)
            ],
            'max_rate' => [
                // Convert B/s to Mbps (Megabits per second)
                'in_mbps' => round($maxInRate * self::OCTETS_TO_MBPS, 2),
                'out_mbps' => round($maxOutRate * self::OCTETS_TO_MBPS, 2),
                'in_peak_time' => $maxInRateTime,
                'out_peak_time' => $maxOutRateTime
            ],
            'total_combined_gigabytes' => round(($totalInBytes + $totalOutBytes) / $BYTES_PER_GB, 4),
            'time_range' => [
                'from_time' => $minTime,
                'to_time' => $maxTime
            ]
        ];
    }


    // from 220
    // public function getDeviceCpuData(): JsonResponse
    // {
    //     $rrdFileBaseDir = '/var/www/html/nttn-monitoring-application/partner-backend/storage/rrd/rrd/';
    //     $timezone = 'Asia/Dhaka'; // GMT+6

    //     // Fetch NAS IPs dynamically from remote PostgreSQL
    //     $nasIps = DB::connection('pgsql')
    //         ->table('partner_activation_plans')
    //         ->pluck('nas_ip', 'id')
    //         ->filter()
    //         ->unique();

    //     if ($nasIps->isEmpty()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'No NAS IPs found in the database.'
    //         ], 404);
    //     }

    //     $hostsData = [];

    //     foreach ($nasIps as $activationPlanId => $host_ip) {

    //         $latestCreatedAt = DB::connection('pgsql')
    //             ->table('nas_cpu_usages as c')
    //             ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
    //             ->where('p.nas_ip', $host_ip)
    //             ->max('c.created_at');

    //         // Set start and end times
    //         if ($latestCreatedAt) {
    //             $startDate = Carbon::parse($latestCreatedAt, $timezone)->addSecond();
    //         } else {
    //             $startDate = Carbon::now($timezone)->subMinutes(30);
    //         }
    //         $endDate = Carbon::now($timezone);

    //         $startDateString = $startDate->format('Y-m-d H:i:s');
    //         $endDateString = $endDate->format('Y-m-d H:i:s');

    //         if ($startDate->gte($endDate)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'info',
    //                 'message' => "No new CPU data to process for host $host_ip. Last collected at: $latestCreatedAt",
    //                 'last_collected_at' => $latestCreatedAt,
    //             ];
    //             continue;
    //         }

    //         $startUnix = $startDate->timestamp;
    //         $endUnix = $endDate->timestamp;
    //         $startTime = '-s ' . $startUnix;
    //         $endTime = '-e ' . $endUnix;
    //         $resolution = '-r 300';

    //         $cpuRrdDirectory = rtrim($rrdFileBaseDir, '/') . '/' . $host_ip;

    //         if (!is_dir($cpuRrdDirectory)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "RRD directory for host $host_ip not found."
    //             ];
    //             continue;
    //         }

    //         $allCpuFiles = glob($cpuRrdDirectory . '/processor-hr-*.rrd');
    //         $numProcessors = count($allCpuFiles);
    //         $allCpuData = [];

    //         if ($numProcessors === 0) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "No processor RRD files found for host $host_ip."
    //             ];
    //             continue;
    //         }

    //         foreach ($allCpuFiles as $cpuFilePath) {
    //             $command = self::RRDTOOL_EXECUTABLE
    //                 . " fetch \"$cpuFilePath\" AVERAGE $resolution $startTime $endTime";
    //             $output = shell_exec($command);

    //             if (!empty($output)) {
    //                 $parsedData = $this->parseRrdFetchOutput($output);

    //                 foreach ($parsedData as $row) {
    //                     $timestamp = $row['timestamp_unix'];
    //                     $usage = $row['usage'] ?? 0.0;

    //                     if (!isset($allCpuData[$timestamp])) {
    //                         $allCpuData[$timestamp] = [
    //                             'timestamp_unix' => $timestamp,
    //                             'timestamp_formatted' => isset($row['timestamp_unix'])
    //                                 ? Carbon::createFromTimestamp($row['timestamp_unix'], $timezone)->format('Y-m-d H:i:s')
    //                                 : date('Y-m-d H:i:s', $timestamp),
    //                             'total_usage' => 0.0,
    //                             'count' => 0,
    //                         ];
    //                     }

    //                     if (is_numeric($usage)) {
    //                         $allCpuData[$timestamp]['total_usage'] += (float)$usage;
    //                         $allCpuData[$timestamp]['count']++;
    //                     }
    //                 }
    //             }
    //         }

    //         $aggregatedCpuResults = [];
    //         $maxLoadPeak = 0.0;
    //         $maxLoadTime = null;

    //         foreach ($allCpuData as $data) {
    //             if ($data['count'] > 0) {
    //                 $averageCpuPercent = round($data['total_usage'] / $data['count'], 2);
    //                 $aggregatedCpuResults[] = [
    //                     'timestamp_unix' => $data['timestamp_unix'],
    //                     'timestamp_formatted' => $data['timestamp_formatted'],
    //                     'average_cpu_percent' => $averageCpuPercent,
    //                 ];

    //                 if ($averageCpuPercent > $maxLoadPeak) {
    //                     $maxLoadPeak = $averageCpuPercent;
    //                     $maxLoadTime = $data['timestamp_formatted'];
    //                 }
    //             }
    //         }

    //         $allAverages = array_column($aggregatedCpuResults, 'average_cpu_percent');

    //         $summary = [
    //             'processor_count' => $numProcessors,
    //             'average_load_overall' => !empty($allAverages) ? round(array_sum($allAverages) / count($allAverages), 2) : 0.0,
    //             'max_load_peak' => $maxLoadPeak,
    //             'max_load_peak_time' => $maxLoadTime,
    //         ];

    //         // Insert into nas_cpu_usages if new peak exists
    //         if ($maxLoadPeak > 0 && $maxLoadTime) {
    //             NasCpuUsage::on('pgsql')->create([
    //                 'activation_plan_id' => $activationPlanId,
    //                 'collected_at' => $maxLoadTime,
    //                 'max_cpu_load' => $maxLoadPeak,
    //             ]);
    //         }

    //         // Store host-wise results
    //         $hostsData[$host_ip] = [
    //             'status' => 'success',
    //             'metric_type' => 'Aggregated_CPU_Usage',
    //             'requested_range' => [
    //                 'start_datetime' => $startDateString,
    //                 'end_datetime' => $endDateString,
    //             ],
    //             'cpu_summary' => $summary,
    //             'results' => $aggregatedCpuResults,
    //         ];
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'hosts' => $hostsData,
    //     ]);
    // }

    // librenms
    public function getDeviceCpuData(): JsonResponse
    {
        $remoteRrdBaseDir = '/opt/librenms/rrd/';
        $remoteHost = '172.17.18.82';
        $remotePort = 65122;
        $remoteUser = 'linkmon';
        $remotePass = '!TB5731roL#';
        $timezone = 'Asia/Dhaka'; // GMT+6

        // Fetch NAS IPs dynamically from remote PostgreSQL
        $nasIps = DB::connection('pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id')
            ->filter()
            ->unique();

        if ($nasIps->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No NAS IPs found in the database.'
            ], 404);
        }

        // --- Initialize SFTP connection ---
        $sftp = new SFTP($remoteHost, $remotePort);
        if (!$sftp->login($remoteUser, $remotePass)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to remote RRD host via SFTP.'
            ], 500);
        }

        $hostsData = [];

        foreach ($nasIps as $activationPlanId => $host_ip) {

            $latestCreatedAt = DB::connection('pgsql')
                ->table('nas_cpu_usages as c')
                ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
                ->where('p.nas_ip', $host_ip)
                ->max('c.created_at');

            // Set start and end times
            if ($latestCreatedAt) {
                $startDate = Carbon::parse($latestCreatedAt, $timezone)->addSecond();
            } else {
                $startDate = Carbon::now($timezone)->subMinutes(30);
            }
            $endDate = Carbon::now($timezone);

            $startDateString = $startDate->format('Y-m-d H:i:s');
            $endDateString = $endDate->format('Y-m-d H:i:s');

            if ($startDate->gte($endDate)) {
                $hostsData[$host_ip] = [
                    'status' => 'info',
                    'message' => "No new CPU data to process for host $host_ip. Last collected at: $latestCreatedAt",
                    'last_collected_at' => $latestCreatedAt,
                ];
                continue;
            }

            $startUnix = $startDate->timestamp;
            $endUnix = $endDate->timestamp;
            $startTime = '-s ' . $startUnix;
            $endTime = '-e ' . $endUnix;
            $resolution = '-r 300';

            $cpuRrdDirectory = rtrim($remoteRrdBaseDir, '/') . '/' . $host_ip;

            // --- Check remote directory existence ---
            $remoteDirListing = $sftp->nlist($cpuRrdDirectory);
            if ($remoteDirListing === false) {
                Log::warning("RRD directory not found: '$cpuRrdDirectory' on remote host");
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "RRD directory for host $host_ip not found. Checked: '$cpuRrdDirectory'"
                ];
                continue;
            }

            // --- Filter CPU RRD files from remote listing ---
            $allCpuFiles = [];
            foreach ($remoteDirListing as $file) {
                if (str_starts_with($file, 'processor-hr-') && str_ends_with($file, '.rrd')) {
                    $allCpuFiles[] = $cpuRrdDirectory . '/' . $file;
                }
            }

            $numProcessors = count($allCpuFiles);
            $allCpuData = [];

            if ($numProcessors === 0) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "No processor RRD files found for host $host_ip."
                ];
                continue;
            }

            foreach ($allCpuFiles as $cpuFilePath) {
                $command = self::RRDTOOL_EXECUTABLE
                    . " fetch \"$cpuFilePath\" AVERAGE $resolution $startTime $endTime";

                // Execute command remotely through SSH
                $output = $sftp->exec($command);

                if (!empty($output)) {
                    $parsedData = $this->parseRrdFetchOutput($output);

                    foreach ($parsedData as $row) {
                        $timestamp = $row['timestamp_unix'];
                        $usage = $row['usage'] ?? 0.0;

                        if (!isset($allCpuData[$timestamp])) {
                            $allCpuData[$timestamp] = [
                                'timestamp_unix' => $timestamp,
                                'timestamp_formatted' => isset($row['timestamp_unix'])
                                    ? Carbon::createFromTimestamp($row['timestamp_unix'], $timezone)->format('Y-m-d H:i:s')
                                    : date('Y-m-d H:i:s', $timestamp),
                                'total_usage' => 0.0,
                                'count' => 0,
                            ];
                        }

                        if (is_numeric($usage)) {
                            $allCpuData[$timestamp]['total_usage'] += (float)$usage;
                            $allCpuData[$timestamp]['count']++;
                        }
                    }
                }
            }

            $aggregatedCpuResults = [];
            $maxLoadPeak = 0.0;
            $maxLoadTime = null;

            foreach ($allCpuData as $data) {
                if ($data['count'] > 0) {
                    $averageCpuPercent = round($data['total_usage'] / $data['count'], 2);
                    $aggregatedCpuResults[] = [
                        'timestamp_unix' => $data['timestamp_unix'],
                        'timestamp_formatted' => $data['timestamp_formatted'],
                        'average_cpu_percent' => $averageCpuPercent,
                    ];

                    if ($averageCpuPercent > $maxLoadPeak) {
                        $maxLoadPeak = $averageCpuPercent;
                        $maxLoadTime = $data['timestamp_formatted'];
                    }
                }
            }

            $allAverages = array_column($aggregatedCpuResults, 'average_cpu_percent');

            $summary = [
                'processor_count' => $numProcessors,
                'average_load_overall' => !empty($allAverages) ? round(array_sum($allAverages) / count($allAverages), 2) : 0.0,
                'max_load_peak' => $maxLoadPeak,
                'max_load_peak_time' => $maxLoadTime,
            ];

            // Insert into nas_cpu_usages if new peak exists
            if ($maxLoadPeak > 0 && $maxLoadTime) {
                NasCpuUsage::on('pgsql')->create([
                    'activation_plan_id' => $activationPlanId,
                    'collected_at' => $maxLoadTime,
                    'max_cpu_load' => $maxLoadPeak,
                ]);
            }

            // Store host-wise results
            $hostsData[$host_ip] = [
                'status' => 'success',
                'metric_type' => 'Aggregated_CPU_Usage',
                'requested_range' => [
                    'start_datetime' => $startDateString,
                    'end_datetime' => $endDateString,
                ],
                'cpu_summary' => $summary,
                'results' => $aggregatedCpuResults,
            ];
        }

        return response()->json([
            'status' => 'success',
            'hosts' => $hostsData,
        ]);
    }


    // from 220


    // public function getMempoolPerformanceData(): JsonResponse
    // {
    //     $timezone = 'Asia/Dhaka'; // GMT+6
    //     $resolution = '-r 300';   // 5 min interval
    //     $rrdFileBaseDir = '/var/www/html/nttn-monitoring-application/partner-backend/storage/rrd/rrd/';

    //     // --- 1. Fetch NAS IPs ---
    //     $nasIps = DB::connection('pgsql')
    //         ->table('partner_activation_plans')
    //         ->pluck('nas_ip', 'id') // key = activation_plan_id
    //         ->filter()
    //         ->unique();

    //     if ($nasIps->isEmpty()) {
    //         return response()->json(['status' => 'error', 'message' => 'No NAS IPs found.'], 404);
    //     }

    //     $hostsData = [];

    //     foreach ($nasIps as $activationPlanId => $host_ip) {
    //         $host_ip = trim($host_ip);
    //         if (empty($host_ip)) continue;

    //         // --- 2. Get latest RAM usage collected_at ---
    //         $latestCreatedAt = DB::connection('pgsql')
    //             ->table('nas_ram_usages as c')
    //             ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
    //             ->where('p.nas_ip', $host_ip)
    //             ->max('c.created_at');

    //         // --- 3. Set start and end times ---
    //         if ($latestCreatedAt) {
    //             $startDate = Carbon::parse($latestCreatedAt, $timezone)->addSecond();
    //         } else {
    //             $startDate = Carbon::now($timezone)->subMinutes(30); // last 30 minutes if no record
    //         }
    //         $endDate = Carbon::now($timezone);

    //         $startDateString = $startDate->format('Y-m-d H:i:s');
    //         $endDateString = $endDate->format('Y-m-d H:i:s');

    //         if ($startDate->gte($endDate)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'info',
    //                 'message' => "No new memory data to process for host $host_ip. Last collected at: $latestCreatedAt",
    //                 'last_collected_at' => $latestCreatedAt,
    //             ];
    //             continue;
    //         }

    //         // --- 4. Convert to UNIX timestamps for RRDTool (UTC) ---
    //         $startUnix = $startDate->copy()->setTimezone('UTC')->timestamp;
    //         $endUnix   = $endDate->copy()->setTimezone('UTC')->timestamp;
    //         $startTime = '-s ' . $startUnix;
    //         $endTime   = '-e ' . $endUnix;

    //         // --- 5. Prepare RRD file ---
    //         $rrdFilePath = $rrdFileBaseDir . $host_ip . '/mempool-hrstorage-system-65536.rrd';

    //         if (!is_file($rrdFilePath)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "RRD file not found for $host_ip",
    //             ];
    //             continue;
    //         }

    //         // --- 6. Fetch RRD Data ---
    //         $command = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime used free";
    //         $output = shell_exec($command);

    //         if (!$output || trim($output) === '') {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "Failed to fetch memory data for $host_ip",
    //             ];
    //             continue;
    //         }

    //         $parsedData = $this->parseRrdFetchOutput($output);

    //         if (empty($parsedData)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "No memory data found for $host_ip",
    //             ];
    //             continue;
    //         }

    //         // --- 7. Process Summary ---
    //         $summary = $this->calculateMemorySummary($parsedData);

    //         $maxMemoryPercent = $summary['used_percent_summary']['max'];
    //         $maxTimestamp = $summary['max_percent_timestamp'];

    //         if ($maxMemoryPercent && $maxTimestamp) {
    //             NasRamUsage::on('pgsql')->create([
    //                 'activation_plan_id' => $activationPlanId,
    //                 'max_memory_load'    => $maxMemoryPercent,
    //                 'collected_at'       => Carbon::createFromTimestamp($maxTimestamp, $timezone)->format('Y-m-d H:i:s'),
    //             ]);
    //         }

    //         $hostsData[$host_ip] = [
    //             'status' => 'success',
    //             'metric_type' => 'Mempool Usage',
    //             'device_ip' => $host_ip,
    //             'rrd_file_used' => $rrdFilePath,
    //             'requested_range' => [
    //                 'start_datetime' => $startDateString,
    //                 'end_datetime'   => $endDateString,
    //             ],
    //             'mempool_summary' => [
    //                 'main_memory' => [
    //                     'percent' => [
    //                         'Min' => $summary['used_percent_summary']['min'] . '%',
    //                         'Max' => $summary['used_percent_summary']['max'] . '%',
    //                         'Cur' => $summary['used_percent_summary']['cur'] . '%',
    //                         'max_timestamp' => $maxTimestamp ? Carbon::createFromTimestamp($maxTimestamp, $timezone)->format('Y-m-d H:i:s') : null,
    //                         'max_timestamp_unix' => $maxTimestamp,
    //                     ],
    //                     'current_value' => $this->formatBytesForSiB($summary['used_bytes_cur_raw']),
    //                 ],
    //                 'Total' => $this->formatBytesForSiB($summary['total_bytes_raw']),
    //                 'raw_data' => [
    //                     'used_bytes_cur' => $summary['used_bytes_cur_raw'],
    //                     'total_bytes'    => $summary['total_bytes_raw'],
    //                 ]
    //             ],
    //             'time_series_raw' => $parsedData,
    //         ];
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'hosts' => $hostsData,
    //     ]);
    // }


    // librenms


    public function getMempoolPerformanceData(): JsonResponse
    {
        $remoteRrdBaseDir = '/opt/librenms/rrd/';
        $remoteHost = '172.17.18.82';
        $remotePort = 65122;
        $remoteUser = 'linkmon';
        $remotePass = '!TB5731roL#';
        $timezone = 'Asia/Dhaka'; // GMT+6
        $resolution = '-r 300';   // 5 min interval

        // Fetch NAS IPs dynamically from remote PostgreSQL
        $nasIps = DB::connection('pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id') // key = activation_plan_id
            ->filter()
            ->unique();

        if ($nasIps->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'No NAS IPs found.'], 404);
        }

        // --- Initialize SFTP connection ---
        $sftp = new SFTP($remoteHost, $remotePort);
        if (!$sftp->login($remoteUser, $remotePass)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to remote RRD host via SFTP.'
            ], 500);
        }

        $hostsData = [];

        foreach ($nasIps as $activationPlanId => $host_ip) {
            $host_ip = trim($host_ip);
            if (empty($host_ip)) continue;

            // --- 2. Get latest RAM usage collected_at ---
            $latestCreatedAt = DB::connection('pgsql')
                ->table('nas_ram_usages as c')
                ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
                ->where('p.nas_ip', $host_ip)
                ->max('c.created_at');

            // --- 3. Set start and end times ---
            if ($latestCreatedAt) {
                $startDate = Carbon::parse($latestCreatedAt, $timezone)->addSecond();
            } else {
                $startDate = Carbon::now($timezone)->subMinutes(30);
            }
            $endDate = Carbon::now($timezone);

            $startDateString = $startDate->format('Y-m-d H:i:s');
            $endDateString = $endDate->format('Y-m-d H:i:s');

            if ($startDate->gte($endDate)) {
                $hostsData[$host_ip] = [
                    'status' => 'info',
                    'message' => "No new memory data to process for host $host_ip. Last collected at: $latestCreatedAt",
                    'last_collected_at' => $latestCreatedAt,
                ];
                continue;
            }

            $startUnix = $startDate->timestamp;
            $endUnix = $endDate->timestamp;
            $startTime = '-s ' . $startUnix;
            $endTime   = '-e ' . $endUnix;

            $mempoolRrdDirectory = rtrim($remoteRrdBaseDir, '/') . '/' . $host_ip;

            // --- Check remote directory existence ---
            $remoteDirListing = $sftp->nlist($mempoolRrdDirectory);
            if ($remoteDirListing === false) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "RRD directory for host $host_ip not found. Checked: '$mempoolRrdDirectory'"
                ];
                continue;
            }

            // --- Filter mempool RRD file ---
            $rrdFileName = 'mempool-hrstorage-system-65536.rrd';
            if (!in_array($rrdFileName, $remoteDirListing)) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "RRD file '$rrdFileName' not found for host $host_ip."
                ];
                continue;
            }

            $rrdFilePath = $mempoolRrdDirectory . '/' . $rrdFileName;

            // --- Fetch RRD Data via SSH ---
            $command = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime used free";
            $output = $sftp->exec($command);

            if (!$output || trim($output) === '') {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "Failed to fetch memory data for $host_ip",
                ];
                continue;
            }

            $parsedData = $this->parseRrdFetchOutput($output);

            if (empty($parsedData)) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "No memory data found for $host_ip",
                ];
                continue;
            }

            // --- 7. Process Summary ---
            $summary = $this->calculateMemorySummary($parsedData);

            $maxMemoryPercent = $summary['used_percent_summary']['max'];
            $maxTimestamp = $summary['max_percent_timestamp'];

            if ($maxMemoryPercent && $maxTimestamp) {
                NasRamUsage::on('pgsql')->create([
                    'activation_plan_id' => $activationPlanId,
                    'max_memory_load'    => $maxMemoryPercent,
                    'collected_at'       => Carbon::createFromTimestamp($maxTimestamp, $timezone)->format('Y-m-d H:i:s'),
                ]);
            }

            $hostsData[$host_ip] = [
                'status' => 'success',
                'metric_type' => 'Mempool Usage',
                'device_ip' => $host_ip,
                'rrd_file_used' => $rrdFilePath,
                'requested_range' => [
                    'start_datetime' => $startDateString,
                    'end_datetime'   => $endDateString,
                ],
                'mempool_summary' => [
                    'main_memory' => [
                        'percent' => [
                            'Min' => $summary['used_percent_summary']['min'] . '%',
                            'Max' => $summary['used_percent_summary']['max'] . '%',
                            'Cur' => $summary['used_percent_summary']['cur'] . '%',
                            'max_timestamp' => $maxTimestamp ? Carbon::createFromTimestamp($maxTimestamp, $timezone)->format('Y-m-d H:i:s') : null,
                            'max_timestamp_unix' => $maxTimestamp,
                        ],
                        'current_value' => $this->formatBytesForSiB($summary['used_bytes_cur_raw']),
                    ],
                    'Total' => $this->formatBytesForSiB($summary['total_bytes_raw']),
                    'raw_data' => [
                        'used_bytes_cur' => $summary['used_bytes_cur_raw'],
                        'total_bytes'    => $summary['total_bytes_raw'],
                    ]
                ],
                'time_series_raw' => $parsedData,
            ];
        }

        return response()->json([
            'status' => 'success',
            'hosts' => $hostsData,
        ]);
    }



    protected function calculateMemorySummary(array $parsedDataMain): array
    {
        if (empty($parsedDataMain)) {
            return [
                'used_percent_summary' => ['cur' => 0.0, 'min' => 0.0, 'max' => 0.0],
                'used_bytes_cur_raw' => 0.0,
                'total_bytes_raw' => 0.0,
                'max_percent_timestamp' => null,
            ];
        }

        $usedData = array_column($parsedDataMain, 'used');
        $freeData = array_column($parsedDataMain, 'free');

        $usedData = array_filter($usedData, fn($value) => is_numeric($value) && $value !== null);
        $freeData = array_filter($freeData, fn($value) => is_numeric($value) && $value !== null);

        $percentSeries = [];
        $totalBytes = 0;
        $maxPercent = 0.0;
        $maxPercentTimestamp = null;

        // Calculate Total and Percentage for each data point
        foreach ($parsedDataMain as $dataPoint) {
            $used = $dataPoint['used'] ?? 0;
            $free = $dataPoint['free'] ?? 0;
            $timestamp = $dataPoint['timestamp_unix'] ?? null;

            if (is_numeric($used) && is_numeric($free)) {
                $total = $used + $free;

                if ($total > 0) {
                    $totalBytes = $total;
                    $currentPercent = ($used / $total) * 100;
                    $percentSeries[] = $currentPercent;

                    // Track maximum percentage and its timestamp
                    if ($currentPercent > $maxPercent) {
                        $maxPercent = $currentPercent;
                        $maxPercentTimestamp = $timestamp;
                    }
                } else {
                    $percentSeries[] = 0.0;
                }
            } else {
                $percentSeries[] = null;
            }
        }

        $percentSeries = array_filter($percentSeries, fn($value) => is_numeric($value));

        // Filter the used data again to ensure it matches the size of the valid percent series
        $lastUsedBytes = !empty($usedData) ? end($usedData) : 0.0;

        // Summary calculations (Percent)
        $percentSummary = [
            'cur' => !empty($percentSeries) ? round(end($percentSeries), 2) : 0.0,
            'min' => !empty($percentSeries) ? round(min($percentSeries), 2) : 0.0,
            'max' => !empty($percentSeries) ? round(max($percentSeries), 2) : 0.0,
        ];

        // Return raw byte counts for accurate processing/display outside this function
        return [
            'used_percent_summary' => $percentSummary,
            'used_bytes_cur_raw' => (float)$lastUsedBytes,
            'total_bytes_raw' => (float)$totalBytes,
            'max_percent_timestamp' => $maxPercentTimestamp,
        ];
    }

    protected function formatBytesForSiB(float $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return "0.00B";
        }

        $base = 1024;
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
        $i = floor(log($bytes, $base));

        // Safety check to prevent array index overflow
        $i = min(count($units) - 1, $i);

        $value = $bytes / pow($base, $i);

        return round($value, $precision) . $units[$i];
    }


    // from 220

    // public function getSystemDiskStorageData(): JsonResponse
    // {
    //     $timezone = 'Asia/Dhaka'; // GMT+6
    //     $resolution = '-r 3600'; // 1-hour interval
    //     $rrdFileBaseDir = '/var/www/html/nttn-monitoring-application/partner-backend/storage/rrd/rrd/';

    //     // --- 1. Fetch NAS IPs ---
    //     $nasIps = DB::connection('pgsql')
    //         ->table('partner_activation_plans')
    //         ->pluck('nas_ip', 'id')
    //         ->filter()
    //         ->unique();

    //     if ($nasIps->isEmpty()) {
    //         return response()->json(['status' => 'error', 'message' => 'No NAS IPs found.'], 404);
    //     }

    //     $hostsData = [];

    //     foreach ($nasIps as $activationPlanId => $host_ip) {
    //         $host_ip = trim($host_ip);
    //         if (empty($host_ip)) continue;

    //         // --- 2. Get latest disk usage collected_at ---
    //         $latestCreatedAt = DB::connection('pgsql')
    //             ->table('nas_disk_usages as c')
    //             ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
    //             ->where('p.nas_ip', $host_ip)
    //             ->max('c.created_at');

    //         // --- 3. Set start and end times ---
    //         if ($latestCreatedAt) {
    //             $startDate = Carbon::parse($latestCreatedAt, $timezone)->addSecond();
    //         } else {
    //             $startDate = Carbon::now($timezone)->subMinutes(30); // last 30 minutes if no record
    //         }
    //         $endDate = Carbon::now($timezone);

    //         $startDateString = $startDate->format('Y-m-d H:i:s');
    //         $endDateString   = $endDate->format('Y-m-d H:i:s');

    //         if ($startDate->gte($endDate)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'info',
    //                 'message' => "No new disk data to process for host $host_ip. Last collected at: $latestCreatedAt",
    //                 'last_collected_at' => $latestCreatedAt,
    //             ];
    //             continue;
    //         }

    //         // --- 4. Convert to UNIX timestamps for RRDTool (UTC) ---
    //         $startUnix = $startDate->copy()->setTimezone('UTC')->timestamp;
    //         $endUnix   = $endDate->copy()->setTimezone('UTC')->timestamp;
    //         $startTime = '-s ' . $startUnix;
    //         $endTime   = '-e ' . $endUnix;

    //         // --- 5. Prepare RRD file ---
    //         $rrdFilePath = $rrdFileBaseDir . $host_ip . '/storage-hrstorage-system_disk.rrd';

    //         if (!is_file($rrdFilePath)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "RRD file not found for $host_ip",
    //             ];
    //             continue;
    //         }

    //         // --- 6. Fetch RRD Data ---
    //         $command = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime used free";
    //         $output = shell_exec($command);

    //         if (!$output || trim($output) === '') {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "Failed to fetch disk storage data for $host_ip",
    //             ];
    //             continue;
    //         }

    //         $parsedData = $this->parseRrdFetchOutput($output);

    //         if (empty($parsedData)) {
    //             $hostsData[$host_ip] = [
    //                 'status' => 'error',
    //                 'message' => "No disk storage data found for $host_ip",
    //             ];
    //             continue;
    //         }

    //         // --- 7. Calculate summary ---
    //         $summary = $this->calculateStorageSummary($parsedData);

    //         $diskSizeBytes = (int)$summary['total_size_raw'];
    //         $diskUsedBytes = (int)$summary['max_used_value'];
    //         $maxTimestamp  = $summary['max_used_timestamp'];

    //         // Insert into nas_disk_usages table
    //         if ($diskUsedBytes && $maxTimestamp) {
    //             NasDiskUsage::on('pgsql')->create([
    //                 'activation_plan_id' => $activationPlanId,
    //                 'disk_size'          => $diskSizeBytes,
    //                 'disk_used'          => $diskUsedBytes,
    //                 'collected_at'       => Carbon::createFromTimestamp($maxTimestamp, $timezone)->format('Y-m-d H:i:s'),
    //             ]);
    //         }

    //         // --- 8. Store host-wise results ---
    //         $hostsData[$host_ip] = [
    //             'status' => 'success',
    //             'metric_type' => 'System Disk Storage',
    //             'device_ip' => $host_ip,
    //             'rrd_file_used' => $rrdFilePath,
    //             'requested_range' => [
    //                 'start_datetime' => $startDateString,
    //                 'end_datetime'   => $endDateString,
    //             ],
    //             'storage_summary' => [
    //                 'disk_name' => 'system disk',
    //                 'Size' => $this->formatBytesForSiB($diskSizeBytes),
    //                 'Used' => [
    //                     'current_value' => $this->formatBytesForSiB($summary['used_raw']),
    //                     'max_value' => $this->formatBytesForSiB($diskUsedBytes),
    //                     'max_timestamp' => $maxTimestamp ? Carbon::createFromTimestamp($maxTimestamp, $timezone)->format('Y-m-d H:i:s') : null,
    //                     'max_timestamp_unix' => $maxTimestamp,
    //                 ],
    //                 'Percent_Used' => [
    //                     'current' => round($summary['percent_used_raw'], 2) . '%',
    //                     'max' => round($summary['max_percent_used'], 2) . '%',
    //                     'max_timestamp' => $maxTimestamp ? Carbon::createFromTimestamp($maxTimestamp, $timezone)->format('Y-m-d H:i:s') : null,
    //                 ],
    //                 'raw_data' => $summary,
    //             ],
    //             'time_series_raw' => $parsedData,
    //         ];
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'hosts' => $hostsData,
    //     ]);
    // }


    // librenms


    public function getSystemDiskStorageData(): JsonResponse
    {
        $remoteRrdBaseDir = '/opt/librenms/rrd/';
        $remoteHost = '172.17.18.82';
        $remotePort = 65122;
        $remoteUser = 'linkmon';
        $remotePass = '!TB5731roL#';
        $timezone = 'Asia/Dhaka'; // GMT+6
        $resolution = '-r 3600'; // 1-hour interval

        // --- 1. Fetch NAS IPs ---
        $nasIps = DB::connection('pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id')
            ->filter()
            ->unique();

        if ($nasIps->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'No NAS IPs found.'], 404);
        }

        // --- Initialize SFTP connection ---
        $sftp = new SFTP($remoteHost, $remotePort);
        if (!$sftp->login($remoteUser, $remotePass)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to remote RRD host via SFTP.'
            ], 500);
        }

        $hostsData = [];

        foreach ($nasIps as $activationPlanId => $host_ip) {
            $host_ip = trim($host_ip);
            if (empty($host_ip)) continue;

            // --- 2. Get latest disk usage collected_at ---
            $latestCreatedAt = DB::connection('pgsql')
                ->table('nas_disk_usages as c')
                ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
                ->where('p.nas_ip', $host_ip)
                ->max('c.created_at');

            // --- 3. Set start and end times ---
            if ($latestCreatedAt) {
                $startDate = Carbon::parse($latestCreatedAt, $timezone)->addSecond();
            } else {
                $startDate = Carbon::now($timezone)->subMinutes(30);
            }
            $endDate = Carbon::now($timezone);

            $startDateString = $startDate->format('Y-m-d H:i:s');
            $endDateString   = $endDate->format('Y-m-d H:i:s');

            if ($startDate->gte($endDate)) {
                $hostsData[$host_ip] = [
                    'status' => 'info',
                    'message' => "No new disk data to process for host $host_ip. Last collected at: $latestCreatedAt",
                    'last_collected_at' => $latestCreatedAt,
                ];
                continue;
            }

            $startUnix = $startDate->timestamp;
            $endUnix   = $endDate->timestamp;
            $startTime = '-s ' . $startUnix;
            $endTime   = '-e ' . $endUnix;

            $diskRrdDirectory = rtrim($remoteRrdBaseDir, '/') . '/' . $host_ip;

            // --- Check remote directory existence ---
            $remoteDirListing = $sftp->nlist($diskRrdDirectory);
            if ($remoteDirListing === false) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "RRD directory for host $host_ip not found. Checked: '$diskRrdDirectory'"
                ];
                continue;
            }

            // --- Filter disk RRD file ---
            $rrdFileName = 'storage-hrstorage-system_disk.rrd';
            if (!in_array($rrdFileName, $remoteDirListing)) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "RRD file '$rrdFileName' not found for host $host_ip."
                ];
                continue;
            }

            $rrdFilePath = $diskRrdDirectory . '/' . $rrdFileName;

            // --- Fetch RRD Data via SSH ---
            $command = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime used free";
            $output = $sftp->exec($command);

            if (!$output || trim($output) === '') {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "Failed to fetch disk storage data for $host_ip",
                ];
                continue;
            }

            $parsedData = $this->parseRrdFetchOutput($output);

            if (empty($parsedData)) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "No disk storage data found for $host_ip",
                ];
                continue;
            }

            // --- 7. Calculate summary ---
            $summary = $this->calculateStorageSummary($parsedData);

            $diskSizeBytes = (int)$summary['total_size_raw'];
            $diskUsedBytes = (int)$summary['max_used_value'];
            $maxTimestamp  = $summary['max_used_timestamp'];

            // Insert into nas_disk_usages table
            if ($diskUsedBytes && $maxTimestamp) {
                NasDiskUsage::on('pgsql')->create([
                    'activation_plan_id' => $activationPlanId,
                    'disk_size'          => $diskSizeBytes,
                    'disk_used'          => $diskUsedBytes,
                    'collected_at'       => Carbon::createFromTimestamp($maxTimestamp, $timezone)->format('Y-m-d H:i:s'),
                ]);
            }

            // --- 8. Store host-wise results ---
            $hostsData[$host_ip] = [
                'status' => 'success',
                'metric_type' => 'System Disk Storage',
                'device_ip' => $host_ip,
                'rrd_file_used' => $rrdFilePath,
                'requested_range' => [
                    'start_datetime' => $startDateString,
                    'end_datetime'   => $endDateString,
                ],
                'storage_summary' => [
                    'disk_name' => 'system disk',
                    'Size' => $this->formatBytesForSiB($diskSizeBytes),
                    'Used' => [
                        'current_value' => $this->formatBytesForSiB($summary['used_raw']),
                        'max_value' => $this->formatBytesForSiB($diskUsedBytes),
                        'max_timestamp' => $maxTimestamp ? Carbon::createFromTimestamp($maxTimestamp, $timezone)->format('Y-m-d H:i:s') : null,
                        'max_timestamp_unix' => $maxTimestamp,
                    ],
                    'Percent_Used' => [
                        'current' => round($summary['percent_used_raw'], 2) . '%',
                        'max' => round($summary['max_percent_used'], 2) . '%',
                        'max_timestamp' => $maxTimestamp ? Carbon::createFromTimestamp($maxTimestamp, $timezone)->format('Y-m-d H:i:s') : null,
                    ],
                    'raw_data' => $summary,
                ],
                'time_series_raw' => $parsedData,
            ];
        }

        return response()->json([
            'status' => 'success',
            'hosts' => $hostsData,
        ]);
    }



    protected function calculateStorageSummary(array $parsedDataMain): array
    {
        if (empty($parsedDataMain)) {
            return [
                'total_size_raw' => 0.0,
                'used_raw' => 0.0,
                'percent_used_raw' => 0.0,
                'max_used_timestamp' => null,
                'max_used_value' => 0.0,
                'max_percent_used' => 0.0
            ];
        }

        // Filter out NaN/NULL values to find valid data points
        $validDataPoints = array_filter($parsedDataMain, function($dataPoint) {
            return is_numeric($dataPoint['used']) && $dataPoint['used'] !== null &&
                is_numeric($dataPoint['free']) && $dataPoint['free'] !== null;
        });

        if (empty($validDataPoints)) {
            return [
                'total_size_raw' => 0.0,
                'used_raw' => 0.0,
                'percent_used_raw' => 0.0,
                'max_used_timestamp' => null,
                'max_used_value' => 0.0,
                'max_percent_used' => 0.0
            ];
        }

        // Get the LAST valid data point for current values
        $lastData = end($validDataPoints);
        $lastUsed = (float)$lastData['used'];
        $lastFree = (float)$lastData['free'];

        // Calculations for current values:
        $totalSize = $lastUsed + $lastFree;

        if ($totalSize > 0) {
            $percentUsed = ($lastUsed / $totalSize) * 100;
        } else {
            $percentUsed = 0.0;
        }

        // Find maximum usage and its timestamp
        $maxUsed = 0.0;
        $maxUsedTimestamp = null;
        $maxPercentUsed = 0.0;

        foreach ($validDataPoints as $dataPoint) {
            $currentUsed = (float)$dataPoint['used'];
            $currentFree = (float)$dataPoint['free'];
            $currentTotal = $currentUsed + $currentFree;

            if ($currentTotal > 0) {
                $currentPercent = ($currentUsed / $currentTotal) * 100;

                // Track maximum used bytes
                if ($currentUsed > $maxUsed) {
                    $maxUsed = $currentUsed;
                    $maxUsedTimestamp = $dataPoint['timestamp_unix'];
                    $maxPercentUsed = $currentPercent;
                }
            }
        }

        return [
            'total_size_raw' => $totalSize,
            'used_raw' => $lastUsed,
            'percent_used_raw' => $percentUsed,
            'max_used_timestamp' => $maxUsedTimestamp,
            'max_used_value' => $maxUsed,
            'max_percent_used' => $maxPercentUsed
        ];
    }


    // from 220

    // public function getIcmpPerformanceData(): JsonResponse
    // {
    //     // --- 1. Load NAS IPs ---
    //     $nasIps = DB::connection('pgsql')
    //         ->table('partner_activation_plans')
    //         ->pluck('nas_ip', 'id')
    //         ->filter()
    //         ->unique();

    //     if ($nasIps->isEmpty()) {
    //         return response()->json(['status' => 'error', 'message' => 'No NAS IPs found.'], 404);
    //     }

    //     $localTimezone = 'Asia/Dhaka';
    //     $utcTimezone   = 'UTC';
    //     $fullResolution = '-r 300';
    //     $hourResolution = '-r 3600';
    //     $dsnListFull = 'avg max min xmt rcv';
    //     $dsnListAvg  = 'avg';

    //     $results = [];

    //     foreach ($nasIps as $activationPlanId => $nasIp) {
    //         $nasIp = trim($nasIp);
    //         if (empty($nasIp)) continue;

    //         // --- 2. Get latest latency and timeout ---
    //         $latestLatency = DB::connection('pgsql')
    //             ->table('nas_icmp_latencies as c')
    //             ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
    //             ->where('p.nas_ip', $nasIp)
    //             ->max('c.created_at');

    //         $latestTimeout = DB::connection('pgsql')
    //             ->table('nas_icmp_timeouts as c')
    //             ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
    //             ->where('p.nas_ip', $nasIp)
    //             ->max('c.created_at');

    //         // --- 3. Determine start and end time ---
    //         $latestCreatedUnix = max(
    //             $latestLatency ? strtotime($latestLatency) : 0,
    //             $latestTimeout ? strtotime($latestTimeout) : 0
    //         );

    //         $startDate = $latestCreatedUnix > 0
    //             ? Carbon::createFromTimestamp($latestCreatedUnix + 1, $localTimezone)
    //             : Carbon::now($localTimezone)->subMinutes(30);

    //         $endDate = Carbon::now($localTimezone);

    //         if ($startDate->gte($endDate)) {
    //             $results[$nasIp] = [
    //                 'status' => 'info',
    //                 'message' => "No new ICMP data to process for host $nasIp",
    //                 'last_collected_at' => $latestCreatedUnix ? Carbon::createFromTimestamp($latestCreatedUnix, $localTimezone)->format('Y-m-d H:i:s') : null,
    //             ];
    //             continue;
    //         }

    //         // --- 4. Convert to UTC UNIX timestamps for RRD ---
    //         $startUnix = $startDate->copy()->setTimezone($utcTimezone)->timestamp;
    //         $endUnix   = $endDate->copy()->setTimezone($utcTimezone)->timestamp;
    //         $startTime = '-s ' . $startUnix;
    //         $endTime   = '-e ' . $endUnix;

    //         $rrdFilePath = "/var/www/html/nttn-monitoring-application/partner-backend/storage/rrd/rrd/{$nasIp}/icmp-perf.rrd";

    //         if (!is_file($rrdFilePath)) {
    //             $results[$nasIp] = [
    //                 'status' => 'error',
    //                 'message' => "RRD file not found for $nasIp",
    //             ];
    //             continue;
    //         }

    //         // --- 5. Fetch RRD Data ---
    //         $outputMain = shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $fullResolution $startTime $endTime $dsnListFull");
    //         if (!$outputMain || trim($outputMain) === '') continue;
    //         $parsedDataMain = $this->parseRrdFetchOutput($outputMain);

    //         $parsedDataHourAvg = $this->parseRrdFetchOutput(shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $hourResolution $startTime $endTime $dsnListAvg"));
    //         $parsedDataHourMin = $this->parseRrdFetchOutput(shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MIN $hourResolution $startTime $endTime $dsnListAvg"));
    //         $parsedDataHourMax = $this->parseRrdFetchOutput(shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MAX $hourResolution $startTime $endTime $dsnListAvg"));

    //         // --- 6. Calculate summary & outages ---
    //         $summary = $this->calculateComprehensiveSummary($parsedDataMain, $parsedDataHourAvg, $parsedDataHourMin, $parsedDataHourMax);
    //         $outageAnalysis = $this->analyzeOutageGaps($parsedDataMain, $endUnix, $localTimezone);

    //         // --- 7. Insert NasIcmpLatency only if value exists ---
    //         $maxRtt = $summary['rtt_loss_high_res']['rtt_ms'] ?? [];
    //         if (!empty($maxRtt['max']) && !empty($maxRtt['max_timestamp'])) {
    //             NasIcmpLatency::on('pgsql')->create([
    //                 'activation_plan_id' => $activationPlanId,
    //                 'collected_at' => Carbon::createFromTimestamp($maxRtt['max_timestamp'], $localTimezone)->format('Y-m-d H:i:s'),
    //                 'threshold_exceeded_value' => $maxRtt['max'],
    //             ]);
    //         }

    //         // --- 8. Insert NasIcmpTimeout only if keys exist ---
    //         if (!empty($outageAnalysis['outage_periods'])) {
    //             foreach ($outageAnalysis['outage_periods'] as $outage) {
    //                 if (!isset($outage['start_unix'], $outage['end_unix'], $outage['duration_seconds'])) {
    //                     continue; // skip invalid/outage with missing keys
    //                 }

    //                 NasIcmpTimeout::on('pgsql')->create([
    //                     'activation_plan_id' => $activationPlanId,
    //                     'timeout_start' => Carbon::createFromTimestamp($outage['start_unix'], $localTimezone)->format('Y-m-d H:i:s'),
    //                     'timeout_end'   => Carbon::createFromTimestamp($outage['end_unix'], $localTimezone)->format('Y-m-d H:i:s'),
    //                     'timeout_duration' => $outage['duration_seconds'],
    //                 ]);
    //             }
    //         }

    //         // --- 9. Store results ---
    //         $results[$nasIp] = [
    //             'activation_plan_id' => $activationPlanId,
    //             'icmp_summary' => $summary,
    //             'outage_analysis' => $outageAnalysis,
    //             'time_series_raw' => $parsedDataMain,
    //         ];
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'hosts' => $nasIps->values(),
    //         'data' => $results,
    //         'requested_range' => [
    //             'start_datetime' => $startDate->format('Y-m-d H:i:s'),
    //             'end_datetime' => $endDate->format('Y-m-d H:i:s'),
    //         ],
    //     ]);
    // }




    // librenms

    public function getIcmpPerformanceData(): JsonResponse
    {
        $remoteRrdBaseDir = '/opt/librenms/rrd/';
        $remoteHost = '172.17.18.82';
        $remotePort = 65122;
        $remoteUser = 'linkmon';
        $remotePass = '!TB5731roL#';
        $localTimezone = 'Asia/Dhaka';
        $utcTimezone   = 'UTC';
        $fullResolution = '-r 300';
        $hourResolution = '-r 3600';
        $dsnListFull = 'avg max min xmt rcv';
        $dsnListAvg  = 'avg';

        // --- 1. Load NAS IPs ---
        $nasIps = DB::connection('pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id')
            ->filter()
            ->unique();

        if ($nasIps->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'No NAS IPs found.'], 404);
        }

        // --- Initialize SFTP connection ---
        $sftp = new SFTP($remoteHost, $remotePort);
        if (!$sftp->login($remoteUser, $remotePass)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to remote RRD host via SFTP.'
            ], 500);
        }

        $results = [];

        foreach ($nasIps as $activationPlanId => $nasIp) {
            $nasIp = trim($nasIp);
            if (empty($nasIp)) continue;

            // --- 2. Get latest latency and timeout (UTC) ---
            $latestLatency = DB::connection('pgsql')
                ->table('nas_icmp_latencies as c')
                ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
                ->where('p.nas_ip', $nasIp)
                ->max('c.created_at');

            $latestTimeout = DB::connection('pgsql')
                ->table('nas_icmp_timeouts as c')
                ->join('partner_activation_plans as p', 'c.activation_plan_id', '=', 'p.id')
                ->where('p.nas_ip', $nasIp)
                ->max('c.created_at');

            $latestCreatedUnix = max(
                $latestLatency ? strtotime($latestLatency . ' UTC') : 0,
                $latestTimeout ? strtotime($latestTimeout . ' UTC') : 0
            );

            // --- Determine start and end times per host ---
            $startDate = $latestCreatedUnix > 0
                ? Carbon::createFromTimestamp($latestCreatedUnix + 1, $localTimezone)
                : Carbon::now($localTimezone)->subMinutes(30);

            $endDate = Carbon::now($localTimezone);

            if ($startDate->gte($endDate)) {
                $results[$nasIp] = [
                    'status' => 'info',
                    'message' => "No new ICMP data to process for host $nasIp",
                    'last_collected_at' => $latestCreatedUnix ? Carbon::createFromTimestamp($latestCreatedUnix, $localTimezone)->format('Y-m-d H:i:s') : null,
                    'requested_range' => [
                        'start_datetime' => $startDate->format('Y-m-d H:i:s'),
                        'end_datetime' => $endDate->format('Y-m-d H:i:s'),
                    ]
                ];
                continue;
            }

            // --- 3. Convert to UTC UNIX timestamps for RRD ---
            $startUnix = $startDate->copy()->setTimezone($utcTimezone)->timestamp;
            $endUnix   = $endDate->copy()->setTimezone($utcTimezone)->timestamp;
            $startTime = '-s ' . $startUnix;
            $endTime   = '-e ' . $endUnix;

            $rrdDirectory = rtrim($remoteRrdBaseDir, '/') . '/' . $nasIp;

            // --- Check remote directory existence ---
            $remoteDirListing = $sftp->nlist($rrdDirectory);
            if ($remoteDirListing === false || !in_array('icmp-perf.rrd', $remoteDirListing)) {
                $results[$nasIp] = [
                    'status' => 'error',
                    'message' => "RRD directory or file for host $nasIp not found. Checked: '$rrdDirectory/icmp-perf.rrd'",
                    'requested_range' => [
                        'start_datetime' => $startDate->format('Y-m-d H:i:s'),
                        'end_datetime' => $endDate->format('Y-m-d H:i:s'),
                    ]
                ];
                continue;
            }

            $rrdFilePath = $rrdDirectory . '/icmp-perf.rrd';

            // --- 4. Fetch RRD Data via SFTP ---
            $outputMain = $sftp->exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $fullResolution $startTime $endTime $dsnListFull");
            if (!$outputMain || trim($outputMain) === '') continue;
            $parsedDataMain = $this->parseRrdFetchOutput($outputMain);

            $parsedDataHourAvg = $this->parseRrdFetchOutput($sftp->exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $hourResolution $startTime $endTime $dsnListAvg"));
            $parsedDataHourMin = $this->parseRrdFetchOutput($sftp->exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MIN $hourResolution $startTime $endTime $dsnListAvg"));
            $parsedDataHourMax = $this->parseRrdFetchOutput($sftp->exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MAX $hourResolution $startTime $endTime $dsnListAvg"));

            // --- 5. Calculate summary & outages ---
            $summary = $this->calculateComprehensiveSummary($parsedDataMain, $parsedDataHourAvg, $parsedDataHourMin, $parsedDataHourMax);
            $outageAnalysis = $this->analyzeOutageGaps($parsedDataMain, $endUnix);

            // --- 6. Insert NasIcmpLatency if value exists ---
            $maxRtt = $summary['rtt_loss_high_res']['rtt_ms'] ?? [];
            if (!empty($maxRtt['max']) && !empty($maxRtt['max_timestamp'])) {
                NasIcmpLatency::on('pgsql')->create([
                    'activation_plan_id' => $activationPlanId,
                    'collected_at' => Carbon::createFromTimestamp($maxRtt['max_timestamp'], $localTimezone)->format('Y-m-d H:i:s'),
                    'threshold_exceeded_value' => $maxRtt['max'],
                ]);
            }

            // --- 7. Insert NasIcmpTimeouts ---
            if (!empty($outageAnalysis['outage_periods'])) {
                foreach ($outageAnalysis['outage_periods'] as $outage) {
                    NasIcmpTimeout::on('pgsql')->create([
                        'activation_plan_id' => $activationPlanId,
                        'timeout_start' => Carbon::createFromTimestamp($outage['start_time_unix'], $localTimezone)->format('Y-m-d H:i:s'),
                        'timeout_end'   => Carbon::createFromTimestamp($outage['end_time_unix'], $localTimezone)->format('Y-m-d H:i:s'),
                        'timeout_duration' => $outage['duration_seconds'],
                    ]);
                }
            }

            // --- 8. Store results per host ---
            $results[$nasIp] = [
                'activation_plan_id' => $activationPlanId,
                'icmp_summary' => $summary,
                'outage_analysis' => $outageAnalysis,
                'time_series_raw' => $parsedDataMain,
                'requested_range' => [
                    'start_datetime' => $startDate->format('Y-m-d H:i:s'),
                    'end_datetime' => $endDate->format('Y-m-d H:i:s'),
                ],
            ];
        }

        return response()->json([
            'status' => 'success',
            'hosts' => $nasIps->values(),
            'data' => $results,
        ]);
    }




    protected function calculateComprehensiveSummary(
        array $parsedDataMain,
        array $parsedDataHourAvg,
        array $parsedDataHourMin,
        array $parsedDataHourMax
    ): array
    {
        if (empty($parsedDataMain)) {
            return [];
        }


        $avgRttData = array_column($parsedDataMain, 'avg');
        $minRttData = array_column($parsedDataMain, 'min');
        $maxRttData = array_column($parsedDataMain, 'max');

        $xmtData    = array_column($parsedDataMain, 'xmt');
        $rcvData    = array_column($parsedDataMain, 'rcv');

        $avgRttData = array_filter($avgRttData, fn($value) => is_numeric($value) && $value !== null);
        $minRttData = array_filter($minRttData, fn($value) => is_numeric($value) && $value !== null);
        $maxRttData = array_filter($maxRttData, fn($value) => is_numeric($value) && $value !== null);


        $maxRttTimestamp = null;
        $minRttTimestamp = null;
        $maxRttValue = 0.0;
        $minRttValue = PHP_FLOAT_MAX;

        foreach ($parsedDataMain as $dataPoint) {
            $currentAvg = $dataPoint['avg'] ?? null;
            $timestamp = $dataPoint['timestamp_unix'] ?? null;

            if (is_numeric($currentAvg) && $timestamp) {

                if ($currentAvg > $maxRttValue) {
                    $maxRttValue = $currentAvg;
                    $maxRttTimestamp = $timestamp;
                }

                // Track minimum RTT
                if ($currentAvg < $minRttValue) {
                    $minRttValue = $currentAvg;
                    $minRttTimestamp = $timestamp;
                }
            }
        }


        $rttSummary = [
            'cur' => !empty($avgRttData) ? round(end($avgRttData), 2) : 0.0,
            'min' => !empty($minRttData) ? round(min($minRttData), 2) : 0.0,
            'max' => !empty($maxRttData) ? round(max($maxRttData), 2) : 0.0,
            'avg' => !empty($avgRttData) ? round(array_sum($avgRttData) / count($avgRttData), 2) : 0.0,

            'min_timestamp' => $minRttTimestamp ? date('Y-m-d H:i:s', $minRttTimestamp) : null,
            'min_timestamp_unix' => $minRttTimestamp,
            'max_timestamp' => $maxRttTimestamp ? date('Y-m-d H:i:s', $maxRttTimestamp) : null,
            'max_timestamp_unix' => $maxRttTimestamp,
        ];


        $lossSeries = [];
        $maxLossTimestamp = null;
        $maxLossValue = 0.0;

        foreach ($parsedDataMain as $dataPoint) {
            $xmt = $dataPoint['xmt'] ?? 0;
            $rcv = $dataPoint['rcv'] ?? 0;
            $timestamp = $dataPoint['timestamp_unix'] ?? null;

            if (is_numeric($xmt) && $xmt > 0 && is_numeric($rcv) && $timestamp) {
                $currentLoss = round((($xmt - $rcv) / $xmt) * 100, 2);
                $lossSeries[] = $currentLoss;


                if ($currentLoss > $maxLossValue) {
                    $maxLossValue = $currentLoss;
                    $maxLossTimestamp = $timestamp;
                }
            } else {
                $lossSeries[] = 0.0;
            }
        }
        $lossSeries = array_filter($lossSeries, fn($value) => is_numeric($value));
        $lossSummary = [
            'cur' => !empty($lossSeries) ? round(end($lossSeries), 2) : 0.0,
            'min' => !empty($lossSeries) ? round(min($lossSeries), 2) : 0.0,
            'max' => !empty($lossSeries) ? round(max($lossSeries), 2) : 0.0,
            'avg' => !empty($lossSeries) ? round(array_sum($lossSeries) / count($lossSeries), 2) : 0.0,

            'max_timestamp' => $maxLossTimestamp ? date('Y-m-d H:i:s', $maxLossTimestamp) : null,
            'max_timestamp_unix' => $maxLossTimestamp,
        ];


        $rttTableSummary = [
            'Now' => !empty($avgRttData) ? round(end($avgRttData), 2) : 0.0,
            'Min' => !empty($avgRttData) ? round(min($avgRttData), 2) : 0.0,
            'Max' => !empty($avgRttData) ? round(max($avgRttData), 2) : 0.0,
            'Avg' => !empty($avgRttData) ? round(array_sum($avgRttData) / count($avgRttData), 2) : 0.0,

            'min_timestamp' => $minRttTimestamp ? date('Y-m-d H:i:s', $minRttTimestamp) : null,
            'max_timestamp' => $maxRttTimestamp ? date('Y-m-d H:i:s', $maxRttTimestamp) : null,
        ];


        $hourAvgData = array_filter(array_column($parsedDataHourAvg, 'avg'), fn($v) => is_numeric($v) && $v !== null);
        $hourMinData = array_filter(array_column($parsedDataHourMin, 'avg'), fn($v) => is_numeric($v) && $v !== null);
        $hourMaxData = array_filter(array_column($parsedDataHourMax, 'avg'), fn($v) => is_numeric($v) && $v !== null);

        $hourAggregatedSummary = [
            '1_hour_avg' => $this->getAggregatedSummary($hourAvgData, $parsedDataHourAvg),
            '1_hour_min' => $this->getAggregatedSummary($hourMinData, $parsedDataHourMin),
            '1_hour_max' => $this->getAggregatedSummary($hourMaxData, $parsedDataHourMax),
        ];


        $sortedCoreRttData = $avgRttData;
        sort($sortedCoreRttData);

        $percentileSummary = [
            '25th_Percentile' => round($this->calculatePercentile($sortedCoreRttData, 25), 6),
            '50th_Percentile' => round($this->calculatePercentile($sortedCoreRttData, 50), 6),
            '75th_Percentile' => round($this->calculatePercentile($sortedCoreRttData, 75), 6),
        ];


        return [
            'rtt_loss_high_res' => ['rtt_ms' => $rttSummary, 'loss_percent' => $lossSummary],
            'ping_response_table' => [
                'Milliseconds_avg' => $rttTableSummary,
                'Aggregated_Hours' => $hourAggregatedSummary,
                'Percentiles' => $percentileSummary,
            ],
        ];
    }


    protected function getAggregatedSummary(array $data, array $parsedData = []): array
    {
        $summary = [
            'Now' => !empty($data) ? round(end($data), 2) : 0.0,
            'Min' => !empty($data) ? round(min($data), 2) : 0.0,
            'Max' => !empty($data) ? round(max($data), 2) : 0.0,
            'Avg' => !empty($data) ? round(array_sum($data) / count($data), 2) : 0.0,
        ];

        // Add timestamps if parsed data is provided
        if (!empty($parsedData)) {
            $maxValue = !empty($data) ? max($data) : 0.0;
            $minValue = !empty($data) ? min($data) : 0.0;

            $maxTimestamp = null;
            $minTimestamp = null;

            foreach ($parsedData as $dataPoint) {
                $currentValue = $dataPoint['avg'] ?? null;
                $timestamp = $dataPoint['timestamp_unix'] ?? null;

                if (is_numeric($currentValue) && $timestamp) {
                    if ($currentValue == $maxValue) {
                        $maxTimestamp = $timestamp;
                    }
                    if ($currentValue == $minValue) {
                        $minTimestamp = $timestamp;
                    }
                }
            }

            if ($maxTimestamp) {
                $summary['max_timestamp'] = date('Y-m-d H:i:s', $maxTimestamp);
                $summary['max_timestamp_unix'] = $maxTimestamp;
            }

            if ($minTimestamp) {
                $summary['min_timestamp'] = date('Y-m-d H:i:s', $minTimestamp);
                $summary['min_timestamp_unix'] = $minTimestamp;
            }
        }

        return $summary;
    }


    protected function calculatePercentile(array $data, float $percentile): float
    {
        if (empty($data)) {
            return 0.0;
        }
        $count = count($data);
        $index = ($percentile / 100) * $count;

        if (floor($index) == $index) {
            // Exact index: average the value at index and index - 1 (1-based index)
            return ($data[(int)$index - 1] + $data[(int)$index]) / 2;
        } else {
            // Interpolated index: use the value at the next higher index
            return $data[(int)floor($index)];
        }
    }


    protected function formatDuration(int $startUnix, int $endUnix): string
    {
        $duration = $endUnix - $startUnix;
        if ($duration <= 0) {
            return "0s";
        }

        $days = floor($duration / 86400);
        $hours = floor(($duration % 86400) / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        // Only include seconds if no larger unit is present, or if it's the only unit
        if ($seconds > 0 || empty($parts)) $parts[] = "{$seconds}s";

        return implode(' ', $parts);
    }

    protected function analyzeOutageGaps(array $parsedDataMain, int $endUnix): array
    {
        $outages = [];
        $isOutage = false;
        $outageStartUnix = 0;

        $originalTimezone = date_default_timezone_get();

        date_default_timezone_set('Asia/Dhaka');

        // The resolution is 300 seconds (5 minutes)

        foreach ($parsedDataMain as $index => $dataPoint) {
            $timestamp = $dataPoint['timestamp_unix'];
            $rttValue = $dataPoint['avg'];


            $isCurrentNull = !is_numeric($rttValue) || $rttValue === null;

            if ($isCurrentNull && !$isOutage) {

                $isOutage = true;
                $outageStartUnix = $timestamp;

            } elseif (!$isCurrentNull && $isOutage) {

                $outageEndUnix = $timestamp;
                $duration = $outageEndUnix - $outageStartUnix;

                if ($duration > 0) {
                    $outages[] = [
                        'start_time_unix' => $outageStartUnix,
                        'end_time_unix' => $outageEndUnix,
                        // FIX: These date() calls now use the 'Asia/Dhaka' timezone
                        'start_time_formatted' => date('Y-m-d H:i:s', $outageStartUnix),
                        'end_time_formatted' => date('Y-m-d H:i:s', $outageEndUnix),
                        'duration_seconds' => $duration,
                        'duration_formatted' => $this->formatDuration($outageStartUnix, $outageEndUnix),
                    ];
                }

                $isOutage = false;
            }
        }


        if ($isOutage) {
            $outageEndUnix = $endUnix;
            $duration = $outageEndUnix - $outageStartUnix;

            if ($duration > 0) {
                $outages[] = [
                    'start_time_unix' => $outageStartUnix,
                    'end_time_unix' => $outageEndUnix,
                    // FIX: These date() calls now use the 'Asia/Dhaka' timezone
                    'start_time_formatted' => date('Y-m-d H:i:s', $outageStartUnix),
                    'end_time_formatted' => date('Y-m-d H:i:s', $outageEndUnix),
                    'duration_seconds' => $duration,
                    'duration_formatted' => $this->formatDuration($outageStartUnix, $outageEndUnix),
                ];
            }
        }


        date_default_timezone_set($originalTimezone);

        return [
            'total_outage_count' => count($outages),
            'outage_periods' => $outages,
        ];
    }
}
