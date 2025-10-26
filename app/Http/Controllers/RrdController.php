<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Illuminate\Http\JsonResponse;
use phpseclib3\Net\SSH2;
use Illuminate\Support\Facades\DB;
use DateTime;
use DateTimeZone;
use App\Models\NasCpuUsage;
use App\Models\NasRamUsage;
use App\Models\NasDiskUsage;
use App\Models\NasIcmpLatency;
use App\Models\NasIcmpTimeout;
use Carbon\Carbon;

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


//    public function getPortData(string $startDateString = null, string $endDateString = null)
//    {
//
//        // $startDateString = $startDateString ?? '2025-10-01 01:05:00';
//        // $endDateString   = $endDateString ?? '2025-10-02 01:05:00';
//        $startDateString = $startDateString ?? '2025-10-01 01:05:00';
//        $endDateString = $endDateString ?? '2025-10-02 01:05:00';
//        // $startDateString = '2025-10-01 01:05:00';
//        // $endDateString   = '2025-10-02 01:05:00';
//
//        $rrdFilePath = '/var/www/html/backend_core_automation/storage/rrd/rrd/172.24.6.16/port-id2664.rrd';
//
//        // /opt/librenms/rrd/
//
//        $filename = basename($rrdFilePath);
//        $port_id = (int)preg_replace('/[^0-9]/', '', $filename);
//
//        $path_parts = explode('/', $rrdFilePath);
//        $host_ip = $path_parts[count($path_parts) - 2];
//
//        if (filter_var($host_ip, FILTER_VALIDATE_IP) === false) {
//            $host_ip = 'Host IP not found';
//        }
//
//        $resolution = '-r 300';
//
//
//        $startUnix = strtotime($startDateString);
//        $endUnix = strtotime($endDateString);
//
//        if ($startUnix === false || $endUnix === false || $startUnix >= $endUnix) {
//            return response()->json([
//                'status' => 'error',
//                'message' => 'Invalid datetime format or range provided. Start time must be before end time.'
//            ], 400);
//        }
//
//        $startTime = '-s ' . $startUnix;
//        $endTime = '-e ' . $endUnix;
//
//        $commandAvg = self::RRDTOOL_EXECUTABLE
//            . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime";
//
//
//        $commandMax = self::RRDTOOL_EXECUTABLE
//            . " fetch \"$rrdFilePath\" MAX $resolution $startTime $endTime";
//
//        $dataOutputAvg = shell_exec($commandAvg);
//        $dataOutputMax = shell_exec($commandMax);
//
//        if ($dataOutputAvg === null || trim($dataOutputAvg) === '') {
//            return response()->json([
//                'status' => 'error',
//                'message' => 'Failed to execute rrdtool or no data returned.'
//            ], 500);
//        }
//
//        // --- Data Parsing and Summary ---
//        $parsedDataAvg = $this->parseRrdFetchOutput($dataOutputAvg);
//        $parsedDataMax = $this->parseRrdFetchOutput($dataOutputMax);
//
//        $trafficSummary = $this->getTrafficSummary($parsedDataAvg, $parsedDataMax);
//
//
//        return response()->json([
//            'status' => 'success',
//            'host_ip' => $host_ip,
//            'port_id' => $port_id,
//            'data_source' => $rrdFilePath,
//            'traffic_summary' => $trafficSummary,
//            'results' => $parsedDataAvg,
//            'requested_range' => [
//                'start_unix' => $startUnix,
//                'end_unix' => $endUnix
//            ]
//        ]);
//    }

    public function getPortData(string $startDateString = null, string $endDateString = null)
    {
        $startDateString = $startDateString ?? '2025-10-01 01:05:00';
        $endDateString   = $endDateString ?? '2025-10-02 01:05:00';

        $rrdFilePath = '/var/www/html/backend_core_automation/storage/rrd/rrd/172.24.6.16/port-id2664.rrd';

        $filename = basename($rrdFilePath);
        $port_id = (int)preg_replace('/[^0-9]/', '', $filename);

        $path_parts = explode('/', $rrdFilePath);
        $host_ip = $path_parts[count($path_parts) - 2];

        if (filter_var($host_ip, FILTER_VALIDATE_IP) === false) {
            $host_ip = 'Host IP not found';
        }

        $resolution = '-r 300';

        $startUnix = strtotime($startDateString);
        $endUnix = strtotime($endDateString);

        if ($startUnix === false || $endUnix === false || $startUnix >= $endUnix) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid datetime format or range provided. Start time must be before end time.'
            ], 400);
        }

        $startTime = '-s ' . $startUnix;
        $endTime = '-e ' . $endUnix;

        $commandAvg = self::RRDTOOL_EXECUTABLE
            . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime";

        $commandMax = self::RRDTOOL_EXECUTABLE
            . " fetch \"$rrdFilePath\" MAX $resolution $startTime $endTime";

        $dataOutputAvg = shell_exec($commandAvg);
        $dataOutputMax = shell_exec($commandMax);

        if ($dataOutputAvg === null || trim($dataOutputAvg) === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to execute rrdtool or no data returned.'
            ], 500);
        }

        // --- Data Parsing and Summary ---
        $parsedDataAvg = $this->parseRrdFetchOutput($dataOutputAvg);
        $parsedDataMax = $this->parseRrdFetchOutput($dataOutputMax);

        $trafficSummary = $this->getTrafficSummary($parsedDataAvg, $parsedDataMax);

        // ✅ Extract required values from summary
        $maxDownloadMbps = $trafficSummary['max_rate']['in_mbps'] ?? 0;
        $maxUploadMbps   = $trafficSummary['max_rate']['out_mbps'] ?? 0;
        $maxDownloadTime = $trafficSummary['max_rate']['in_peak_time'] ?? null;
        $maxUploadTime   = $trafficSummary['max_rate']['out_peak_time'] ?? null;

        // ✅ Insert or Update into nas_interface_utilization
        try {
            \App\Models\NasInterfaceUtilization::updateOrCreate(
                [
                    'activation_plan_id' => $port_id, // if this actually maps to activation_plan_id, adjust if needed
                    'interface_port' => $port_id
                ],
                [
                    'max_download_mbps' => $maxDownloadMbps,
                    'max_upload_mbps' => $maxUploadMbps,
                    'max_download_collected_at' => $maxDownloadTime,
                    'max_upload_collected_at' => $maxUploadTime,
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to insert data into nas_interface_utilization.',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'host_ip' => $host_ip,
            'port_id' => $port_id,
            'data_source' => $rrdFilePath,
            'traffic_summary' => $trafficSummary,
            'results' => $parsedDataAvg,
            'requested_range' => [
                'start_unix' => $startUnix,
                'end_unix' => $endUnix
            ]
        ]);
    }


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



    // public function getDeviceCpuData(string $startDateString = null, string $endDateString = null): JsonResponse
    // {



    //     $startDateString = $startDateString ?? '2025-10-01 01:05:00';
    //     $endDateString = $endDateString ?? '2025-10-02 01:05:00';


    //     $rrdFileBaseDir = '/var/www/html/backend_core_automation/storage/rrd/rrd/';

    //     $host_ip = '172.24.6.16';
    //     $cpuRrdDirectory = $rrdFileBaseDir . $host_ip;


    //     $startUnix = strtotime($startDateString);
    //     $endUnix = strtotime($endDateString);

    //     if ($startUnix === false || $endUnix === false || $startUnix >= $endUnix) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Invalid datetime format or range provided.'
    //         ], 400);
    //     }

    //     $startTime = '-s ' . $startUnix;
    //     $endTime = '-e ' . $endUnix;
    //     $resolution = '-r 300';


    //     $allCpuFiles = glob($cpuRrdDirectory . '/processor-hr-*.rrd');
    //     $numProcessors = count($allCpuFiles);
    //     $allCpuData = [];

    //     if ($numProcessors === 0) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => "No processor RRD files found for host $host_ip in $cpuRrdDirectory."
    //         ], 404);
    //     }

    //     foreach ($allCpuFiles as $cpuFilePath) {
    //         $command = self::RRDTOOL_EXECUTABLE
    //                   . " fetch \"$cpuFilePath\" AVERAGE $resolution $startTime $endTime";

    //         $output = shell_exec($command);

    //         if (!empty($output)) {
    //             $parsedData = $this->parseRrdFetchOutput($output);

    //             foreach ($parsedData as $row) {
    //                 $timestamp = $row['timestamp_unix'];

    //                 $usage = $row['usage'] ?? 0.0;

    //                 if (!isset($allCpuData[$timestamp])) {
    //                     $allCpuData[$timestamp] = [
    //                         'timestamp_unix' => $timestamp,
    //                         'timestamp_formatted' => $row['timestamp_formatted'] ?? date('Y-m-d H:i:s', $timestamp),
    //                         'total_usage' => 0.0,
    //                         'count' => 0,
    //                     ];
    //                 }

    //                 if (is_numeric($usage) && $usage !== null) {
    //                     $allCpuData[$timestamp]['total_usage'] += (float)$usage;
    //                     $allCpuData[$timestamp]['count']++;
    //                 }
    //             }
    //         }
    //     }


    //     $aggregatedCpuResults = [];
    //     $maxLoadPeak = 0.0;
    //     $maxLoadTime = null;

    //     foreach ($allCpuData as $data) {
    //         if ($data['count'] > 0) {

    //             $averageCpuPercent = round($data['total_usage'] / $data['count'], 2);

    //             $aggregatedCpuResults[] = [
    //                 'timestamp_unix' => $data['timestamp_unix'],
    //                 'timestamp_formatted' => $data['timestamp_formatted'],
    //                 'average_cpu_percent' => $averageCpuPercent,
    //             ];


    //             if ($averageCpuPercent > $maxLoadPeak) {
    //                 $maxLoadPeak = $averageCpuPercent;
    //                 $maxLoadTime = $data['timestamp_formatted'];
    //             }
    //         }
    //     }


    //     $allAverages = array_column($aggregatedCpuResults, 'average_cpu_percent');

    //     $summary = [
    //         'processor_count' => $numProcessors,
    //         'average_load_overall' => !empty($allAverages) ? round(array_sum($allAverages) / count($allAverages), 2) : 0.0,
    //         'max_load_peak' => $maxLoadPeak,
    //         'max_load_peak_time' => $maxLoadTime,
    //     ];

    //     return response()->json([
    //         'status' => 'success',
    //         'host_ip' => $host_ip,
    //         'metric_type' => 'Aggregated_CPU_Usage',
    //         'requested_range' => [
    //             'start_datetime' => $startDateString,
    //             'end_datetime' => $endDateString,
    //         ],
    //         'cpu_summary' => $summary,
    //         'results' => $aggregatedCpuResults,
    //     ]);
    // }




    public function getDeviceCpuData(string $startDateString = null, string $endDateString = null): JsonResponse
    {

        // $endDate = Carbon::now();
        // $startDate = $endDate->copy()->subMinutes(15);

        // $startDateString = $startDateString ?? $startDate->format('Y-m-d H:i:s');
        // $endDateString = $endDateString ?? $endDate->format('Y-m-d H:i:s');


        $startDateString = $startDateString ?? '2025-10-01 01:05:00';
        $endDateString = $endDateString ?? '2025-10-02 01:05:00';

        $rrdFileBaseDir = '/var/www/html/backend_core_automation/storage/rrd/rrd/';

        $startUnix = strtotime($startDateString);
        $endUnix = strtotime($endDateString);

        if ($startUnix === false || $endUnix === false || $startUnix >= $endUnix) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid datetime format or range provided.'
            ], 400);
        }

        $startTime = '-s ' . $startUnix;
        $endTime = '-e ' . $endUnix;
        $resolution = '-r 300';

        // Fetch NAS IPs dynamically from remote PostgreSQL
        $nasIps = DB::connection('remote_pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id') // key = activation_plan_id
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
            $cpuRrdDirectory = $rrdFileBaseDir . $host_ip;

            if (!is_dir($cpuRrdDirectory)) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "RRD directory for host $host_ip not found."
                ];
                continue;
            }

            $allCpuFiles = glob($cpuRrdDirectory . '/processor-hr-*.rrd');
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
                $output = shell_exec($command);

                if (!empty($output)) {
                    $parsedData = $this->parseRrdFetchOutput($output);

                    foreach ($parsedData as $row) {
                        $timestamp = $row['timestamp_unix'];
                        $usage = $row['usage'] ?? 0.0;

                        if (!isset($allCpuData[$timestamp])) {
                            $allCpuData[$timestamp] = [
                                'timestamp_unix' => $timestamp,
                                'timestamp_formatted' => $row['timestamp_formatted'] ?? date('Y-m-d H:i:s', $timestamp),
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

            // Aggregate per host
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

            // Insert or update in nas_cpu_usages table on remote PostgreSQL
            if ($maxLoadPeak > 0 && $maxLoadTime) {

                NasCpuUsage::on('remote_pgsql')->create([
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






// public function getDeviceCpuData(string $startDateString = null, string $endDateString = null): JsonResponse
// {
//     $ssh = new SSH2('172.17.18.82', 65122);

//     if (!$ssh->login('itbilladmin', 'hsr$8-2v%Qse-e@bS!g')) {
//         return response()->json([
//             'status' => false,
//             'message' => 'SSH login failed'
//         ], 500);
//     }

//     // Get all nas_ip from remote PostgreSQL database
//     $nasIps = DB::connection('remote_pgsql')
//                 ->table('partner_activation_plans')
//                 ->select('nas_ip')
//                 ->get()
//                 ->pluck('nas_ip')
//                 ->filter() // Remove null/empty values
//                 ->unique() // Remove duplicates
//                 ->values();

//     if ($nasIps->isEmpty()) {
//         return response()->json([
//             'status' => 'error',
//             'message' => 'No nas_ip found in database'
//         ], 404);
//     }

//     // Debug: Check what NAS IPs we got
//     \Log::info('NAS IPs from database:', $nasIps->toArray());

//     $startDateString = $startDateString ?? '2025-10-01 01:05:00';
//     $endDateString = $endDateString ?? '2025-10-02 01:05:00';

//     $rrdFileBaseDir = '/opt/librenms/rrd/';

//     $startUnix = strtotime($startDateString);
//     $endUnix = strtotime($endDateString);

//     if ($startUnix === false || $endUnix === false || $startUnix >= $endUnix) {
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Invalid datetime format or range provided.'
//         ], 400);
//     }

//     $startTime = '-s ' . $startUnix;
//     $endTime = '-e ' . $endUnix;
//     $resolution = '-r 300';

//     $allHostsData = [];

//     foreach ($nasIps as $host_ip) {
//         try {
//             \Log::info("Processing NAS IP: " . $host_ip);

//             // Check if folder exists on remote server with better error handling
//             $output = $ssh->exec('ls -lh /opt/librenms/rrd/' . $host_ip . ' 2>&1');

//             \Log::info("SSH output for {$host_ip}: " . $output);

//             // If folder doesn't exist, skip this nas_ip
//             if (str_contains($output, 'No such file or directory') ||
//                 str_contains($output, 'cannot access') ||
//                 str_contains($output, 'not found') ||
//                 empty(trim($output))) {

//                 \Log::warning("RRD directory not found for host: " . $host_ip);
//                 $allHostsData[$host_ip] = [
//                     'status' => 'error',
//                     'message' => 'RRD directory not found for this host',
//                     'ssh_output' => trim($output) // Include SSH output for debugging
//                 ];
//                 continue;
//             }

//             $cpuRrdDirectory = $rrdFileBaseDir . $host_ip;

//             \Log::info("Looking for RRD files in: " . $cpuRrdDirectory);

//             $allCpuFiles = glob($cpuRrdDirectory . '/processor-hr-*.rrd');
//             $numProcessors = count($allCpuFiles);
//             $allCpuData = [];

//             \Log::info("Found {$numProcessors} processor files for {$host_ip}");

//             if ($numProcessors === 0) {
//                 $allHostsData[$host_ip] = [
//                     'status' => 'error',
//                     'message' => "No processor RRD files found for host $host_ip",
//                     'directory_checked' => $cpuRrdDirectory
//                 ];
//                 continue;
//             }

//             foreach ($allCpuFiles as $cpuFilePath) {
//                 $command = self::RRDTOOL_EXECUTABLE
//                           . " fetch \"$cpuFilePath\" AVERAGE $resolution $startTime $endTime";

//                 \Log::info("Executing command: " . $command);

//                 $output = shell_exec($command);

//                 if (!empty($output)) {
//                     $parsedData = $this->parseRrdFetchOutput($output);

//                     foreach ($parsedData as $row) {
//                         $timestamp = $row['timestamp_unix'];

//                         $usage = $row['usage'] ?? 0.0;

//                         if (!isset($allCpuData[$timestamp])) {
//                             $allCpuData[$timestamp] = [
//                                 'timestamp_unix' => $timestamp,
//                                 'timestamp_formatted' => $row['timestamp_formatted'] ?? date('Y-m-d H:i:s', $timestamp),
//                                 'total_usage' => 0.0,
//                                 'count' => 0,
//                             ];
//                         }

//                         if (is_numeric($usage) && $usage !== null) {
//                             $allCpuData[$timestamp]['total_usage'] += (float)$usage;
//                             $allCpuData[$timestamp]['count']++;
//                         }
//                     }
//                 }
//             }

//             $aggregatedCpuResults = [];
//             $maxLoadPeak = 0.0;
//             $maxLoadTime = null;

//             foreach ($allCpuData as $data) {
//                 if ($data['count'] > 0) {
//                     $averageCpuPercent = round($data['total_usage'] / $data['count'], 2);

//                     $aggregatedCpuResults[] = [
//                         'timestamp_unix' => $data['timestamp_unix'],
//                         'timestamp_formatted' => $data['timestamp_formatted'],
//                         'average_cpu_percent' => $averageCpuPercent,
//                     ];

//                     if ($averageCpuPercent > $maxLoadPeak) {
//                         $maxLoadPeak = $averageCpuPercent;
//                         $maxLoadTime = $data['timestamp_formatted'];
//                     }
//                 }
//             }

//             $allAverages = array_column($aggregatedCpuResults, 'average_cpu_percent');

//             $summary = [
//                 'processor_count' => $numProcessors,
//                 'average_load_overall' => !empty($allAverages) ? round(array_sum($allAverages) / count($allAverages), 2) : 0.0,
//                 'max_load_peak' => $maxLoadPeak,
//                 'max_load_peak_time' => $maxLoadTime,
//             ];

//             // Store results for this host in the original format
//             $allHostsData[$host_ip] = [
//                 'status' => 'success',
//                 'host_ip' => $host_ip,
//                 'metric_type' => 'Aggregated_CPU_Usage',
//                 'requested_range' => [
//                     'start_datetime' => $startDateString,
//                     'end_datetime' => $endDateString,
//                 ],
//                 'cpu_summary' => $summary,
//                 'results' => $aggregatedCpuResults,
//             ];

//             \Log::info("Successfully processed NAS IP: " . $host_ip);

//         } catch (\Exception $e) {
//             \Log::error("Error processing NAS IP {$host_ip}: " . $e->getMessage());
//             \Log::error("File: " . $e->getFile() . " Line: " . $e->getLine());

//             $allHostsData[$host_ip] = [
//                 'status' => 'error',
//                 'message' => 'Error processing this host: ' . $e->getMessage(),
//                 'error_line' => $e->getLine()
//             ];
//             continue;
//         }
//     }

//     // Return all hosts data
//     return response()->json([
//         'status' => 'success',
//         'total_hosts_processed' => count($allHostsData),
//         'hosts_data' => $allHostsData,
//     ]);
// }



    // public function getMempoolPerformanceData(string $startDateString = null, string $endDateString = null): JsonResponse
    // {
    //     // --- 1. Configuration & Path Setup ---
    //     // Note: The RRD file path is based on the provided RRDtool command.
    //     $rrdFileBaseDir = '/var/www/html/backend_core_automation/storage/rrd/rrd/172.24.6.16/';
    //     $rrdFileName = 'mempool-hrstorage-system-65536.rrd';
    //     $defaultHostIp = '172.24.6.16';
    //     $rrdFilePath = $rrdFileBaseDir . $rrdFileName;

    //     // DSNs required: used and free for total/percent calculation
    //     $dsnList = 'used free';
    //     $resolution = '-r 300'; // Standard 5-minute resolution (default for this type of graph)

    //     // --- 2. Time Range Calculation (Using requested range from RRD image, converted to UTC) ---
    //     // Example range from the image: 2025-10-01 02:05 to 2025-10-02 01:05 (Adjusted for a typical 24h view)
    //     $startDateString = $startDateString ?? '2025-10-01 01:05:00';
    //     $endDateString   = $endDateString ?? '2025-10-02 01:05:00';

    //     // LibreNMS typically runs on UTC, but the user's input might be local (GMT+6)
    //     // We will assume the input strings are intended as GMT+6, but the timestamps used by RRDTool must be UTC.
    //     $localTimezone = new DateTimeZone('Asia/Dhaka');
    //     $utcTimezone = new DateTimeZone('UTC');

    //     try {
    //         $startDateTime = new DateTime($startDateString, $localTimezone);
    //         $endDateTime   = new DateTime($endDateString, $localTimezone);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'error', 'message' => 'Invalid datetime format.'], 400);
    //     }

    //     // Convert to UTC/GMT+0 for RRDtool
    //     $startDateTime->setTimezone($utcTimezone);
    //     $endDateTime->setTimezone($utcTimezone);

    //     $startUnix = $startDateTime->getTimestamp();
    //     $endUnix = $endDateTime->getTimestamp();


    //     if ($startUnix === false || $endUnix === false || $startUnix >= $endUnix) {
    //         return response()->json(['status' => 'error', 'message' => 'Invalid datetime range.'], 400);
    //     }

    //     $startTime = '-s ' . $startUnix;
    //     $endTime = '-e ' . $endUnix;

    //     // --- 3. RRDtool Command Execution (Fetch used and free memory) ---
    //     $command = self::RRDTOOL_EXECUTABLE
    //                     . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime $dsnList";

    //     $output = shell_exec($command);

    //     if ($output === null || trim($output) === '') {
    //         return response()->json(['status' => 'error', 'message' => "Failed to fetch memory data."], 500);
    //     }

    //     // Assuming this method converts the RRD fetch output to an array:
    //     // [['timestamp_unix' => 1700000000, 'used' => 1000, 'free' => 9000], ...]
    //     $parsedDataMain = $this->parseRrdFetchOutput($output);

    //     // --- 4. Data Processing and Summary Calculation ---
    //     $summary = $this->calculateMemorySummary($parsedDataMain);


    //     $formattedUsed = $this->formatBytesForSiB($summary['used_bytes_cur_raw']);
    //     $formattedTotal = $this->formatBytesForSiB($summary['total_bytes_raw']);

    //     // Format the percent summary to match the structure in the image (Min, Max, Cur)
    //     $percentOutput = [
    //         'Min' => $summary['used_percent_summary']['min'] . '%',
    //         'Max' => $summary['used_percent_summary']['max'] . '%',
    //         'Cur' => $summary['used_percent_summary']['cur'] . '%',
    //     ];

    //     return response()->json([
    //         'status' => 'success',
    //         'metric_type' => 'Mempool Usage',
    //         'device_ip' => $defaultHostIp,
    //         'rrd_file_used' => $rrdFilePath,
    //         'requested_range' => [
    //             'start_datetime' => $startDateString,
    //             'end_datetime' => $endDateString,
    //         ],
    //         'mempool_summary' => [
    //             'main_memory' => [
    //                 'percent' => [
    //                     'Min' => $summary['used_percent_summary']['min'] . '%',
    //                     'Max' => $summary['used_percent_summary']['max'] . '%',
    //                     'Cur' => $summary['used_percent_summary']['cur'] . '%',
    //                     'max_timestamp' => $summary['max_percent_timestamp'] ?
    //                         date('Y-m-d H:i:s', $summary['max_percent_timestamp']) : null,
    //                     'max_timestamp_unix' => $summary['max_percent_timestamp'],
    //                 ],
    //                 'current_value' => $formattedUsed,
    //             ],
    //             'Total' => $formattedTotal,
    //             // Include raw bytes for client-side processing if needed
    //             'raw_data' => [
    //                 'used_bytes_cur' => $summary['used_bytes_cur_raw'],
    //                 'total_bytes' => $summary['total_bytes_raw'],
    //             ]
    //         ],
    //         'time_series_raw' => $parsedDataMain,
    //     ]);

    // }

    public function getMempoolPerformanceData15Min(string $startDateString = null, string $endDateString = null): JsonResponse
    {
        $rrdFileBaseDir = '/var/www/html/backend_core_automation/storage/rrd/rrd/';
        $rrdFileName = 'mempool-hrstorage-system-65536.rrd';
        $resolution = '-r 300';

        // --- 1. Set time range (default last 15 minutes if none provided) ---
        $endDateTime = new \DateTime($endDateString ?? 'now', new \DateTimeZone('Asia/Dhaka'));
        $startDateTime = new \DateTime($startDateString ?? '-15 minutes', new \DateTimeZone('Asia/Dhaka'));



        $utcTimezone = new \DateTimeZone('UTC');
        $startDateTime->setTimezone($utcTimezone);
        $endDateTime->setTimezone($utcTimezone);

        $startUnix = $startDateTime->getTimestamp();
        $endUnix = $endDateTime->getTimestamp();

        if ($startUnix >= $endUnix) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid datetime range.'
            ], 400);
        }

        $startTime = '-s ' . $startUnix;
        $endTime = '-e ' . $endUnix;

        // --- 2. Fetch NAS IPs dynamically from remote PostgreSQL ---
        $nasIps = DB::connection('remote_pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id') // key = activation_plan_id
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
            $rrdFilePath = $rrdFileBaseDir . $host_ip . '/' . $rrdFileName;

            if (!is_file($rrdFilePath)) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "RRD file for host $host_ip not found."
                ];
                continue;
            }

            // --- 3. Execute RRDtool fetch ---
            $command = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $resolution used free";
            $output = shell_exec($command);

            if (empty($output)) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "Failed to fetch memory data for $host_ip."
                ];
                continue;
            }

            $parsedData = $this->parseRrdFetchOutput($output);

            // --- 4. Calculate summary ---
            $summary = $this->calculateMemorySummary($parsedData);

            $maxMemoryPercent = $summary['used_percent_summary']['max'];
            $maxMemoryTimestamp = $summary['max_percent_timestamp'];

            // --- 5. Insert into remote DB ---
            if ($maxMemoryPercent > 0 && $maxMemoryTimestamp) {
                NasRamUsage::on('remote_pgsql')->create([
                    'activation_plan_id' => $activationPlanId,
                    'max_memory_load' => $maxMemoryPercent,
                    'collected_at' => date('Y-m-d H:i:s', $maxMemoryTimestamp),
                ]);
            }

            // --- 6. Store host-wise JSON response ---
            $hostsData[$host_ip] = [
                'status' => 'success',
                'metric_type' => 'Mempool Usage',
                'device_ip' => $host_ip,
                'rrd_file_used' => $rrdFilePath,
                'requested_range' => [
                    'start_datetime' => $startDateTime->format('Y-m-d H:i:s'),
                    'end_datetime' => $endDateTime->format('Y-m-d H:i:s'),
                ],
                'mempool_summary' => [
                    'main_memory' => [
                        'percent' => [
                            'Min' => $summary['used_percent_summary']['min'] . '%',
                            'Max' => $summary['used_percent_summary']['max'] . '%',
                            'Cur' => $summary['used_percent_summary']['cur'] . '%',
                            'max_timestamp' => $maxMemoryTimestamp ? date('Y-m-d H:i:s', $maxMemoryTimestamp) : null,
                            'max_timestamp_unix' => $maxMemoryTimestamp,
                        ],
                        'current_value' => $this->formatBytesForSiB($summary['used_bytes_cur_raw']),
                    ],
                    'Total' => $this->formatBytesForSiB($summary['total_bytes_raw']),
                    'raw_data' => [
                        'used_bytes_cur' => $summary['used_bytes_cur_raw'],
                        'total_bytes' => $summary['total_bytes_raw'],
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

    public function getMempoolPerformanceData(string $startDateString = null, string $endDateString = null): JsonResponse
    {
        // --- 1. Time Range Setup ---
        $startDateString = $startDateString ?? '2025-10-01 01:05:00';
        $endDateString   = $endDateString ?? '2025-10-02 01:05:00';

        $localTimezone = new \DateTimeZone('Asia/Dhaka');
        $utcTimezone   = new \DateTimeZone('UTC');

        try {
            $startDateTime = new \DateTime($startDateString, $localTimezone);
            $endDateTime   = new \DateTime($endDateString, $localTimezone);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Invalid datetime format.'], 400);
        }

        // Convert to UTC for RRDtool
        $startDateTime->setTimezone($utcTimezone);
        $endDateTime->setTimezone($utcTimezone);

        $startUnix = $startDateTime->getTimestamp();
        $endUnix   = $endDateTime->getTimestamp();

        if ($startUnix >= $endUnix) {
            return response()->json(['status' => 'error', 'message' => 'Invalid datetime range.'], 400);
        }

        $startTime = '-s ' . $startUnix;
        $endTime   = '-e ' . $endUnix;
        $resolution = '-r 300'; // 5 min interval

        // --- 2. Fetch NAS IPs ---
        $nasIps = DB::connection('remote_pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id') // key = activation_plan_id
            ->filter()
            ->unique();

        if ($nasIps->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'No NAS IPs found.'], 404);
        }

        $hostsData = [];

        foreach ($nasIps as $activationPlanId => $host_ip) {
            $rrdFileBaseDir = "/var/www/html/backend_core_automation/storage/rrd/rrd/$host_ip/";
            $rrdFileName = 'mempool-hrstorage-system-65536.rrd';
            $rrdFilePath = $rrdFileBaseDir . $rrdFileName;

            if (!is_file($rrdFilePath)) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "RRD file not found for $host_ip",
                ];
                continue;
            }

            // --- 3. Fetch RRD Data ---
            $command = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime used free";
            $output = shell_exec($command);

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

            // --- 4. Process Summary ---
            $summary = $this->calculateMemorySummary($parsedData);

            $maxMemoryPercent = $summary['used_percent_summary']['max'];
            $maxTimestamp     = $summary['max_percent_timestamp'];

            // Insert into nas_ram_usages on remote_pgsql
            if ($maxMemoryPercent && $maxTimestamp) {
                NasRamUsage::on('remote_pgsql')->create([
                    'activation_plan_id' => $activationPlanId,
                    'max_memory_load'    => $maxMemoryPercent,
                    'collected_at'       => date('Y-m-d H:i:s', $maxTimestamp),
                ]);
            }

            // --- 5. Store results per host ---
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
                            'max_timestamp' => $maxTimestamp ? date('Y-m-d H:i:s', $maxTimestamp) : null,
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




    // public function getSystemDiskStorageData(string $startDateString = null, string $endDateString = null): JsonResponse
    // {
    //     // --- 1. Configuration & Path Setup ---
    //     // RRD file path from the command:
    //     $rrdFileBaseDir = '/var/www/html/backend_core_automation/storage/rrd/rrd/172.24.6.16/';
    //     $rrdFileName = 'storage-hrstorage-system_disk.rrd';
    //     $defaultHostIp = '172.24.6.16';
    //     $rrdFilePath = $rrdFileBaseDir . $rrdFileName;

    //     // DSNs required: used and free
    //     $dsnList = 'used free';
    //     $resolution = '-r 3600'; // 1-hour resolution is often used for long-term storage data (3600s/1hr step)

    //     // --- 2. Time Range Calculation (GMT+6 to UTC conversion) ---
    //     // Example range from the image: 2025-10-15 11:50 to 2025-10-16 11:50 (GMT+6)
    //     $startDateString = $startDateString ?? '2025-10-15 11:50:00';
    //     $endDateString   = $endDateString ?? '2025-10-16 11:50:00';

    //     $localTimezone = new DateTimeZone('Asia/Dhaka');
    //     $utcTimezone = new DateTimeZone('UTC');

    //     try {
    //         $startDateTime = new DateTime($startDateString, $localTimezone);
    //         $endDateTime   = new DateTime($endDateString, $localTimezone);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'error', 'message' => 'Invalid datetime format.'], 400);
    //     }

    //     // Convert to UTC/GMT+0 for RRDtool
    //     $startDateTime->setTimezone($utcTimezone);
    //     $endDateTime->setTimezone($utcTimezone);

    //     $startUnix = $startDateTime->getTimestamp();
    //     $endUnix = $endDateTime->getTimestamp();


    //     if ($startUnix === false || $endUnix === false || $startUnix >= $endUnix) {
    //         return response()->json(['status' => 'error', 'message' => 'Invalid datetime range.'], 400);
    //     }

    //     $startTime = '-s ' . $startUnix;
    //     $endTime = '-e ' . $endUnix;

    //     // --- 3. RRDtool Command Execution (Fetch used and free storage space) ---
    //     $command = self::RRDTOOL_EXECUTABLE
    //                     . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime $dsnList";

    //     $output = shell_exec($command);

    //     if ($output === null || trim($output) === '') {
    //         return response()->json(['status' => 'error', 'message' => "Failed to fetch storage data."], 500);
    //     }

    //     $parsedDataMain = $this->parseRrdFetchOutput($output);

    //     // --- 4. Data Processing and Summary Calculation ---
    //     $summary = $this->calculateStorageSummary($parsedDataMain);

    //     // Format raw bytes (Size and Used) to match the graph legend (e.g., 1.87GB, 176.99MB)
    //     $formattedSize = $this->formatBytesForSiB($summary['total_size_raw'], 2);
    //     $formattedUsed = $this->formatBytesForSiB($summary['used_raw'], 2);

    //     // --- 5. Return Response ---
    //     return response()->json([
    //         'status' => 'success',
    //         'metric_type' => 'System Disk Storage',
    //         'device_ip' => $defaultHostIp,
    //         'rrd_file_used' => $rrdFilePath,
    //         'requested_range' => [
    //             'start_datetime' => $startDateString,
    //             'end_datetime' => $endDateString,
    //         ],
    //         'storage_summary' => [
    //             'disk_name' => 'system disk',
    //             'Size' => $formattedSize,
    //             'Used' => [
    //                 'current_value' => $formattedUsed,
    //                 'max_value' => $this->formatBytesForSiB($summary['max_used_value'], 2),
    //                 'max_timestamp' => $summary['max_used_timestamp'] ?
    //                     date('Y-m-d H:i:s', $summary['max_used_timestamp']) : null,
    //                 'max_timestamp_unix' => $summary['max_used_timestamp'],
    //             ],
    //             'Percent_Used' => [
    //                 'current' => round($summary['percent_used_raw'], 2) . '%',
    //                 'max' => round($summary['max_percent_used'], 2) . '%',
    //                 'max_timestamp' => $summary['max_used_timestamp'] ?
    //                     date('Y-m-d H:i:s', $summary['max_used_timestamp']) : null,
    //             ],
    //             'raw_data' => $summary, // Includes the raw bytes/percent values
    //         ],
    //         'time_series_raw' => $parsedDataMain,
    //     ]);
    // }

    public function getSystemDiskStorageData(string $startDateString = null, string $endDateString = null): JsonResponse
    {
        // --- 1. Time Range Setup ---
        $startDateString = $startDateString ?? '2025-10-15 11:50:00';
        $endDateString   = $endDateString ?? '2025-10-16 11:50:00';

        $localTimezone = new \DateTimeZone('Asia/Dhaka');
        $utcTimezone   = new \DateTimeZone('UTC');

        try {
            $startDateTime = new \DateTime($startDateString, $localTimezone);
            $endDateTime   = new \DateTime($endDateString, $localTimezone);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Invalid datetime format.'], 400);
        }

        // Convert to UTC for RRDtool
        $startDateTime->setTimezone($utcTimezone);
        $endDateTime->setTimezone($utcTimezone);

        $startUnix = $startDateTime->getTimestamp();
        $endUnix   = $endDateTime->getTimestamp();

        if ($startUnix >= $endUnix) {
            return response()->json(['status' => 'error', 'message' => 'Invalid datetime range.'], 400);
        }

        $startTime = '-s ' . $startUnix;
        $endTime   = '-e ' . $endUnix;
        $resolution = '-r 3600'; // 1-hour interval

        // --- 2. Fetch NAS IPs ---
        $nasIps = DB::connection('remote_pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id') // key = activation_plan_id
            ->filter()
            ->unique();

        if ($nasIps->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'No NAS IPs found.'], 404);
        }

        $hostsData = [];

        foreach ($nasIps as $activationPlanId => $host_ip) {
            $rrdFileBaseDir = "/var/www/html/backend_core_automation/storage/rrd/rrd/$host_ip/";
            $rrdFileName = 'storage-hrstorage-system_disk.rrd';
            $rrdFilePath = $rrdFileBaseDir . $rrdFileName;

            if (!is_file($rrdFilePath)) {
                $hostsData[$host_ip] = [
                    'status' => 'error',
                    'message' => "RRD file not found for $host_ip",
                ];
                continue;
            }

            // --- 3. Fetch RRD Data ---
            $command = self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $resolution $startTime $endTime used free";
            $output = shell_exec($command);

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

            // --- 4. Calculate summary ---
            $summary = $this->calculateStorageSummary($parsedData);

            $diskSizeBytes   = (int)$summary['total_size_raw'];
            $diskUsedBytes   = (int)$summary['max_used_value'];
            $maxTimestamp    = $summary['max_used_timestamp'];

            // Insert into nas_disk_usages table
            if ($diskUsedBytes && $maxTimestamp) {
                NasDiskUsage::on('remote_pgsql')->create([
                    'activation_plan_id' => $activationPlanId,
                    'disk_size'          => $diskSizeBytes,
                    'disk_used'          => $diskUsedBytes,
                    'collected_at'       => date('Y-m-d H:i:s', $maxTimestamp),
                ]);
            }

            // --- 5. Store host-wise results ---
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
                        'max_timestamp' => $maxTimestamp ? date('Y-m-d H:i:s', $maxTimestamp) : null,
                        'max_timestamp_unix' => $maxTimestamp,
                    ],
                    'Percent_Used' => [
                        'current' => round($summary['percent_used_raw'], 2) . '%',
                        'max' => round($summary['max_percent_used'], 2) . '%',
                        'max_timestamp' => $maxTimestamp ? date('Y-m-d H:i:s', $maxTimestamp) : null,
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



    // public function getIcmpPerformanceData(string $startDateString = null, string $endDateString = null): JsonResponse
    // {

    //     $rrdFileBaseDir = '/var/www/html/backend_core_automation/storage/rrd/rrd/';
    //     $rrdFileName = 'icmp-perf.rrd';
    //     $defaultHostIp = '172.24.6.16';
    //     $rrdFilePath = $rrdFileBaseDir . $defaultHostIp . '/' . $rrdFileName;


    //     $dsnListFull = 'avg max min xmt rcv';
    //     $dsnListAvg = 'avg';
    //     $fullResolution = '-r 300';
    //     $hourResolution = '-r 3600'; // 1-hour resolution



    //     $startDateString = $startDateString ?? '2025-10-14 17:25:00';
    //     $endDateString   = $endDateString ?? '2025-10-15 17:25:00';


    //     $localTimezone = new DateTimeZone('Asia/Dhaka');
    //     $utcTimezone = new DateTimeZone('UTC');

    //     try {

    //         $startDateTime = new DateTime($startDateString, $localTimezone);
    //         $endDateTime   = new DateTime($endDateString, $localTimezone);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Invalid datetime format: ' . $e->getMessage()
    //         ], 400);
    //     }


    //     $startDateTime->setTimezone($utcTimezone);
    //     $endDateTime->setTimezone($utcTimezone);

    //     $startUnix = $startDateTime->getTimestamp();
    //     $endUnix = $endDateTime->getTimestamp();


    //     if ($startUnix === false || $endUnix === false || $startUnix >= $endUnix) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Invalid datetime range. Start time must be before end time.'
    //         ], 400);
    //     }

    //     $startTime = '-s ' . $startUnix;
    //     $endTime = '-e ' . $endUnix;



    //     //  Fetch Main Data (5-min res, all DSNs)
    //     $commandMain = self::RRDTOOL_EXECUTABLE
    //                     . " fetch \"$rrdFilePath\" AVERAGE $fullResolution $startTime $endTime $dsnListFull";
    //     $outputMain = shell_exec($commandMain);

    //     if ($outputMain === null || trim($outputMain) === '') {
    //         return response()->json(['status' => 'error', 'message' => "Failed to fetch main data."], 500);
    //     }
    //     $parsedDataMain = $this->parseRrdFetchOutput($outputMain);

    //     // 3B. Fetch 1-Hour Aggregation Data
    //     $outputHourAvg = shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $hourResolution $startTime $endTime $dsnListAvg");
    //     $outputHourMin = shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MIN $hourResolution $startTime $endTime $dsnListAvg");
    //     $outputHourMax = shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MAX $hourResolution $startTime $endTime $dsnListAvg");

    //     $parsedDataHourAvg = $this->parseRrdFetchOutput($outputHourAvg);
    //     $parsedDataHourMin = $this->parseRrdFetchOutput($outputHourMin);
    //     $parsedDataHourMax = $this->parseRrdFetchOutput($outputHourMax);




    //     $summary = $this->calculateComprehensiveSummary(
    //         $parsedDataMain,
    //         $parsedDataHourAvg,
    //         $parsedDataHourMin,
    //         $parsedDataHourMax
    //     );

    //     // Pass $endUnix (now UTC) to correctly cap the final outage.
    //     $outageAnalysis = $this->analyzeOutageGaps($parsedDataMain, $endUnix);


    //     // --- 5. Return Response ---
    //     return response()->json([
    //         'status' => 'success',
    //         'metric_type' => 'ICMP Performance (Comprehensive)',
    //         'device_ip' => $defaultHostIp,
    //         'rrd_file_used' => $rrdFilePath,
    //         'requested_range' => [
    //             'start_datetime' => $startDateString,
    //             'end_datetime' => $endDateString,
    //             // Return the UTC Unix timestamps used for RRDtool for debugging
    //             'start_unix_utc' => $startUnix,
    //             'end_unix_utc' => $endUnix,
    //         ],
    //         'icmp_summary' => $summary,
    //         'outage_analysis' => $outageAnalysis,
    //         'time_series_raw' => $parsedDataMain,
    //     ]);
    // }

    public function getIcmpPerformanceData(string $startDateString = null, string $endDateString = null): JsonResponse
    {
        // --- 1. Load NAS IPs mapped to activation_plan_id ---
        $nasIps = DB::connection('remote_pgsql')
            ->table('partner_activation_plans')
            ->pluck('nas_ip', 'id') // key = activation_plan_id
            ->filter()
            ->unique();

        if ($nasIps->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'No NAS IPs found.'], 404);
        }

        $startDateString = $startDateString ?? '2025-10-14 17:25:00';
        $endDateString   = $endDateString ?? '2025-10-15 17:25:00';

        $localTimezone = new DateTimeZone('Asia/Dhaka');
        $utcTimezone   = new DateTimeZone('UTC');

        try {
            $startDateTime = new DateTime($startDateString, $localTimezone);
            $endDateTime   = new DateTime($endDateString, $localTimezone);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Invalid datetime format.'], 400);
        }

        $startDateTime->setTimezone($utcTimezone);
        $endDateTime->setTimezone($utcTimezone);

        $startUnix = $startDateTime->getTimestamp();
        $endUnix   = $endDateTime->getTimestamp();

        if ($startUnix >= $endUnix) {
            return response()->json(['status' => 'error', 'message' => 'Start time must be before end time.'], 400);
        }

        $startTime = '-s ' . $startUnix;
        $endTime   = '-e ' . $endUnix;
        $fullResolution = '-r 300';
        $hourResolution = '-r 3600';
        $dsnListFull = 'avg max min xmt rcv';
        $dsnListAvg  = 'avg';

        $results = [];

        foreach ($nasIps as $activationPlanId => $nasIp) {

            $rrdFilePath = "/var/www/html/backend_core_automation/storage/rrd/rrd/{$nasIp}/icmp-perf.rrd";

            // --- Fetch 5-min resolution main data ---
            $outputMain = shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $fullResolution $startTime $endTime $dsnListFull");
            if ($outputMain === null || trim($outputMain) === '') continue;
            $parsedDataMain = $this->parseRrdFetchOutput($outputMain);

            // --- Fetch 1-hour aggregated avg/min/max ---
            $parsedDataHourAvg = $this->parseRrdFetchOutput(shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $hourResolution $startTime $endTime $dsnListAvg"));
            $parsedDataHourMin = $this->parseRrdFetchOutput(shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MIN $hourResolution $startTime $endTime $dsnListAvg"));
            $parsedDataHourMax = $this->parseRrdFetchOutput(shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MAX $hourResolution $startTime $endTime $dsnListAvg"));

            // --- Calculate summary & outages ---
            $summary = $this->calculateComprehensiveSummary($parsedDataMain, $parsedDataHourAvg, $parsedDataHourMin, $parsedDataHourMax);
            $outageAnalysis = $this->analyzeOutageGaps($parsedDataMain, $endUnix);

            // --- Insert max RTT into NasIcmpLatency ---
            $maxRtt = $summary['rtt_loss_high_res']['rtt_ms'] ?? [];
            if (!empty($maxRtt['max']) && !empty($maxRtt['max_timestamp'])) {
                NasIcmpLatency::on('remote_pgsql')->updateOrCreate(
                    [
                        'activation_plan_id' => $activationPlanId,
                        'collected_at' => $maxRtt['max_timestamp'],
                    ],
                    [
                        'threshold_exceeded_value' => $maxRtt['max'],
                    ]
                );
            }

            // --- Insert outage periods into NasIcmpTimeout ---
            if (!empty($outageAnalysis['outage_periods'])) {
                foreach ($outageAnalysis['outage_periods'] as $outage) {
                    NasIcmpTimeout::on('remote_pgsql')->updateOrCreate(
                        [
                            'activation_plan_id' => $activationPlanId,
                            'timeout_start' => $outage['start_time_formatted'],
                            'timeout_end' => $outage['end_time_formatted'],
                        ],
                        [
                            'timeout_duration' => $outage['duration_seconds'],
                        ]
                    );
                }
            }

            $results[$nasIp] = [
                'activation_plan_id' => $activationPlanId,
                'icmp_summary' => $summary,
                'outage_analysis' => $outageAnalysis,
                'time_series_raw' => $parsedDataMain,
            ];
        }

        return response()->json([
            'status' => 'success',
            'hosts' => $nasIps->values(),
            'data' => $results,
            'requested_range' => [
                'start_datetime' => $startDateString,
                'end_datetime' => $endDateString,
            ],
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

        // --- Prepare Data (filtering out NaN for metrics) ---
        $avgRttData = array_column($parsedDataMain, 'avg');
        $minRttData = array_column($parsedDataMain, 'min');
        $maxRttData = array_column($parsedDataMain, 'max');

        $xmtData    = array_column($parsedDataMain, 'xmt');
        $rcvData    = array_column($parsedDataMain, 'rcv');

        $avgRttData = array_filter($avgRttData, fn($value) => is_numeric($value) && $value !== null);
        $minRttData = array_filter($minRttData, fn($value) => is_numeric($value) && $value !== null);
        $maxRttData = array_filter($maxRttData, fn($value) => is_numeric($value) && $value !== null);

        // --- Find timestamps for min/max values ---
        $maxRttTimestamp = null;
        $minRttTimestamp = null;
        $maxRttValue = 0.0;
        $minRttValue = PHP_FLOAT_MAX;

        foreach ($parsedDataMain as $dataPoint) {
            $currentAvg = $dataPoint['avg'] ?? null;
            $timestamp = $dataPoint['timestamp_unix'] ?? null;

            if (is_numeric($currentAvg) && $timestamp) {
                // Track maximum RTT
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

        // --- RTT and Loss Summary (High Resolution) ---
        $rttSummary = [
            'cur' => !empty($avgRttData) ? round(end($avgRttData), 2) : 0.0,
            'min' => !empty($minRttData) ? round(min($minRttData), 2) : 0.0,
            'max' => !empty($maxRttData) ? round(max($maxRttData), 2) : 0.0,
            'avg' => !empty($avgRttData) ? round(array_sum($avgRttData) / count($avgRttData), 2) : 0.0,
            // Add timestamps for min and max
            'min_timestamp' => $minRttTimestamp ? date('Y-m-d H:i:s', $minRttTimestamp) : null,
            'min_timestamp_unix' => $minRttTimestamp,
            'max_timestamp' => $maxRttTimestamp ? date('Y-m-d H:i:s', $maxRttTimestamp) : null,
            'max_timestamp_unix' => $maxRttTimestamp,
        ];

        // Loss Summary
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

                // Track maximum loss
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
            // Add timestamp for maximum loss
            'max_timestamp' => $maxLossTimestamp ? date('Y-m-d H:i:s', $maxLossTimestamp) : null,
            'max_timestamp_unix' => $maxLossTimestamp,
        ];

        // --- Ping Response Table (Yellow Box Data) ---
        $rttTableSummary = [
            'Now' => !empty($avgRttData) ? round(end($avgRttData), 2) : 0.0,
            'Min' => !empty($avgRttData) ? round(min($avgRttData), 2) : 0.0,
            'Max' => !empty($avgRttData) ? round(max($avgRttData), 2) : 0.0,
            'Avg' => !empty($avgRttData) ? round(array_sum($avgRttData) / count($avgRttData), 2) : 0.0,
            // Add timestamps for Min and Max
            'min_timestamp' => $minRttTimestamp ? date('Y-m-d H:i:s', $minRttTimestamp) : null,
            'max_timestamp' => $maxRttTimestamp ? date('Y-m-d H:i:s', $maxRttTimestamp) : null,
        ];

        // 1-Hour Aggregation Rows
        $hourAvgData = array_filter(array_column($parsedDataHourAvg, 'avg'), fn($v) => is_numeric($v) && $v !== null);
        $hourMinData = array_filter(array_column($parsedDataHourMin, 'avg'), fn($v) => is_numeric($v) && $v !== null);
        $hourMaxData = array_filter(array_column($parsedDataHourMax, 'avg'), fn($v) => is_numeric($v) && $v !== null);

        $hourAggregatedSummary = [
            '1_hour_avg' => $this->getAggregatedSummary($hourAvgData, $parsedDataHourAvg),
            '1_hour_min' => $this->getAggregatedSummary($hourMinData, $parsedDataHourMin),
            '1_hour_max' => $this->getAggregatedSummary($hourMaxData, $parsedDataHourMax),
        ];

        // Percentiles
        $sortedCoreRttData = $avgRttData;
        sort($sortedCoreRttData);

        $percentileSummary = [
            '25th_Percentile' => round($this->calculatePercentile($sortedCoreRttData, 25), 6),
            '50th_Percentile' => round($this->calculatePercentile($sortedCoreRttData, 50), 6),
            '75th_Percentile' => round($this->calculatePercentile($sortedCoreRttData, 75), 6),
        ];

        // Combine all summaries
        return [
            'rtt_loss_high_res' => ['rtt_ms' => $rttSummary, 'loss_percent' => $lossSummary],
            'ping_response_table' => [
                'Milliseconds_avg' => $rttTableSummary,
                'Aggregated_Hours' => $hourAggregatedSummary,
                'Percentiles' => $percentileSummary,
            ],
        ];
    }

    // protected function getAggregatedSummary(array $data): array
    // {
    //     return [
    //         'Now' => !empty($data) ? round(end($data), 2) : 0.0,
    //         'Min' => !empty($data) ? round(min($data), 2) : 0.0,
    //         'Max' => !empty($data) ? round(max($data), 2) : 0.0,
    //         'Avg' => !empty($data) ? round(array_sum($data) / count($data), 2) : 0.0,
    //     ];
    // }

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
