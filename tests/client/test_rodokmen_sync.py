"""Tests of bin/rodokmen-sync (standard library only): python3 -m unittest discover -s tests/client"""
import hashlib
import importlib.machinery
import importlib.util
import io
import json
import tempfile
import threading
import unittest
from contextlib import redirect_stdout
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[2] / "bin" / "rodokmen-sync"
loader = importlib.machinery.SourceFileLoader("rodokmen_sync", str(SCRIPT))
spec = importlib.util.spec_from_loader("rodokmen_sync", loader)
sync = importlib.util.module_from_spec(spec)
loader.exec_module(sync)


def make_project(root):
    """A project folder as the skill's exporter leaves it."""
    files = {
        "web/src/data/site.json": b'{"people": {}}',
        "web/src/generated/zajimavosti.md": "# Zajímavosti\n".encode(),
        "web/src/generated/fan.svg": b"<svg/>",
        "web/public/img/krest_jan_1890.jpg": b"crop",
        "web/public/zdroje/krest_jan_1890.jpg": b"full scan",
        "web/public/img/Bad Name.JPG": b"skipped",
    }
    for path, content in files.items():
        (root / path).parent.mkdir(parents=True, exist_ok=True)
        (root / path).write_bytes(content)
    return root


def quiet(function, *args, **kwargs):
    with redirect_stdout(io.StringIO()):
        return function(*args, **kwargs)


class ConfigTest(unittest.TestCase):
    def test_file_environment_and_options_in_this_order(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = Path(tmp)
            (project / sync.CONFIG_FILE).write_text(
                "# comment\nRODOKMEN_WEB_TARGET='https://file.example'\nRODOKMEN_WEB_TOKEN=file-token\n", encoding="utf-8")
            self.assertEqual(sync.load_config(project, {})["RODOKMEN_WEB_TARGET"], "https://file.example")
            config = sync.load_config(project, {"RODOKMEN_WEB_TOKEN": "env-token"})
            self.assertEqual(config["RODOKMEN_WEB_TOKEN"], "env-token")
            config = sync.load_config(project, {"RODOKMEN_WEB_TARGET": "https://env.example"}, target="/tmp/x")
            self.assertEqual(config["RODOKMEN_WEB_TARGET"], "/tmp/x")

    def test_parse_target(self):
        self.assertEqual(sync.parse_target("https://rodokmen.example.org/"), ("api", "https://rodokmen.example.org"))
        self.assertEqual(sync.parse_target("user@host:/srv/data"), ("ssh", "user@host:/srv/data", None))
        self.assertEqual(sync.parse_target("ssh://user@host:2222/srv/data"), ("ssh", "user@host:/srv/data", "2222"))
        self.assertEqual(sync.parse_target("server:data"), ("ssh", "server:data", None))
        self.assertEqual(sync.parse_target("../web/var/data"), ("local", Path("../web/var/data")))
        self.assertEqual(sync.parse_target("C:\\web\\data")[0], "local")
        with self.assertRaises(sync.SyncError):
            sync.parse_target("")


class BundleTest(unittest.TestCase):
    def test_collect_takes_only_publishable_files(self):
        with tempfile.TemporaryDirectory() as tmp:
            files = quiet(sync.collect, make_project(Path(tmp)))
            self.assertEqual(sorted(files), ["full/krest_jan_1890.jpg", "img/krest_jan_1890.jpg", "md/zajimavosti.md", "site.json"])

    def test_collect_without_export_fails(self):
        with tempfile.TemporaryDirectory() as tmp, self.assertRaises(sync.SyncError):
            sync.collect(Path(tmp))

    def test_manifest_lists_hashes_and_sizes(self):
        with tempfile.TemporaryDirectory() as tmp:
            files = quiet(sync.collect, make_project(Path(tmp)))
            manifest = sync.build_manifest(files)
            self.assertEqual(manifest["format"], 1)
            self.assertEqual(manifest["files"]["img/krest_jan_1890.jpg"],
                             {"sha256": hashlib.sha256(b"crop").hexdigest(), "size": 4})
            self.assertEqual(json.loads(sync.manifest_bytes(manifest)), manifest)

    def test_export_requires_a_project(self):
        with tempfile.TemporaryDirectory() as tmp, self.assertRaises(sync.SyncError):
            sync.export(Path(tmp))


class LocalTargetTest(unittest.TestCase):
    def test_copies_changes_deletes_stale_files_and_keeps_others(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = make_project(Path(tmp) / "project")
            data = Path(tmp) / "data"
            (data / "img").mkdir(parents=True)
            (data / "img/old_record.jpg").write_bytes(b"old")
            (data / "notes.txt").write_text("not part of a bundle")
            files = quiet(sync.collect, project)
            manifest = sync.build_manifest(files)

            preview = sync.sync_local(files, manifest, data, dry_run=True)
            self.assertEqual(len(preview["copied"]), 4)
            self.assertFalse((data / "site.json").exists())

            result = sync.sync_local(files, manifest, data)
            self.assertEqual(result["deleted"], ["img/old_record.jpg"])
            self.assertEqual((data / "full/krest_jan_1890.jpg").read_bytes(), b"full scan")
            self.assertTrue((data / "notes.txt").exists())
            self.assertEqual(json.loads((data / "manifest.json").read_text())["files"], manifest["files"])

            again = sync.sync_local(files, manifest, data)
            self.assertEqual((again["copied"], again["unchanged"]), ([], 4))

    def test_stage_and_rsync_command(self):
        with tempfile.TemporaryDirectory() as tmp:
            files = quiet(sync.collect, make_project(Path(tmp) / "project"))
            manifest = sync.build_manifest(files)
            staged = Path(tmp) / "stage"
            sync.stage(files, manifest, staged)
            self.assertEqual(sync.bundle_paths(staged), sorted(files))
            self.assertTrue((staged / "manifest.json").is_file())
        command = sync.rsync_command("/tmp/s", "user@host:/srv/data", port="2222", dry_run=True)
        self.assertEqual(command[-2:], ["/tmp/s/", "user@host:/srv/data/"])
        self.assertIn("--dry-run", command)
        self.assertIn("ssh -p 2222", command)


class FakeSite:
    """In-memory implementation of the website's sync API."""

    def __init__(self, token="secret", have=()):
        self.token = token
        self.stored = set(have)
        self.committed = None
        site = self

        class Handler(BaseHTTPRequestHandler):
            def log_message(self, *args):
                pass

            def reply(self, status, data):
                body = json.dumps(data).encode()
                self.send_response(status)
                self.send_header("Content-Type", "application/json")
                self.send_header("Content-Length", str(len(body)))
                self.end_headers()
                self.wfile.write(body)

            def body(self):
                return self.rfile.read(int(self.headers.get("Content-Length", 0)))

            def authorized(self):
                if self.headers.get("Authorization") != f"Bearer {site.token}":
                    self.reply(401, {"error": "Invalid token"})
                    return False
                return True

            def do_POST(self):
                if not self.authorized():
                    return
                manifest = json.loads(self.body())
                hashes = {f["sha256"] for f in manifest["files"].values()}
                missing = sorted(hashes - site.stored)
                if self.path == "/api/sync/plan":
                    current = site.committed is not None and site.committed["files"] == manifest["files"]
                    self.reply(200, {"files": len(manifest["files"]), "missing": missing, "current": current})
                elif missing:
                    self.reply(409, {"error": "Some files have not been uploaded", "details": missing})
                else:
                    site.committed = manifest
                    self.reply(200, {"release": "20261113-120000-abcdef12", "files": len(manifest["files"])})

            def do_PUT(self):
                if not self.authorized():
                    return
                sha = self.path.rsplit("/", 1)[1]
                if hashlib.sha256(self.body()).hexdigest() != sha:
                    self.reply(422, {"error": "Uploaded content does not match its hash"})
                    return
                site.stored.add(sha)
                self.reply(201, {"stored": sha})

        self.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        self.url = f"http://127.0.0.1:{self.server.server_port}"
        threading.Thread(target=self.server.serve_forever, daemon=True).start()

    def close(self):
        self.server.shutdown()
        self.server.server_close()


class ApiTargetTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.files = quiet(sync.collect, make_project(Path(self.tmp.name)))
        self.manifest = sync.build_manifest(self.files)

    def tearDown(self):
        self.tmp.cleanup()

    def test_uploads_only_missing_files_and_commits(self):
        known = self.manifest["files"]["site.json"]["sha256"]
        site = FakeSite(have={known})
        try:
            result = quiet(sync.sync_api, self.files, self.manifest, sync.Api(site.url, "secret"))
        finally:
            site.close()
        self.assertEqual(result, {"uploaded": 3, "release": "20261113-120000-abcdef12"})
        self.assertEqual(site.committed["files"], self.manifest["files"])

    def test_nothing_to_do_when_the_site_is_current(self):
        site = FakeSite()
        try:
            api = sync.Api(site.url, "secret")
            quiet(sync.sync_api, self.files, self.manifest, api)
            site.committed["generated"] = "earlier"
            self.assertEqual(quiet(sync.sync_api, self.files, self.manifest, api), {"uploaded": 0})
        finally:
            site.close()

    def test_dry_run_only_plans(self):
        site = FakeSite()
        try:
            result = quiet(sync.sync_api, self.files, self.manifest, sync.Api(site.url, "secret"), dry_run=True)
        finally:
            site.close()
        self.assertEqual(result, {"uploaded": 0, "missing": 4})
        self.assertIsNone(site.committed)

    def test_wrong_token_is_reported(self):
        site = FakeSite()
        try:
            with self.assertRaisesRegex(sync.SyncError, "HTTP 401 – Invalid token"):
                quiet(sync.sync_api, self.files, self.manifest, sync.Api(site.url, "wrong"))
        finally:
            site.close()

    def test_missing_token_and_unreachable_site(self):
        with self.assertRaises(sync.SyncError):
            sync.Api("https://example.invalid", "")
        with self.assertRaisesRegex(sync.SyncError, "Cannot reach"):
            sync.Api("http://127.0.0.1:9", "x").plan(self.manifest)


class MainTest(unittest.TestCase):
    def test_publishes_last_export_into_a_local_folder(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = make_project(Path(tmp) / "project")
            data = Path(tmp) / "data"
            code = quiet(sync.main, ["-p", str(project), "--target", str(data), "--no-export"], environ={})
            self.assertEqual(code, 0)
            self.assertTrue((data / "site.json").is_file())

    def test_error_without_target(self):
        with tempfile.TemporaryDirectory() as tmp, redirect_stdout(io.StringIO()):
            err = io.StringIO()
            from contextlib import redirect_stderr
            with redirect_stderr(err):
                self.assertEqual(sync.main(["-p", tmp, "--no-export"], environ={}), 1)
            self.assertIn("No target", err.getvalue())


if __name__ == "__main__":
    unittest.main()
