<?php
/**
 * Lightweight JSON API for real-time miner stats polling.
 * Returns current hashrate, power, fans, efficiency, and wattage for each miner.
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/functions.php';

$config = load_config();
$miners = $config['miners'];

$total_wattage  = 0;
$total_hashrate = 0;
$results = [];

foreach ($miners as $miner) {
    query_miner($miner, $config);
    $total_wattage  += $miner['current_miner_consumption'];
    $total_hashrate += $miner['hashrate_5m'];

    $eff = ($miner['hashrate_5m'] > 0)
        ? round($miner['current_miner_consumption'] / $miner['hashrate_5m'], 2)
        : 0;

    $results[] = [
        'name'        => $miner['name'],
        'connection'  => $miner['connection'],
        'hashrate_5m' => $miner['hashrate_5m'],
        'power'       => $miner['current_miner_consumption'],
        'wattage'     => $miner['current_wattage'],
        'fans'        => $miner['current_fans'],
        'efficiency'  => $eff,
        'online'      => ($miner['current_wattage'] != 0),
    ];
}

$fleet_efficiency = ($total_hashrate > 0) ? round($total_wattage / $total_hashrate, 2) : 0;

echo json_encode([
    'fleet' => [
        'total_wattage'  => $total_wattage,
        'total_hashrate' => $total_hashrate,
        'efficiency'     => $fleet_efficiency,
    ],
    'miners' => $results,
], JSON_UNESCAPED_SLASHES);
