<?php
require_once __DIR__ . '/functions.php';

$config = load_config();
$miners = $config['miners'];

// Query all miners for current status
$total_wattage  = 0;
$total_hashrate = 0;
foreach ($miners as &$miner) {
    query_miner($miner, $config);
    $total_wattage  += $miner['current_miner_consumption'];
    $total_hashrate += $miner['hashrate_5m'];
}
unset($miner);

$efficiency = ($total_hashrate > 0) ? round($total_wattage / $total_hashrate, 2) : 0;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Miner Wattage Manager</title>
<style>
:root {
    --bg: #1a1a2e;
    --surface: #16213e;
    --card: #1e2a47;
    --accent: #0f3460;
    --highlight: #e94560;
    --text: #eee;
    --text-muted: #8892a4;
    --green: #4ecca3;
    --orange: #f5a623;
    --red: #e94560;
    --radius: 10px;
}
* { box-sizing: border-box; }
body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    margin: 0;
    padding: 20px;
    font-size: 16px;
    min-height: 100vh;
}
.page-header {
    text-align: center;
    margin-bottom: 24px;
}
.page-header h1 {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 12px 0;
    color: #fff;
}
.fleet-stats {
    display: flex;
    justify-content: center;
    gap: 24px;
    flex-wrap: wrap;
}
.stat-badge {
    background: var(--accent);
    border-radius: var(--radius);
    padding: 10px 20px;
    font-size: 15px;
    font-weight: 600;
}
.stat-badge .value {
    color: var(--green);
}
.miner-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 20px;
    max-width: 1200px;
    margin: 0 auto;
}
.miner-card {
    background: var(--card);
    border-radius: var(--radius);
    padding: 20px;
    border: 1px solid rgba(255,255,255,0.06);
}
.miner-card.offline {
    opacity: 0.5;
}
.miner-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 14px;
}
.miner-header a {
    text-decoration: none;
    color: inherit;
}
.miner-header a:hover .miner-name {
    color: var(--green);
}
.miner-name {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    transition: color 0.2s;
}
.miner-location {
    color: var(--text-muted);
    font-size: 13px;
    margin: 2px 0 0 0;
}
.miner-ip {
    color: var(--text-muted);
    font-size: 13px;
    font-family: 'Consolas', 'Courier New', monospace;
}
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}
.stat-item {
    background: var(--surface);
    border-radius: 8px;
    padding: 10px;
    text-align: center;
}
.stat-item .label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 4px;
}
.stat-item .val {
    font-size: 18px;
    font-weight: 700;
}
.stat-item .val.hashrate { color: var(--green); }
.stat-item .val.power { color: var(--orange); }
.stat-item .val.fan { color: var(--text); }
.controls-row {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.controls-row form {
    display: flex;
    gap: 6px;
    align-items: center;
    margin: 0;
}
select, input[type="text"] {
    background: var(--surface);
    color: var(--text);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 15px;
    outline: none;
    transition: border-color 0.2s;
}
select:focus, input[type="text"]:focus {
    border-color: var(--green);
}
select {
    cursor: pointer;
    min-width: 120px;
}
input[type="text"] {
    width: 90px;
}
button, .btn {
    background: var(--accent);
    color: var(--text);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    text-transform: capitalize;
}
button:hover {
    background: #1a4a8a;
    transform: translateY(-1px);
}
button.pause {
    background: var(--orange);
    color: #1a1a2e;
}
button.pause:hover {
    background: #e09510;
}
button.resume {
    background: var(--green);
    color: #1a1a2e;
}
button.resume:hover {
    background: #3db88e;
}
.pool-section {
    border-top: 1px solid rgba(255,255,255,0.06);
    padding-top: 12px;
    margin-top: 4px;
}
.pool-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 14px;
    gap: 10px;
}
.pool-row + .pool-row {
    border-top: 1px solid rgba(255,255,255,0.04);
}
.pool-name {
    font-weight: 600;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pool-user {
    color: var(--text-muted);
    font-size: 12px;
}
.pool-status {
    font-size: 12px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: uppercase;
}
.pool-status.alive { background: rgba(78,204,163,0.15); color: var(--green); }
.pool-status.dead { background: rgba(233,69,96,0.15); color: var(--red); }
.pool-status.disabled { background: rgba(136,146,164,0.15); color: var(--text-muted); }
.pool-row form {
    margin: 0;
}
.pool-row button {
    font-size: 12px;
    padding: 4px 10px;
}
.offline-banner {
    text-align: center;
    color: var(--text-muted);
    font-size: 18px;
    padding: 30px 0;
}
.action-result {
    max-width: 600px;
    margin: 20px auto;
    background: var(--card);
    border: 1px solid var(--green);
    border-radius: var(--radius);
    padding: 20px;
    text-align: center;
}
.action-result h2, .action-result h3 {
    margin: 4px 0;
}
.action-result p {
    margin: 8px 0;
    color: var(--text-muted);
}
.action-result a {
    color: var(--green);
    text-decoration: none;
    font-weight: 600;
}
.action-result a:hover {
    text-decoration: underline;
}
</style>
</head>
<body>
<div class="page-header">
    <h1>&#9889; Miner Wattage Manager</h1>
    <div class="fleet-stats">
        <div class="stat-badge"><span class="value"><?= $total_wattage ?>W</span> Total</div>
        <div class="stat-badge"><span class="value"><?= $total_hashrate ?> TH/s</span> Hashrate</div>
        <div class="stat-badge"><span class="value"><?= $efficiency ?> W/TH</span> Efficiency</div>
    </div>
</div>

<div class="miner-grid">
<?php foreach ($miners as $miner): ?>
<?php $online = ($miner['current_wattage'] != 0); ?>
<div class="miner-card<?= $online ? '' : ' offline' ?>">
    <div class="miner-header">
        <a href="http://<?= htmlspecialchars($miner['ip']) ?>">
            <p class="miner-name"><?= htmlspecialchars($miner['name']) ?></p>
            <p class="miner-location"><?= htmlspecialchars($miner['location']) ?></p>
        </a>
        <span class="miner-ip"><?= htmlspecialchars($miner['ip']) ?></span>
    </div>

<?php if ($online): ?>
    <div class="stats-row">
        <div class="stat-item">
            <div class="label">Hashrate (5m)</div>
            <div class="val hashrate"><?= $miner['hashrate_5m'] ?> TH/s</div>
        </div>
        <div class="stat-item">
            <div class="label">Power</div>
            <div class="val power"><?= $miner['current_miner_consumption'] ?>W</div>
        </div>
        <div class="stat-item">
            <div class="label">Fans</div>
            <div class="val fan"><?= $miner['current_fans'] ?>%</div>
        </div>
    </div>
<?php if ($miner['hashrate_5m'] > 0): ?>
    <div class="stats-row">
        <div class="stat-item" style="grid-column: span 3;">
            <div class="label">Efficiency</div>
            <div class="val power"><?= round($miner['current_miner_consumption'] / $miner['hashrate_5m'], 2) ?> W/TH</div>
        </div>
    </div>
<?php endif; ?>

    <div class="controls-row">
<?php $poweraction = ($miner['current_miner_consumption'] == 0) ? 'resume' : 'pause'; ?>
        <form method="post">
            <input type="hidden" name="action" value="power">
            <input type="hidden" name="poweraction" value="<?= $poweraction ?>">
            <input type="hidden" name="name" value="<?= htmlspecialchars($miner['name']) ?>">
            <input type="hidden" name="connection" value="<?= htmlspecialchars($miner['connection']) ?>">
            <button type="submit" class="<?= $poweraction ?>"><?= $poweraction ?></button>
        </form>

        <form method="post">
            <input type="hidden" name="action" value="wattage">
            <input type="hidden" name="name" value="<?= htmlspecialchars($miner['name']) ?>">
            <input type="hidden" name="connection" value="<?= htmlspecialchars($miner['connection']) ?>">
            <input type="hidden" name="current_wattage" value="<?= $miner['current_wattage'] ?>">
            <select name="wattage" onchange="this.form.submit()">
<?php
            $presets = $config['wattage_presets'];
            $current = $miner['current_wattage'];
            $is_custom = !in_array($current, $presets) && $current > 0;
            if ($is_custom) {
                $presets[] = $current;
                sort($presets);
            }
            foreach ($presets as $w):
                if ($w <= $miner['max_wattage']):
                    $selected = ($w === $current) ? ' selected' : '';
                    $label = $w . ' W';
                    if ($w === $current && $is_custom) {
                        $label .= ' (current)';
                    }
?>
                <option value="<?= $w ?>"<?= $selected ?>><?= $label ?></option>
<?php
                endif;
            endforeach;
?>
            </select>
        </form>

        <form method="post">
            <input type="hidden" name="action" value="wattage">
            <input type="hidden" name="name" value="<?= htmlspecialchars($miner['name']) ?>">
            <input type="hidden" name="connection" value="<?= htmlspecialchars($miner['connection']) ?>">
            <input type="hidden" name="current_wattage" value="<?= $miner['current_wattage'] ?>">
            <input type="text" name="wattage" minlength="3" maxlength="4" size="6" pattern="\d+" placeholder="Watts">
            <button type="submit">Set</button>
        </form>
    </div>

<?php
    // Pool information
    $pools_data = miner_command(
        $miner['connection'], $config['api_port'], 'pools', null, $config['api_timeout']
    );
    if ($pools_data && isset($pools_data['POOLS'])):
?>
    <div class="pool-section">
<?php   foreach ($pools_data['POOLS'] as $pool):
            $url_parts = explode('.', $pool['Stratum URL']);
            end($url_parts);
            $pool_name = prev($url_parts);
            $poolaction = ($pool['Status'] === 'Alive' || $pool['Status'] === 'Dead')
                ? 'disable' : 'enable';
            $status_class = strtolower($pool['Status']);
?>
        <div class="pool-row">
            <div class="pool-name">
                <?= htmlspecialchars($pool_name) ?>
                <span class="pool-user"><?= htmlspecialchars($pool['User']) ?></span>
            </div>
            <span class="pool-status <?= $status_class ?>"><?= htmlspecialchars($pool['Status']) ?></span>
            <form method="post">
                <input type="hidden" name="action" value="pool">
                <input type="hidden" name="poolaction" value="<?= $poolaction ?>pool">
                <input type="hidden" name="pool" value="<?= (int)$pool['POOL'] ?>">
                <input type="hidden" name="name" value="<?= htmlspecialchars($miner['name']) ?>">
                <input type="hidden" name="connection" value="<?= htmlspecialchars($miner['connection']) ?>">
                <button type="submit"><?= $poolaction ?></button>
            </form>
        </div>
<?php   endforeach; ?>
    </div>
<?php endif; ?>

<?php else: ?>
    <div class="offline-banner">Miner currently offline</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<?php
// ---------------------------------------------------------------------------
// Handle POST actions (after rendering so the page shows current state first)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'], $_POST['name'], $_POST['connection'])
) {
    // Validate the target miner exists in config
    $target = find_miner($config['miners'], $_POST['name'], $_POST['connection']);
    if (!$target) {
        echo '<div class="action-result"><p>Unknown miner.</p></div>';
    } else {
        echo '<div class="action-result">';
        $refresh = 15;

        switch ($_POST['action']) {
            case 'wattage':
                if (isset($_POST['wattage'])) {
                    $wattage = (int)$_POST['wattage'];
                    $current = isset($_POST['current_wattage']) ? (int)$_POST['current_wattage'] : -1;

                    if ($wattage === $current) {
                        echo '<p>Entered wattage is equal to the current wattage. No action taken.</p>';
                    } elseif ($wattage >= 250 && $wattage <= $target['max_wattage']) {
                        echo '<h2>Updating ' . htmlspecialchars($target['name'])
                            . ' (' . htmlspecialchars($target['ip']) . ')</h2>';
                        echo '<h3>New Wattage: ' . $wattage . ' W</h3>';
                        change_wattage($target['connection'], $wattage, $config['ssh_password']);
                    } else {
                        echo '<p>Target wattage (' . $wattage . 'W) is out of range. '
                            . 'Enter a value between 250 and ' . $target['max_wattage'] . '.</p>';
                    }
                }
                break;

            case 'pool':
                $valid = ['disablepool', 'enablepool'];
                if (isset($_POST['poolaction'], $_POST['pool'])
                    && in_array($_POST['poolaction'], $valid, true)
                ) {
                    $result = pool_change(
                        $target['connection'], $config['api_port'],
                        $_POST['poolaction'], (int)$_POST['pool'], $config['api_timeout']
                    );
                    if ($result && isset($result['STATUS'][0]['Msg'])) {
                        echo '<h2>' . htmlspecialchars($result['STATUS'][0]['Msg'])
                            . ' on ' . htmlspecialchars($target['name'])
                            . ' (' . htmlspecialchars($target['ip']) . ')</h2>';
                    }
                    $refresh = 5;
                } else {
                    echo '<p>Invalid pool action.</p>';
                }
                break;

            case 'power':
                $valid = ['pause', 'resume'];
                if (isset($_POST['poweraction'])
                    && in_array($_POST['poweraction'], $valid, true)
                ) {
                    $result = state_change(
                        $target['connection'], $config['api_port'],
                        $_POST['poweraction'], $config['api_timeout']
                    );
                    if ($result && isset($result['STATUS'][0]['Msg'])) {
                        echo '<h2>' . htmlspecialchars($result['STATUS'][0]['Msg'])
                            . ' initiated on ' . htmlspecialchars($target['name'])
                            . ' (' . htmlspecialchars($target['ip']) . ')</h2>';
                    }
                } else {
                    echo '<p>Invalid power action.</p>';
                }
                break;
        }

        echo '<p>Page will reload in ' . $refresh . ' seconds. '
            . '<a href=".">Refresh now</a></p>';
        echo '<meta http-equiv="refresh" content="' . $refresh . '">';
        echo '</div>';
    }
}
?>
</body>
</html>
