# MinerManager

A single-page PHP dashboard for monitoring and controlling Bitcoin miners running [Braiins OS](https://braiins.com/os/plus) (BOS) firmware.

## Features

- **Real-time status** — hashrate, power consumption, efficiency (W/TH), and fan speed per miner
- **Wattage control** — dropdown presets or custom input with SSH-based `bosminer.toml` editing
- **Pool management** — enable/disable mining pools via the CGMiner API
- **Pause/Resume** — toggle mining on individual miners
- **Fleet totals** — aggregate wattage, hashrate, and efficiency across all miners

## Requirements

- PHP 7.4+ with sockets enabled (standard in most distributions)
- A web server (Apache, nginx, lighttpd) with PHP support
- `sshpass` installed on the web server host (for wattage changes via SSH)
- Miners running Braiins OS with the CGMiner API enabled on port 4028

## Setup

1. Copy the project files to your web server's document root:
   ```
   index.php
   functions.php
   config.example.json
   ```

2. Create your configuration:
   ```bash
   cp config.example.json config.json
   ```

3. Edit `config.json` with your miner details:
   - `ssh_password` — root password for SSH access to miners
   - `api_port` — CGMiner API port (default: 4028)
   - `api_timeout` — socket timeout in seconds
   - `wattage_presets` — array of wattage values for the dropdown
   - `miners` — array of miner entries with `name`, `ip`, `max_wattage`, `connection`, and `location`

4. Open the page in a browser.

## Configuration

The `ip` and `connection` fields in each miner entry can differ if the miner's web UI and API are reachable on different addresses (e.g. through a VPN). In most setups they will be the same.

| Field | Description |
|-------|-------------|
| `name` | Miner hostname (shown in the UI and used for identification) |
| `ip` | Address used for the web UI link |
| `connection` | Address used for API and SSH communication |
| `max_wattage` | Upper limit for the wattage dropdown |
| `location` | Human-readable location label |

## Security Notes

- `config.json` contains your SSH password and miner IPs — it is gitignored and must never be committed
- This tool is designed for **local network use only** and has no authentication layer
- All form inputs are validated server-side against the configuration

## License

MIT — see [LICENSE](LICENSE).
