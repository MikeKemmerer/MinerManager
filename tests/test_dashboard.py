import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import socketserver
import subprocess
import tempfile
import threading
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request


ROOT = Path(__file__).resolve().parents[1]
INJECTED_STATUS = '"><script>alert("pool")</script>'


class MinerHandler(socketserver.BaseRequestHandler):
    def handle(self):
        command = json.loads(self.request.recv(8192))["command"]
        self.server.commands.append(command)
        responses = {
            "summary": {"SUMMARY": [{"MHS 5m": 10_000_000}]},
            "tunerstatus": {"TUNERSTATUS": [{
                "PowerLimit": 600, "ApproximateMinerPowerConsumption": 500,
            }]},
            "fans": {"FANS": [{"Speed": 50}]},
            "pools": {"POOLS": [{
                "Stratum URL": "stratum.example.com", "User": "test",
                "Status": INJECTED_STATUS, "POOL": 0,
            }]},
            "pause": {"STATUS": [{"Msg": "Paused"}]},
        }
        self.request.sendall(json.dumps(responses.get(command, {})).encode() + b"\0")


class DashboardTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory()
        cls.root = Path(cls.temp.name)
        for name in ("index.php", "api.php", "functions.php"):
            shutil.copyfile(ROOT / name, cls.root / name)
        cls.miner = socketserver.ThreadingTCPServer(("127.0.0.1", 0), MinerHandler)
        cls.miner.commands = []
        threading.Thread(target=cls.miner.serve_forever, daemon=True).start()
        (cls.root / "config.json").write_text(json.dumps({
            "ssh_key_path": "/unused-test-key",
            "api_port": cls.miner.server_address[1], "api_timeout": 1,
            "wattage_presets": [500, 600, 700],
            "miners": [{
                "name": "test-miner", "ip": "127.0.0.1", "connection": "127.0.0.1",
                "location": "Test", "max_wattage": 1000,
            }],
        }))
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        cls.url = f"http://127.0.0.1:{port}/"
        cls.cookies = http.cookiejar.CookieJar()
        cls.client = urllib.request.build_opener(
            urllib.request.ProxyHandler({}),
            urllib.request.HTTPCookieProcessor(cls.cookies),
        )
        cls.log = (cls.root / "php.log").open("w+")
        env = dict(os.environ)
        if os.name != "nt":
            stub_bin = cls.root / "bin"
            stub_bin.mkdir()
            ssh = stub_bin / "ssh"
            ssh.write_text("#!/bin/sh\nexit 255\n")
            ssh.chmod(0o700)
            env["PATH"] = str(stub_bin) + os.pathsep + env["PATH"]
        cls.php = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{port}", "-t", str(cls.root)],
            stdout=cls.log, stderr=cls.log, env=env,
        )
        for _ in range(100):
            try:
                cls.client.open(cls.url, timeout=1).close()
                return
            except urllib.error.URLError:
                if cls.php.poll() is not None:
                    break
                time.sleep(0.05)
        cls.tearDownClass()
        raise RuntimeError("Test PHP server did not start")

    @classmethod
    def tearDownClass(cls):
        cls.php.terminate()
        cls.php.wait(timeout=5)
        cls.log.close()
        cls.miner.shutdown()
        cls.miner.server_close()
        cls.temp.cleanup()

    def get_page(self):
        with self.client.open(self.url, timeout=5) as response:
            return response.read().decode()

    def post(self, token=None, **fields):
        data = {"action": "wattage", "name": "test-miner", "connection": "127.0.0.1"}
        if token is not None:
            data["csrf_token"] = token
        data.update(fields)
        return self.client.open(self.url, urllib.parse.urlencode(data).encode(), timeout=5)

    def test_pool_status_cannot_inject_markup(self):
        page = self.get_page()
        self.assertNotIn(INJECTED_STATUS, page)
        self.assertIn('class="pool-status unknown"', page)
        self.assertIn("&lt;script&gt;", page)

    def test_missing_and_invalid_tokens_reject_before_querying_miners(self):
        for token in (None, "invalid"):
            before = len(self.miner.commands)
            with self.assertRaises(urllib.error.HTTPError) as raised:
                self.post(token, wattage=700)
            self.assertEqual(raised.exception.code, 403)
            raised.exception.close()
            self.assertEqual(len(self.miner.commands), before)

    def test_valid_session_token_and_server_side_current_wattage(self):
        page = self.get_page()
        token = re.search(r'name="csrf_token" value="([^"]+)"', page)[1]
        with self.post(token, wattage=600, current_wattage=999) as response:
            result = response.read().decode()
        self.assertIn("No action taken", result)
        self.assertTrue(all(cookie.has_nonstandard_attr("HttpOnly") for cookie in self.cookies))

    def test_token_from_another_session_is_rejected(self):
        page = self.get_page()
        token = re.search(r'name="csrf_token" value="([^"]+)"', page)[1]
        other = urllib.request.build_opener(urllib.request.ProxyHandler({}))
        data = urllib.parse.urlencode({"csrf_token": token}).encode()
        with self.assertRaises(urllib.error.HTTPError) as raised:
            other.open(self.url, data, timeout=5)
        self.assertEqual(raised.exception.code, 403)
        raised.exception.close()

    @unittest.skipIf(os.name == "nt", "SSH stub uses a POSIX executable")
    def test_wattage_action_reports_ssh_failure(self):
        token = re.search(r'name="csrf_token" value="([^"]+)"', self.get_page())[1]
        with self.post(token, wattage=700) as response:
            page = response.read().decode()
        self.assertIn("Power-limit update failed", page)
        self.assertNotIn("Power-limit update completed", page)

    @unittest.skipIf(os.name == "nt", "SSH stub uses a POSIX executable")
    def test_wattage_helper_reports_ssh_exit_status(self):
        with tempfile.TemporaryDirectory() as directory:
            ssh = Path(directory) / "ssh"
            env = dict(os.environ, PATH=directory + os.pathsep + os.environ["PATH"])
            for status in (0, 1, 255):
                ssh.write_text(f"#!/bin/sh\nexit {status}\n")
                ssh.chmod(0o700)
                result = subprocess.run([
                    "php", "-r",
                    "require 'functions.php'; "
                    "exit(change_wattage('unused', 700, '/unused') ? 0 : 1);",
                ], cwd=self.root, env=env, timeout=5)
                self.assertEqual(result.returncode, 0 if status == 0 else 1)

    def test_api_preserves_metric_units(self):
        with self.client.open(self.url + "api.php", timeout=5) as response:
            data = json.load(response)
        self.assertEqual(data["miners"][0]["wattage"], 600)
        self.assertEqual(data["miners"][0]["power"], 500)
        self.assertEqual(data["fleet"]["total_hashrate"], 10)
        self.assertEqual(data["fleet"]["efficiency"], 50)


if __name__ == "__main__":
    unittest.main()
