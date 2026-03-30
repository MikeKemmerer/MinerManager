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
<title>Miner Wattage Manager</title>
<style>
body {
    font-family: Arial, Helvetica, sans-serif;
    background-color: lightgray;
    color: darkblue;
    width: 100%;
    padding: 0;
    margin: 0;
    font-size: 20px;
}
h1, h2, h3, h4, h5 {
    padding: 1px;
    margin: 1px;
    text-align: center;
}
button, input, select {
    font-size: 20px;
}
table {
    width: 100%;
}
td {
    text-align: center;
    vertical-align: middle;
}
.container {
    text-align: center;
}
.pool-table td:first-child {
    text-align: left;
}
.action-result {
    margin-top: 10px;
    padding: 10px;
}
</style>
</head>
<body>
<div class="container">
<h1>Miner Wattage Manager</h1>
<p>Total Watts: <?= $total_wattage ?>W | Total TH/s: <?= $total_hashrate ?> | W/TH: <?= $efficiency ?></p>

<table>
<?php foreach ($miners as $miner): ?>
<?php $online = ($miner['current_wattage'] != 0); ?>
<tr>
    <td>
        <a href="http://<?= htmlspecialchars($miner['ip']) ?>">
            <h3><?= htmlspecialchars($miner['name']) ?></h3>
            <h5><?= htmlspecialchars($miner['location']) ?></h5>
            <h4><?= htmlspecialchars($miner['ip']) ?></h4>
        </a>
        <h5>5m Hashrate: <?= $miner['hashrate_5m'] ?> TH/s @ <?= $miner['current_miner_consumption'] ?>W</h5>
<?php if ($miner['hashrate_5m'] > 0): ?>
        <h5><?= round($miner['current_miner_consumption'] / $miner['hashrate_5m'], 2) ?> W/TH</h5>
<?php endif; ?>
        <h5>Fans: <?= $miner['current_fans'] ?>%</h5>

<?php if ($online): ?>
<?php $poweraction = ($miner['current_miner_consumption'] == 0) ? 'resume' : 'pause'; ?>
        <form method="post">
            <input type="hidden" name="action" value="power">
            <input type="hidden" name="poweraction" value="<?= $poweraction ?>">
            <input type="hidden" name="name" value="<?= htmlspecialchars($miner['name']) ?>">
            <input type="hidden" name="connection" value="<?= htmlspecialchars($miner['connection']) ?>">
            <button type="submit"><?= $poweraction ?></button>
        </form>
<?php endif; ?>
    </td>

<?php if ($online): ?>
    <!-- Wattage dropdown -->
    <td>
        <form method="post">
            <input type="hidden" name="action" value="wattage">
            <input type="hidden" name="name" value="<?= htmlspecialchars($miner['name']) ?>">
            <input type="hidden" name="connection" value="<?= htmlspecialchars($miner['connection']) ?>">
            <input type="hidden" name="current_wattage" value="<?= $miner['current_wattage'] ?>">
            <select name="wattage" onchange="this.form.submit()">
<?php
            // Build options list, inserting current wattage if it's a custom value
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
    </td>

    <!-- Custom wattage input -->
    <td colspan="2">
        <form method="post">
            <input type="hidden" name="action" value="wattage">
            <input type="hidden" name="name" value="<?= htmlspecialchars($miner['name']) ?>">
            <input type="hidden" name="connection" value="<?= htmlspecialchars($miner['connection']) ?>">
            <input type="hidden" name="current_wattage" value="<?= $miner['current_wattage'] ?>">
            <input type="text" name="wattage" minlength="3" maxlength="4" size="10" pattern="\d+" placeholder="Watts">
            <button type="submit">Other</button>
        </form>
    </td>
<?php else: ?>
    <td colspan="3"><h2>Miner currently offline</h2></td>
<?php endif; ?>
</tr>

<?php if ($online): ?>
<?php
    // Pool information
    $pools_data = miner_command(
        $miner['connection'], $config['api_port'], 'pools', null, $config['api_timeout']
    );
    if ($pools_data && isset($pools_data['POOLS'])):
?>
<tr>
    <td colspan="4" style="text-align:left">
        <table class="pool-table" style="width:100%">
<?php   foreach ($pools_data['POOLS'] as $pool):
            $url_parts = explode('.', $pool['Stratum URL']);
            end($url_parts);
            $pool_name = prev($url_parts);
            $poolaction = ($pool['Status'] === 'Alive' || $pool['Status'] === 'Dead')
                ? 'disable' : 'enable';
?>
            <tr>
                <td style="text-align:left">
                    <b><?= htmlspecialchars($pool_name) ?>:</b>
                    <?= htmlspecialchars($pool['User']) ?>
                </td>
                <td><?= htmlspecialchars($pool['Status']) ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="action" value="pool">
                        <input type="hidden" name="poolaction" value="<?= $poolaction ?>pool">
                        <input type="hidden" name="pool" value="<?= (int)$pool['POOL'] ?>">
                        <input type="hidden" name="name" value="<?= htmlspecialchars($miner['name']) ?>">
                        <input type="hidden" name="connection" value="<?= htmlspecialchars($miner['connection']) ?>">
                        <button type="submit"><?= $poolaction ?></button>
                    </form>
                </td>
            </tr>
<?php   endforeach; ?>
        </table>
    </td>
</tr>
<?php endif; ?>
<?php endif; ?>

<?php endforeach; ?>
</table>

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
</div>
</body>
</html>
