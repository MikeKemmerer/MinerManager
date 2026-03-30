<?php
/**
 * MinerManager helper functions.
 *
 * Provides configuration loading, CGMiner API communication via native
 * PHP sockets, and miner control actions (wattage, pool, power state).
 */

/**
 * Load and validate the JSON configuration file.
 */
function load_config() {
    $path = __DIR__ . '/config.json';
    if (!file_exists($path)) {
        die('Configuration file not found. Copy config.example.json to config.json and update with your values.');
    }
    $config = json_decode(file_get_contents($path), true);
    if ($config === null) {
        die('Invalid JSON in config.json: ' . json_last_error_msg());
    }
    return $config;
}

/**
 * Send a command to the CGMiner API via a TCP socket.
 *
 * @param string   $connection  Miner IP or hostname
 * @param int      $port        API port (typically 4028)
 * @param string   $command     CGMiner command name
 * @param int|null $parameter   Optional command parameter (e.g. pool ID)
 * @param int      $timeout     Socket timeout in seconds
 * @return array|null  Decoded JSON response, or null on failure
 */
function miner_command($connection, $port, $command, $parameter = null, $timeout = 1) {
    $payload = ['command' => $command];
    if ($parameter !== null) {
        $payload['parameter'] = (int)$parameter;
    }

    $fp = @fsockopen($connection, $port, $errno, $errstr, $timeout);
    if (!$fp) {
        return null;
    }
    stream_set_timeout($fp, $timeout);
    fwrite($fp, json_encode($payload));

    $response = '';
    while (!feof($fp)) {
        $chunk = fread($fp, 8192);
        if ($chunk === false) {
            break;
        }
        $response .= $chunk;
    }
    fclose($fp);

    // Strip control characters that the CGMiner API injects
    $response = preg_replace('/[[:cntrl:]]/', '', $response);
    return json_decode($response, true);
}

/**
 * Query a miner for summary, tuner status, and fan speed.
 * Populates runtime fields on the $miner array.
 */
function query_miner(&$miner, $config) {
    $port    = $config['api_port'];
    $timeout = $config['api_timeout'];
    $conn    = $miner['connection'];

    // Hashrate
    $summary = miner_command($conn, $port, 'summary', null, $timeout);
    $miner['hashrate_5m'] = 0;
    if ($summary && isset($summary['SUMMARY'][0]['MHS 5m'])) {
        $miner['hashrate_5m'] = round($summary['SUMMARY'][0]['MHS 5m'] / 1000000, 2);
    }

    // Wattage / power consumption
    $tuner = miner_command($conn, $port, 'tunerstatus', null, $timeout);
    $miner['current_wattage'] = 0;
    $miner['current_miner_consumption'] = 0;
    if ($tuner && isset($tuner['TUNERSTATUS'][0])) {
        $miner['current_wattage'] = (int)$tuner['TUNERSTATUS'][0]['PowerLimit'];
        $miner['current_miner_consumption'] = (int)$tuner['TUNERSTATUS'][0]['ApproximateMinerPowerConsumption'];
    }

    // Fan speed
    $fans = miner_command($conn, $port, 'fans', null, $timeout);
    $miner['current_fans'] = 0;
    if ($fans && isset($fans['FANS'][0]['Speed'])) {
        $miner['current_fans'] = (int)$fans['FANS'][0]['Speed'];
    }
}

/**
 * Look up a miner in the config by name and connection address.
 * Returns the config entry or null if not found.
 */
function find_miner($miners, $name, $connection) {
    foreach ($miners as $miner) {
        if ($miner['name'] === $name && $miner['connection'] === $connection) {
            return $miner;
        }
    }
    return null;
}

/**
 * Change the power limit on a miner via SSH.
 * Edits bosminer.toml and reloads the service.
 */
function change_wattage($connection, $wattage, $ssh_password) {
    $wattage = (int)$wattage;
    $cmd = sprintf(
        'sshpass -p %s ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 root@%s %s',
        escapeshellarg($ssh_password),
        escapeshellarg($connection),
        escapeshellarg(
            "sed -i 's/^psu_power_limit.*\$/psu_power_limit = $wattage/g' /etc/bosminer.toml; "
            . "/etc/init.d/bosminer reload"
        )
    );
    return shell_exec($cmd);
}

/**
 * Enable or disable a mining pool via the CGMiner API.
 */
function pool_change($connection, $port, $poolaction, $pool_id, $timeout = 1) {
    return miner_command($connection, $port, $poolaction, $pool_id, $timeout);
}

/**
 * Pause or resume mining via the CGMiner API.
 */
function state_change($connection, $port, $action, $timeout = 1) {
    return miner_command($connection, $port, $action, null, $timeout);
}
