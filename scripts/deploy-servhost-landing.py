#!/usr/bin/env python3
"""Publish tracked landing assets only to its dedicated ServHost document root."""
import argparse
import ftplib
import hashlib
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import ssl
import subprocess
import sys
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parent.parent
REMOTE_ROOT = "/public_html/equipamentos.venezapiscinas.com.br"
ORIGIN = "https://equipamentos.venezapiscinas.com.br"
HOST = "rv2.servhost.com.br"
PAGES = ("index.html", "posts.html", "font-showcase.html", "robots.txt", "sitemap.xml", ".htaccess")
ASSET_DIRS = ("assets/css/", "assets/data/", "assets/images/", "assets/js/", "assets/videos/")
EXTENSIONS = {".css", ".js", ".json", ".png", ".jpg", ".jpeg", ".webp", ".gif", ".svg", ".ico", ".avif", ".mp4", ".webm"}


def approved(name):
    path = PurePosixPath(name)
    return (not path.is_absolute() and ".." not in path.parts
            and not any(c in name for c in "\r\n\0\\")
            and (name in PAGES or (name.startswith(ASSET_DIRS)
                 and path.suffix.lower() in EXTENSIONS
                 and not any(part.startswith(".") for part in path.parts)
                 and "-original" not in path.stem.lower())))


def production_files(root=ROOT):
    root = Path(root).resolve()
    tracked = subprocess.check_output(["git", "-C", str(root), "ls-files", "-z"]).decode().split("\0")
    names = {name for name in tracked if name and approved(name)}
    if not set(PAGES).issubset(names):
        raise ValueError("Required landing pages/configuration are not tracked")
    files = []
    # Assets first; entrypoint last. This is not an all-files transaction.
    for name in sorted(names - {"index.html"}) + ["index.html"]:
        path = root / name
        if (not path.is_file() or not path.stat().st_size
                or any(p.is_symlink() for p in (path, *path.parents))
                or not path.resolve().is_relative_to(root)):
            raise ValueError("Missing, empty or unsafe tracked landing file")
        files.append((name, path))
    return files


def stage_pages(destination, root=ROOT):
    """Build a fresh Pages artifact from the same tracked public allowlist."""
    files = [(name, source) for name, source in production_files(root) if name != ".htaccess"]
    output = Path(destination)
    if any(path.is_symlink() for path in (output, *output.parents)):
        raise ValueError("Unsafe Pages staging directory")
    # Never reuse a directory: stale documentation/untracked files must not survive.
    output.mkdir(parents=True, exist_ok=False)
    for name, source in files:
        target = output / name
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source, target)
    return len(files)


class ReusingTLS(ftplib.FTP_TLS):
    def ntransfercmd(self, cmd, rest=None):
        conn, size = ftplib.FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            conn = self.context.wrap_socket(conn, server_hostname=self.host, session=self.sock.session)
        return conn, size


def upload(ftp, files, release):
    if not re.fullmatch(r"[a-f0-9]{40}-[a-f0-9]{32}", release):
        raise ValueError("Invalid landing release identifier")
    # Fail if the FTP account does not reach this exact, already-created root.
    ftp.cwd(REMOTE_ROOT)
    directories = {REMOTE_ROOT}
    for name, source in files:
        if not approved(name):
            raise ValueError("Destination outside landing publication scope")
        destination = REMOTE_ROOT + "/" + name
        parent = PurePosixPath(destination).parent
        for directory in (*reversed(parent.parents), parent):
            directory = str(directory)
            if not directory.startswith(REMOTE_ROOT + "/") or directory in directories:
                continue
            try:
                ftp.mkd(directory)
            except ftplib.error_perm as error:
                if not str(error).startswith("550"):
                    raise
                ftp.cwd(directory)  # distinguish existing directory from denied mkdir
                ftp.cwd(REMOTE_ROOT)
            directories.add(directory)
        temporary = destination + ".deploy-" + release
        with source.open("rb") as stream:
            ftp.storbinary("STOR " + temporary, stream)
        ftp.voidcmd("TYPE I")
        if ftp.size(temporary) != source.stat().st_size:
            raise RuntimeError("Incomplete upload; destination was not promoted")
        ftp.rename(temporary, destination)


def verify_live(files, release):
    expected = dict(files)
    for name in ("index.html", "posts.html", "assets/css/styles.css", "assets/images/brand/logo.png", "robots.txt", "sitemap.xml"):
        url = ORIGIN + ("/" if name == "index.html" else "/" + name) + "?release=" + release
        request = urllib.request.Request(url, headers={"Cache-Control": "no-cache"})
        with urllib.request.urlopen(request, timeout=25) as response:
            data = response.read(2 * 1024 * 1024 + 1)
            if response.status != 200 or response.url.split("?", 1)[0] != url.split("?", 1)[0]:
                raise RuntimeError("Landing HTTPS URL verification failed")
        if hashlib.sha256(data).digest() != hashlib.sha256(expected[name].read_bytes()).digest():
            raise RuntimeError("Published landing differs from this commit")
        print("Verified HTTPS and SHA-256: " + name, flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument("--validate-only", action="store_true")
    modes.add_argument("--stage-pages", metavar="DIRECTORY", help="stage a fresh Pages artifact without network access")
    args = parser.parse_args()
    if args.stage_pages is not None:
        count = stage_pages(args.stage_pages)
        print(f"Staged {count} tracked public landing files for GitHub Pages")
        return
    files = production_files()
    if args.validate_only:
        print(f"Validated {len(files)} landing files; destination {REMOTE_ROOT}")
        return
    sha = os.environ.get("GITHUB_SHA", "")
    if not re.fullmatch(r"[a-f0-9]{40}", sha):
        raise ValueError("GITHUB_SHA must identify the published commit")
    if subprocess.check_output(["git", "-C", str(ROOT), "rev-parse", "HEAD"], text=True).strip() != sha:
        raise ValueError("Checkout differs from workflow commit")
    username = os.environ.get("SERVHOST_FTP_USERNAME", "")
    password = os.environ.get("SERVHOST_FTP_PASSWORD", "")
    host = os.environ.get("SERVHOST_FTP_HOST", "") or HOST
    if not username or not password or not re.fullmatch(r"[a-zA-Z0-9.-]+", host):
        raise ValueError("Missing secrets or invalid FTPS host")
    release = sha + "-" + uuid.uuid4().hex
    print(f"Publishing {len(files)} landing files to {host}{REMOTE_ROOT}; one FTPS session, no retry.", flush=True)
    with ReusingTLS(context=ssl.create_default_context(), timeout=45) as ftp:
        ftp.connect(host, 21)
        ftp.login(username, password)
        ftp.prot_p()
        ftp.set_pasv(True)
        upload(ftp, files, release)
    verify_live(files, release)
    print("Landing publication confirmed: " + sha, flush=True)


if __name__ == "__main__":
    try:
        main()
    except Exception:
        # FTP responses and exceptions can contain credentials; never echo them.
        print("::error::Landing deployment stopped. Check Actions secrets, FTPS access, dedicated document root and public HTTPS content. No automatic retry; other sites were not targeted.", file=sys.stderr)
        sys.exit(1)
