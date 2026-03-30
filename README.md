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
- Apache with `mod_php` or similar (nginx + php-fpm also works)
- Miners running Braiins OS with the CGMiner API enabled on port 4028

## Setup

1. Copy the project files to your web server's document root:
   ```
   index.php
   functions.php
   api.php
   config.example.json
   ```

2. Create your configuration:
   ```bash
   cp config.example.json config.json
   ```

3. Edit `config.json` with your miner details:
   - `ssh_key_path` — absolute path to the SSH private key used for miner access
   - `api_port` — CGMiner API port (default: 4028)
   - `api_timeout` — socket timeout in seconds
   - `wattage_presets` — array of wattage values for the dropdown
   - `miners` — array of miner entries with `name`, `ip`, `max_wattage`, `connection`, and `location`

4. Set up SSH key-based authentication for miner access:
   ```bash
   # Create a directory for the web server's SSH keys
   sudo mkdir -p /var/www/.ssh
   sudo chown www-data:www-data /var/www/.ssh
   sudo chmod 700 /var/www/.ssh

   # Generate an RSA key pair (Dropbear on many miners does not support Ed25519)
   sudo -u www-data ssh-keygen -t rsa -b 4096 -f /var/www/.ssh/id_rsa_miners -N ""

   # Copy the public key to each miner
   ssh-copy-id -i /var/www/.ssh/id_rsa_miners.pub root@<miner-ip>

   # Verify passwordless login works
   sudo -u www-data ssh -i /var/www/.ssh/id_rsa_miners -o StrictHostKeyChecking=no root@<miner-ip> echo ok
   ```

   > **Note:** The `ssh_key_path` in `config.json` must match the private key path
   > created above. Braiins OS miners run Dropbear SSH, which may only support RSA
   > and ECDSA keys — Ed25519 keys will be silently rejected on older firmware.

5. Set up the Apache virtual host:
   ```bash
   sudo cp apache-vhost.conf.example /etc/apache2/sites-available/minermanager.conf
   # Edit ServerName and DocumentRoot to match your setup
   sudo a2ensite minermanager
   sudo systemctl reload apache2
   ```

6. Open the page in a browser.

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

- `config.json` contains your miner IPs and SSH key path — it is gitignored and must never be committed
- The SSH private key should be owned by `www-data` with mode `600`
- This tool is designed for **local network use only** and has no authentication layer
- All form inputs are validated server-side against the configuration

## License

MIT — see [LICENSE](LICENSE).
