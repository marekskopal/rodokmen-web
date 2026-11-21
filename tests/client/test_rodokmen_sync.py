"""Tests of bin/rodokmen-sync (standard library only): python3 -m unittest discover -s tests/client"""
import hashlib
import importlib.machinery
import importlib.util
import io
import json
import os
import socket
import ssl
import tempfile
import threading
import unittest
import urllib.error
from contextlib import redirect_stderr, redirect_stdout
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


def collect(project):
    """The bundle's files; the files made by rodokmen-sync go to a folder next to the project."""
    staging = project.parent / f"{project.name}-staging"
    staging.mkdir(exist_ok=True)
    return quiet(sync.collect, project, staging)


def add_story(project, name, text):
    path = project / "data" / "pribehy" / name
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8")
    return path


SITE = {
    "people": {"jan-novak-1890": {}},
    "records": {"krest_jan_1890": {}},
    "families": {},
    "lines": {"novakovi": {"k": 8}, "rybkovi": {"k": 9}},
}

STORY = """---
title: Příběh Rybků
lead: ''
---
<!-- for Claude: check the date -->
## Rybníkáři
[Jan](/osoba/jan-novak-1890/) and his [baptism](/zdroj/krest_jan_1890/), [the line](/rod/rybkovi/),
[nobody](/osoba/nikdo/) and [a family](/rodina/nikdo-a-nikdo/).
"""


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
            files = collect(make_project(Path(tmp)))
            self.assertEqual(sorted(files), ["full/krest_jan_1890.jpg", "img/krest_jan_1890.jpg", "md/zajimavosti.md", "site.json"])

    def test_collect_publishes_the_stories_of_the_lines(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = make_project(Path(tmp) / "project")
            (project / "web/src/data/site.json").write_text(json.dumps(SITE), encoding="utf-8")
            add_story(project, "rybkovi.md", STORY)
            add_story(project, "neznami.md", "unknown line")
            add_story(project, "Rybkovi-2.md", "not an address")
            add_story(project, "poznamky.txt", "not markdown")
            output = io.StringIO()
            with redirect_stdout(output):
                files = sync.collect(project, Path(tmp))

            self.assertIn("md/pribeh-rybkovi.md", files)
            self.assertEqual([path for path in files if path.startswith("md/pribeh")], ["md/pribeh-rybkovi.md"])
            text = files["md/pribeh-rybkovi.md"].read_text(encoding="utf-8")
            self.assertNotIn("<!--", text)
            self.assertTrue(text.startswith("---\ntitle: Příběh Rybků\n"))
            self.assertNotEqual(files["md/pribeh-rybkovi.md"].parent, project / "data" / "pribehy", "staged, not the source")
            log = output.getvalue()
            self.assertIn("Skipping data/pribehy/neznami.md", log)
            self.assertIn("Skipping data/pribehy/Rybkovi-2.md", log)
            self.assertNotIn("poznamky", log)
            self.assertIn("broken link /osoba/nikdo/ in data/pribehy/rybkovi.md", log)
            self.assertIn("broken link /rodina/nikdo-a-nikdo/", log)
            self.assertNotIn("jan-novak-1890", log)
            self.assertNotIn("krest_jan_1890", log)

    def test_story_of_the_main_line_and_unreadable_story(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = make_project(Path(tmp) / "project")
            site = dict(SITE, config={"pribeh": {"rod": 9}})
            (project / "web/src/data/site.json").write_text(json.dumps(site), encoding="utf-8")
            (project / "web/src/generated/pribeh.md").write_text("main story", encoding="utf-8")
            story = add_story(project, "rybkovi.md", "## Rybníkáři\n")
            os.utime(story, (1_000_000_000, 1_000_000_000))
            output = io.StringIO()
            with redirect_stdout(output):
                files = sync.collect(project, Path(tmp))
            self.assertIn("rybkovi.md is not shown – the line rybkovi has the main story", output.getvalue())
            self.assertEqual(files["md/pribeh-rybkovi.md"].stat().st_mtime, 1_000_000_000, "rsync compares the time")
            self.assertEqual(sync.main_line({"lines": SITE["lines"]}), "novakovi", "line 8 by default")
            self.assertIsNone(sync.main_line({"config": {"maPribeh": False}, "lines": SITE["lines"]}))

            story.write_bytes("Rybníkáři".encode("cp1250"))
            with self.assertRaises(sync.SyncError):
                quiet(sync.collect, project, Path(tmp))

    def test_broken_links(self):
        self.assertEqual(sync.broken_links("[a](/rod/rybkovi/) [b](/rod/x/) [c](/rod/x/#y)", SITE), ["/rod/x"])
        self.assertEqual(sync.broken_links("/osoba/x/", {"people": []}), ["/osoba/x"])
        self.assertEqual(sync.strip_comments("a<!-- x\ny -->\nb <!--z-->c"), "ab c")

    def test_collect_fails_on_broken_site_json(self):
        with tempfile.TemporaryDirectory() as tmp, self.assertRaises(sync.SyncError):
            project = make_project(Path(tmp))
            (project / "web/src/data/site.json").write_text("{", encoding="utf-8")
            sync.collect(project, Path(tmp))

    def test_collect_without_export_fails(self):
        with tempfile.TemporaryDirectory() as tmp, self.assertRaises(sync.SyncError):
            sync.collect(Path(tmp), Path(tmp))

    def test_manifest_lists_hashes_and_sizes(self):
        with tempfile.TemporaryDirectory() as tmp:
            files = collect(make_project(Path(tmp)))
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
            files = collect(project)
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

    def test_deleted_story_is_removed_from_the_website(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = make_project(Path(tmp) / "project")
            (project / "web/src/data/site.json").write_text(json.dumps(SITE), encoding="utf-8")
            story = add_story(project, "rybkovi.md", "## Rybníkáři\n")
            data = Path(tmp) / "data"
            files = collect(project)
            sync.sync_local(files, sync.build_manifest(files), data)
            self.assertTrue((data / "md/pribeh-rybkovi.md").is_file())

            story.unlink()
            files = collect(project)
            result = sync.sync_local(files, sync.build_manifest(files), data)
            self.assertEqual(result["deleted"], ["md/pribeh-rybkovi.md"])
            self.assertFalse((data / "md/pribeh-rybkovi.md").exists())

    def test_stage_and_rsync_command(self):
        with tempfile.TemporaryDirectory() as tmp:
            files = collect(make_project(Path(tmp) / "project"))
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
        self.rollbacks = 0
        # Injected failures, used up one per request: a status (int, or (status, Retry-After)) or "drop"
        # (the connection is closed without a response).
        self.faults = []
        self.requests = []
        self.lock = threading.Lock()
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

            def fault(self):
                """Records the request and answers with an injected failure if there is one."""
                body = self.body()
                with site.lock:
                    site.requests.append((self.command, self.path))
                    fault = site.faults.pop(0) if site.faults else None
                if fault == "drop":
                    self.close_connection = True
                    self.connection.shutdown(socket.SHUT_RDWR)
                    return None
                if fault is not None:
                    status, retry_after = fault if isinstance(fault, tuple) else (fault, None)
                    body_out = json.dumps({"error": "Injected failure"}).encode()
                    self.send_response(status)
                    if retry_after is not None:
                        self.send_header("Retry-After", str(retry_after))
                    self.send_header("Content-Type", "application/json")
                    self.send_header("Content-Length", str(len(body_out)))
                    self.end_headers()
                    self.wfile.write(body_out)
                    return None
                return body

            def authorized(self):
                if self.headers.get("X-Sync-Token") != site.token:
                    self.reply(401, {"error": "Invalid token"})
                    return False
                return True

            def do_POST(self):
                body = self.fault()
                if body is None or not self.authorized():
                    return
                if self.path == "/api/sync/rollback":
                    if site.committed is None:
                        self.reply(409, {"error": "There is no previous release to roll back to"})
                        return
                    site.rollbacks += 1
                    self.reply(200, {"release": "20261112-120000-01234567"})
                    return
                manifest = json.loads(body)
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
                body = self.fault()
                if body is None or not self.authorized():
                    return
                sha = self.path.rsplit("/", 1)[1]
                if hashlib.sha256(body).hexdigest() != sha:
                    self.reply(422, {"error": "Uploaded content does not match its hash"})
                    return
                with site.lock:
                    site.stored.add(sha)
                self.reply(201, {"stored": sha})

        self.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        self.url = f"http://127.0.0.1:{self.server.server_port}"
        threading.Thread(target=self.server.serve_forever, kwargs={"poll_interval": 0.05}, daemon=True).start()

    def close(self):
        self.server.shutdown()
        self.server.server_close()


class ApiTargetTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.files = collect(make_project(Path(self.tmp.name)))
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
        with self.assertRaisesRegex(sync.SyncError, "Cannot reach .*gave up after 5 attempts"):
            quiet(sync.Api("http://127.0.0.1:9", "x", sleep=lambda s: None).plan, self.manifest)


class RollbackTest(unittest.TestCase):
    def setUp(self):
        self.site = FakeSite()
        self.tmp = tempfile.TemporaryDirectory()
        self.project = make_project(Path(self.tmp.name))

    def tearDown(self):
        self.site.close()
        self.tmp.cleanup()

    def main(self, *args):
        out, err = io.StringIO(), io.StringIO()
        with redirect_stdout(out), redirect_stderr(err):
            code = sync.main(["-p", str(self.project), "--token", "secret", *args], environ={})
        return code, out.getvalue(), err.getvalue()

    def test_rollback_over_the_api_without_exporting(self):
        self.site.committed = {"files": {}}
        code, out, _ = self.main("--target", self.site.url, "--rollback")
        self.assertEqual(code, 0)
        self.assertEqual(self.site.rollbacks, 1)
        self.assertEqual(self.site.requests, [("POST", "/api/sync/rollback")])
        self.assertIn("release 20261112-120000-01234567 again", out)

    def test_rollback_error_is_reported(self):
        code, _, err = self.main("--target", self.site.url, "--rollback")
        self.assertEqual(code, 1)
        self.assertIn("HTTP 409 – There is no previous release to roll back to", err)

    def test_rollback_is_not_repeated(self):
        # A rollback whose answer was lost must not be sent again: the second one would fail or go a step further.
        self.site.committed = {"files": {}}
        self.site.faults = ["drop"]
        code, _, err = self.main("--target", self.site.url, "--rollback")
        self.assertEqual(code, 1)
        self.assertIn("connection failed", err)
        self.assertEqual(self.site.requests, [("POST", "/api/sync/rollback")])

    def test_rollback_needs_the_api(self):
        code, _, err = self.main("--target", self.tmp.name, "--rollback")
        self.assertEqual(code, 1)
        self.assertIn("only with the website's API", err)

    def test_rollback_has_no_dry_run(self):
        code, _, err = self.main("--target", self.site.url, "--rollback", "--dry-run")
        self.assertEqual(code, 1)
        self.assertIn("cannot be combined with --dry-run", err)
        self.assertEqual(self.site.requests, [])


class RetryTest(unittest.TestCase):
    """Lost connections and temporary server errors are retried; real errors are reported at once."""

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.files = collect(make_project(Path(self.tmp.name)))
        self.manifest = sync.build_manifest(self.files)
        self.site = FakeSite()
        self.waits = []
        self.api = sync.Api(self.site.url, "secret", sleep=self.waits.append)

    def tearDown(self):
        self.site.close()
        self.tmp.cleanup()

    def sync(self, jobs=sync.JOBS):
        return quiet(sync.sync_api, self.files, self.manifest, self.api, jobs=jobs)

    def test_temporary_errors_and_dropped_connections_are_retried(self):
        self.site.faults = [503, "drop", 502]
        self.assertEqual(self.sync(jobs=1), {"uploaded": 4, "release": "20261113-120000-abcdef12"})
        self.assertEqual(self.waits, [2.0, 4.0, 8.0])
        self.assertEqual(self.site.committed["files"], self.manifest["files"])

    def test_retry_after_is_respected(self):
        self.site.faults = [(429, 7)]
        self.sync()
        self.assertEqual(self.waits, [7.0])

    def test_too_long_retry_after_is_not_waited_for(self):
        self.site.faults = [(429, 900)]
        with self.assertRaisesRegex(sync.SyncError, "HTTP 429"):
            self.sync()
        self.assertEqual(self.waits, [])
        self.assertEqual(len(self.site.requests), 1)

    def test_gives_up_after_the_last_retry(self):
        self.site.faults = [500] * 10
        with self.assertRaisesRegex(sync.SyncError, r"HTTP 500 .*gave up after 5 attempts"):
            self.sync()
        self.assertEqual(len(self.site.requests), 5)
        self.assertIsNone(self.site.committed)

    def test_client_errors_are_not_retried(self):
        self.site.faults = [400]
        with self.assertRaisesRegex(sync.SyncError, "HTTP 400 – Injected failure"):
            self.sync()
        self.assertEqual(len(self.site.requests), 1)

    def test_parallel_uploads_send_every_file_once(self):
        self.assertEqual(self.sync(jobs=3)["uploaded"], 4)
        uploads = [path for method, path in self.site.requests if method == "PUT"]
        self.assertEqual(sorted(uploads), sorted(f"/api/sync/file/{f['sha256']}" for f in self.manifest["files"].values()))

    def test_failed_upload_stops_before_the_commit(self):
        self.site.faults = [None, 413]
        with self.assertRaisesRegex(sync.SyncError, "HTTP 413"):
            self.sync(jobs=1)
        self.assertIsNone(self.site.committed)
        self.assertFalse(any(method == "POST" and path.endswith("/commit") for method, path in self.site.requests))

    def test_failed_upload_stops_the_retries_of_the_others(self):
        self.api.stopped.set()
        self.site.faults = [503]
        with self.assertRaisesRegex(sync.SyncError, "HTTP 503"):
            quiet(self.api.plan, self.manifest)
        self.assertEqual(self.waits, [])

    def test_rejected_certificate_is_not_retried(self):
        class Opener:
            def open(self, request, timeout):
                raise urllib.error.URLError(ssl.SSLCertVerificationError("certificate verify failed"))

        api = sync.Api("https://example.invalid", "x", opener=Opener(), sleep=self.waits.append)
        with self.assertRaisesRegex(sync.SyncError, "certificate verify failed"):
            api.plan(self.manifest)
        self.assertEqual(self.waits, [])


class MainTest(unittest.TestCase):
    def test_publishes_last_export_into_a_local_folder(self):
        with tempfile.TemporaryDirectory() as tmp:
            project = make_project(Path(tmp) / "project")
            data = Path(tmp) / "data"
            code = quiet(sync.main, ["-p", str(project), "--target", str(data), "--no-export"], environ={})
            self.assertEqual(code, 0)
            self.assertTrue((data / "site.json").is_file())

    def test_jobs_must_be_positive(self):
        with redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()), self.assertRaises(SystemExit):
            sync.main(["--jobs", "0"], environ={})

    def test_error_without_target(self):
        with tempfile.TemporaryDirectory() as tmp, redirect_stdout(io.StringIO()):
            err = io.StringIO()
            with redirect_stderr(err):
                self.assertEqual(sync.main(["-p", tmp, "--no-export"], environ={}), 1)
            self.assertIn("No target", err.getvalue())


if __name__ == "__main__":
    unittest.main()
