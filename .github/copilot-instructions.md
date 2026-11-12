# MinerManager

## Runtime and commands

- Requires PHP 7.4+ with socket support and SSH available to the web-server user for wattage changes. Production uses Apache/mod_php or nginx/php-fpm.
- Local setup in PowerShell: `Copy-Item config.example.json config.json`, then edit the copy with deployment-specific values. Do not overwrite an existing configuration.
- Local preview: `php -S 127.0.0.1:8000 -t .`, then open `http://127.0.0.1:8000/`. Rendering the dashboard and polling stats contact the configured miners.
- Syntax-check the application: `php -l functions.php && php -l index.php && php -l api.php` (PowerShell 7 or a shell supporting `&&`).
- Syntax-check one file: `php -l functions.php`.
- Regression tests: `python3 -m unittest discover -s tests -v` (PHP CLI and Python 3, no dependencies). One test: `python3 -m unittest discover -s tests -k test_pool_status_cannot_inject_markup -v`. Tests use a temporary configuration and loopback fake miner. CONTRIBUTING.md also recommends real-miner testing when possible; control actions change real hardware and require explicit authorization.

## Architecture

- This is a single-page, procedural PHP application for Braiins OS miners. There is no framework, dependency-install step, asset build, or database. Persistent deployment settings live in `config.json`; miner status is queried on each request.
- `functions.php` is the shared boundary for configuration loading, CGMiner TCP communication, status normalization, miner lookup, and control actions. Both HTTP entry points include it using `__DIR__`.
- `index.php` validates session-bound CSRF tokens before querying miners for POST requests, then loads configuration, queries miners, and renders HTML with inline CSS and vanilla JavaScript. Controls submit ordinary POST forms. Action handling occurs after rendering; feedback schedules a full-page GET refresh.
- `api.php` is the read-only polling endpoint. It returns a `fleet` object and `miners` array using the same `query_miner()` helper as initial rendering. Inline JavaScript waits 10 seconds between completed polls to avoid overlap.
- `miner_command()` sends JSON commands over TCP to the configured API port (normally 4028), strips control characters from the response, and returns a decoded array or null on failure. Pool enable/disable and mining pause/resume use this transport.
- Wattage changes take a separate SSH path: `change_wattage()` edits `psu_power_limit` in the miner's `/etc/bosminer.toml` and reloads bosminer. README.md documents web-server-owned SSH keys and RSA compatibility with older Dropbear firmware.

## Repository-specific conventions

- Treat `config.example.json` as the shareable configuration template. Never commit `config.json`, SSH keys, or credentials. `MinerManagerDropdown.php` is a gitignored legacy implementation containing hardcoded secrets; `apache-vhost.local.conf` and `.vscode/` are local-only too. Make application changes in the tracked PHP files.
- The miner identity is the exact pair `name` and `connection`: forms post both, `find_miner()` checks both against configuration, and polling locates cards through matching `data-miner`/`data-conn` attributes. Keep these paths consistent.
- `ip` is the miner web-UI link address; `connection` is the API/SSH address. They may intentionally differ, such as with VPN routing.
- Resolve action targets and wattage limits from configuration, not posted copies. Wattage accepts 250 through the configured `max_wattage`; pool actions are allowlisted as `disablepool`/`enablepool`, and power actions as `pause`/`resume`.
- `query_miner()` mutates a miner array by reference and initializes runtime metrics to zero when responses are unavailable. Hashrate is converted from CGMiner's `MHS 5m` to TH/s by dividing by 1,000,000.
- Keep target power (`current_wattage`, exposed as API `wattage`) distinct from measured consumption (`current_miner_consumption`, exposed as API `power`). Fleet wattage sums consumption; efficiency is consumption divided by TH/s, with zero-hashrate guards. Online status is currently defined by nonzero target wattage, not hashrate.
- Coordinate metric changes across `query_miner()`, initial HTML, the JSON response, and the polling updater. Polling updates metrics, wattage selection, and pause/resume state; online/offline transitions trigger a GET navigation to rebuild cards and pools. Preserve CSRF fields in every control form.
- Preserve the existing procedural style: snake_case PHP helpers and array keys, four-space indentation, `htmlspecialchars()` for dynamic HTML, integer casts for numeric command parameters, and `escapeshellarg()` for SSH shell arguments.
- The dashboard is intended for trusted local networks and has no authentication layer. Keep local previews bound to loopback.
