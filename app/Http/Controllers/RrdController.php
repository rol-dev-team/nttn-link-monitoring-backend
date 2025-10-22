<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Illuminate\Http\JsonResponse;
use DateTime;
use DateTimeZone;

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

    public function getPortData(string $startDateString = null, string $endDateString = null)
    {
        
        // $startDateString = $startDateString ?? '2025-10-01 01:05:00';
        // $endDateString   = $endDateString ?? '2025-10-02 01:05:00'; 
        $startDateString = $startDateString ?? '2025-10-01 01:05:00';
        $endDateString = $endDateString ?? '2025-10-02 01:05:00';
        // $startDateString = '2025-10-01 01:05:00';
        // $endDateString   = '2025-10-02 01:05:00';
        
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
    
    public function getDeviceCpuData(string $startDateString = null, string $endDateString = null): JsonResponse
    {
        
        $startDateString = $startDateString ?? '2025-10-01 01:05:00';
        $endDateString = $endDateString ?? '2025-10-02 01:05:00'; 
        
        
        $rrdFileBaseDir = '/var/www/html/backend_core_automation/storage/rrd/rrd/';
        
        $host_ip = '172.24.6.16';
        $cpuRrdDirectory = $rrdFileBaseDir . $host_ip;

        
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

        
        $allCpuFiles = glob($cpuRrdDirectory . '/processor-hr-*.rrd');
        $numProcessors = count($allCpuFiles);
        $allCpuData = []; 

        if ($numProcessors === 0) {
            return response()->json([
                'status' => 'error',
                'message' => "No processor RRD files found for host $host_ip in $cpuRrdDirectory."
            ], 404);
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
                    
                    if (is_numeric($usage) && $usage !== null) {
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

        return response()->json([
            'status' => 'success',
            'host_ip' => $host_ip, 
            'metric_type' => 'Aggregated_CPU_Usage',
            'requested_range' => [
                'start_datetime' => $startDateString,
                'end_datetime' => $endDateString,
            ],
            'cpu_summary' => $summary,
            'results' => $aggregatedCpuResults, 
        ]);
    }


    public function getIcmpPerformanceData(string $startDateString = null, string $endDateString = null): JsonResponse
    {
        
        $rrdFileBaseDir = '/var/www/html/backend_core_automation/storage/rrd/rrd/';
        $rrdFileName = 'icmp-perf.rrd';
        $defaultHostIp = '172.24.6.16'; 
        $rrdFilePath = $rrdFileBaseDir . $defaultHostIp . '/' . $rrdFileName;
        
        
        $dsnListFull = 'avg max min xmt rcv'; 
        $dsnListAvg = 'avg'; 
        $fullResolution = '-r 300';
        $hourResolution = '-r 3600'; // 1-hour resolution

       
        
        $startDateString = $startDateString ?? '2025-10-14 17:25:00'; 
        $endDateString   = $endDateString ?? '2025-10-15 17:25:00'; 
        
        
        $localTimezone = new DateTimeZone('Asia/Dhaka'); 
        $utcTimezone = new DateTimeZone('UTC');

        try {
            
            $startDateTime = new DateTime($startDateString, $localTimezone);
            $endDateTime   = new DateTime($endDateString, $localTimezone);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid datetime format: ' . $e->getMessage()
            ], 400);
        }
        
        
        $startDateTime->setTimezone($utcTimezone);
        $endDateTime->setTimezone($utcTimezone);

        $startUnix = $startDateTime->getTimestamp();
        $endUnix = $endDateTime->getTimestamp();


        if ($startUnix === false || $endUnix === false || $startUnix >= $endUnix) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid datetime range. Start time must be before end time.'
            ], 400);
        }

        $startTime = '-s ' . $startUnix;
        $endTime = '-e ' . $endUnix;

        

        //  Fetch Main Data (5-min res, all DSNs)
        $commandMain = self::RRDTOOL_EXECUTABLE 
                        . " fetch \"$rrdFilePath\" AVERAGE $fullResolution $startTime $endTime $dsnListFull";
        $outputMain = shell_exec($commandMain);
        
        if ($outputMain === null || trim($outputMain) === '') {
            return response()->json(['status' => 'error', 'message' => "Failed to fetch main data."], 500); 
        }
        $parsedDataMain = $this->parseRrdFetchOutput($outputMain);

        // 3B. Fetch 1-Hour Aggregation Data 
        $outputHourAvg = shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" AVERAGE $hourResolution $startTime $endTime $dsnListAvg");
        $outputHourMin = shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MIN $hourResolution $startTime $endTime $dsnListAvg");
        $outputHourMax = shell_exec(self::RRDTOOL_EXECUTABLE . " fetch \"$rrdFilePath\" MAX $hourResolution $startTime $endTime $dsnListAvg");

        $parsedDataHourAvg = $this->parseRrdFetchOutput($outputHourAvg);
        $parsedDataHourMin = $this->parseRrdFetchOutput($outputHourMin);
        $parsedDataHourMax = $this->parseRrdFetchOutput($outputHourMax);


        
        
        $summary = $this->calculateComprehensiveSummary(
            $parsedDataMain,
            $parsedDataHourAvg,
            $parsedDataHourMin,
            $parsedDataHourMax
        );
        
        // Pass $endUnix (now UTC) to correctly cap the final outage.
        $outageAnalysis = $this->analyzeOutageGaps($parsedDataMain, $endUnix); 


        // --- 5. Return Response ---
        return response()->json([
            'status' => 'success',
            'metric_type' => 'ICMP Performance (Comprehensive)',
            'device_ip' => $defaultHostIp,
            'rrd_file_used' => $rrdFilePath,
            'requested_range' => [
                'start_datetime' => $startDateString,
                'end_datetime' => $endDateString,
                // Return the UTC Unix timestamps used for RRDtool for debugging
                'start_unix_utc' => $startUnix,
                'end_unix_utc' => $endUnix, 
            ],
            'icmp_summary' => $summary,
            'outage_analysis' => $outageAnalysis, 
            'time_series_raw' => $parsedDataMain, 
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

        // --- RTT and Loss Summary (High Resolution) ---
        $rttSummary = [
            'cur' => !empty($avgRttData) ? round(end($avgRttData), 2) : 0.0,
            'min' => !empty($minRttData) ? round(min($minRttData), 2) : 0.0,
            'max' => !empty($maxRttData) ? round(max($maxRttData), 2) : 0.0,
            'avg' => !empty($avgRttData) ? round(array_sum($avgRttData) / count($avgRttData), 2) : 0.0,
        ];

        // Loss Summary
        $lossSeries = [];
        foreach ($xmtData as $index => $xmt) {
            $rcv = $rcvData[$index] ?? 0;
            if (is_numeric($xmt) && $xmt > 0 && is_numeric($rcv)) {
                $lossSeries[] = round((($xmt - $rcv) / $xmt) * 100, 2);
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
        ];
        
        // --- Ping Response Table (Yellow Box Data) ---
        $rttTableSummary = [
            'Now' => !empty($avgRttData) ? round(end($avgRttData), 2) : 0.0,
            'Min' => !empty($avgRttData) ? round(min($avgRttData), 2) : 0.0,
            'Max' => !empty($avgRttData) ? round(max($avgRttData), 2) : 0.0,
            'Avg' => !empty($avgRttData) ? round(array_sum($avgRttData) / count($avgRttData), 2) : 0.0,
        ];

        // 1-Hour Aggregation Rows
        $hourAvgData = array_filter(array_column($parsedDataHourAvg, 'avg'), fn($v) => is_numeric($v) && $v !== null);
        $hourMinData = array_filter(array_column($parsedDataHourMin, 'avg'), fn($v) => is_numeric($v) && $v !== null);
        $hourMaxData = array_filter(array_column($parsedDataHourMax, 'avg'), fn($v) => is_numeric($v) && $v !== null);

        $hourAggregatedSummary = [
            '1_hour_avg' => $this->getAggregatedSummary($hourAvgData),
            '1_hour_min' => $this->getAggregatedSummary($hourMinData),
            '1_hour_max' => $this->getAggregatedSummary($hourMaxData),
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

    
    protected function getAggregatedSummary(array $data): array
    {
        return [
            'Now' => !empty($data) ? round(end($data), 2) : 0.0,
            'Min' => !empty($data) ? round(min($data), 2) : 0.0,
            'Max' => !empty($data) ? round(max($data), 2) : 0.0,
            'Avg' => !empty($data) ? round(array_sum($data) / count($data), 2) : 0.0,
        ];
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
        
        // The resolution is 300 seconds (5 minutes)
        // $step = 300; 

        foreach ($parsedDataMain as $index => $dataPoint) {
            $timestamp = $dataPoint['timestamp_unix'];
            $rttValue = $dataPoint['avg']; 
            
            // Check if RTT is null/NaN (missing data, the white blank gap)
            $isCurrentNull = !is_numeric($rttValue) || $rttValue === null;

            if ($isCurrentNull && !$isOutage) {
                // Start of a new outage period
                $isOutage = true;
                $outageStartUnix = $timestamp;
                
            } elseif (!$isCurrentNull && $isOutage) {
                // End of an outage period (The gap ends at the timestamp of the *first good* data point.)
                $outageEndUnix = $timestamp;
                $duration = $outageEndUnix - $outageStartUnix;

                // Ensure duration is positive before logging
                if ($duration > 0) { 
                    $outages[] = [
                        'start_time_unix' => $outageStartUnix,
                        'end_time_unix' => $outageEndUnix,
                        'start_time_formatted' => date('Y-m-d H:i:s', $outageStartUnix),
                        'end_time_formatted' => date('Y-m-d H:i:s', $outageEndUnix), 
                        'duration_seconds' => $duration,
                        'duration_formatted' => $this->formatDuration($outageStartUnix, $outageEndUnix),
                    ];
                }
                
                $isOutage = false;
            }
        }

        // Handle case where the outage continues until the end of the fetched range
        if ($isOutage) {
            // Use the requested $endUnix as the absolute end time for the final outage
            $outageEndUnix = $endUnix; 
            $duration = $outageEndUnix - $outageStartUnix;

            // Only record the final outage if the duration is positive
            if ($duration > 0) {
                $outages[] = [
                    'start_time_unix' => $outageStartUnix,
                    'end_time_unix' => $outageEndUnix,
                    'start_time_formatted' => date('Y-m-d H:i:s', $outageStartUnix),
                    'end_time_formatted' => date('Y-m-d H:i:s', $outageEndUnix),
                    'duration_seconds' => $duration,
                    'duration_formatted' => $this->formatDuration($outageStartUnix, $outageEndUnix),
                ];
            }
        }

        return [
            'total_outage_count' => count($outages),
            'outage_periods' => $outages,
        ];
    }
}