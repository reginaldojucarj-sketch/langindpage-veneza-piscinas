#!/usr/bin/env python3
"""Publish only the institutional site's approved files over explicit FTPS."""

import argparse
import hashlib
import os
from pathlib import Path
import re
import subprocess
import sys
import urllib.request


HOST = "rv2.servhost.com.br"
REMOTE_ROOT = "/public_html"
PUBLIC_ORIGIN = "https://www.venezapiscinas.com.br"
FILES = (
    "admin/index.php", "article.php", "artigo.html",
    "assets/css/site.css",
    "assets/images/aquecedor-nautilus.jpg",
    "assets/images/casa-maquinas-polinezia-la-fleur.jpg",
    "assets/images/cliente-amarante.svg", "assets/images/cliente-rio-ave.svg",
    "assets/images/cliente-salinas.svg", "assets/images/cliente-vertical-engenharia.png",
    "assets/images/filtro-nautilus.jpg", "assets/images/gerador-cloro-nautilus.webp",
    "assets/images/gerador-ozonio-panozon.png", "assets/images/iluminacao-led-tholz.webp",
    "assets/images/logo-veneza-piscinas.png", "assets/images/parceira-nautilus.svg",
    "assets/images/parceira-sodramar.png", "assets/images/parceira-syllent.svg",
    "assets/images/piscina-borda-infinita-nova-cruz.jpg",
    "assets/images/piscina-deck-vista-agua.jpg", "assets/images/piscina-iluminacao-noturna.jpg",
    "assets/images/piscina-manta-armada.jpg", "assets/images/piscina-resort-japaratinga.jpg",
    "assets/images/projeto-hidraulico-bim.jpg",
    "assets/js/config.js", "assets/js/content.js", "assets/js/knowledge-article.js",
    "assets/js/knowledge-list.js", "assets/js/site.js", "assets/js/ssr-article.js",
    "conhecimento.html", "conhecimento/index.php", "contato.html", "lib/knowledge.php",
    "produtos.html", "projetos.html", "solucoes.html", "veneza.html",
    ".htaccess", "index.html",
)


def production_files(repo):
    site = Path(repo).resolve() / "site"
    files = []
    for relative in FILES:
        source = site / relative
        if not re.fullmatch(r"[a-zA-Z0-9._/-]+", relative) or ".." in Path(relative).parts:
            raise ValueError("Invalid production path")
        if any(part.is_symlink() for part in (source, *source.parents)):
            raise ValueError(f"Symlink forbidden: {relative}")
        if not source.is_file() or not source.stat().st_size:
            raise ValueError(f"Production file missing or empty: {relative}")
        if not source.resolve().is_relative_to(site.resolve()):
            raise ValueError("Production file escaped site/")
        files.append((relative, source))
    return files


def config_value(value):
    if not value or any(char in value for char in ("\0", "\r", "\n")):
        raise ValueError("Empty or invalid FTPS configuration value")
    return '"' + value.replace("\\", "\\\\").replace('"', '\\"') + '"'


def curl_config(files, username, password, release):
    if not re.fullmatch(r"[a-f0-9]{7,40}", release):
        raise ValueError("Invalid release identifier")
    lines = []
    for index, (relative, source) in enumerate(files):
        if index:
            lines.append("next")
        destination = f"{REMOTE_ROOT}/{relative}"
        temporary = f"{destination}.deploy-{release}"
        lines.extend((
            "user = " + config_value(f"{username}:{password}"),
            "ssl-reqd", "ftp-create-dirs", "silent", "show-error",
            "connect-timeout = 10", "max-time = 45",
            "upload-file = " + config_value(str(source)),
            "url = " + config_value(f"ftp://{HOST}{temporary}"),
            "quote = " + config_value(f"-RNFR {temporary}"),
            "quote = " + config_value(f"-RNTO {destination}"),
            "write-out = " + config_value(f"DEPLOY {index} %{{exitcode}} %{{size_upload}}\\n"),
        ))
    return "\n".join(lines) + "\n"


def confirmed_uploads(output, files):
    records = re.findall(r"^DEPLOY (\d+) (\d+) (\d+)\s*$", output, re.MULTILINE)
    if len(records) != len(files):
        return False
    return all(
        int(index) == expected and int(code) == 0 and int(size) == source.stat().st_size
        for expected, ((index, code, size), (_, source)) in enumerate(zip(records, files))
    )


def verify_live(files, release):
    expected = dict(files)
    for relative in ("index.html", "assets/css/site.css", "assets/images/logo-veneza-piscinas.png"):
        url = f"{PUBLIC_ORIGIN}/{relative}?release={release}"
        request = urllib.request.Request(url, headers={"Cache-Control": "no-cache"})
        with urllib.request.urlopen(request, timeout=20) as response:
            if response.status != 200:
                raise RuntimeError(f"HTTP validation failed: {relative}")
            published = response.read(2 * 1024 * 1024)
        if hashlib.sha256(published).digest() != hashlib.sha256(expected[relative].read_bytes()).digest():
            raise RuntimeError(f"Published content differs: {relative}")
        print(f"Verified HTTP 200 and SHA-256: {relative}", flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--validate-only", action="store_true")
    args = parser.parse_args()
    files = production_files(Path(__file__).resolve().parent.parent)
    if args.validate_only:
        print(f"Validated {len(files)} approved institutional files; destination {REMOTE_ROOT}/")
        return 0

    username = os.environ.get("SERVHOST_FTP_USERNAME", "")
    password = os.environ.get("SERVHOST_FTP_PASSWORD", "")
    if not username or not password:
        raise ValueError("Configure GitHub Actions secrets SERVHOST_FTP_USERNAME and SERVHOST_FTP_PASSWORD")
    release = os.environ.get("GITHUB_SHA", "")
    config = curl_config(files, username, password, release)
    print(f"Publishing {len(files)} institutional files to {HOST}{REMOTE_ROOT}/", flush=True)
    try:
        result = subprocess.run(
            ["curl", "-q", "--fail-early", "--config", "-"],
            input=config, text=True, capture_output=True, timeout=450,
        )
    except subprocess.TimeoutExpired:
        raise RuntimeError("FTPS publication timed out; no automatic retry") from None
    # Never print raw curl stderr/config, which may contain authentication data.
    if result.returncode != 0 or not confirmed_uploads(result.stdout, files):
        raise RuntimeError(f"FTPS publication incomplete (curl {result.returncode}); inspect network/access before rerunning")
    verify_live(files, release)
    print("Institutional site publication confirmed.", flush=True)
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (ValueError, RuntimeError, OSError) as error:
        print(f"Deployment failed: {error}", file=sys.stderr)
        sys.exit(1)
