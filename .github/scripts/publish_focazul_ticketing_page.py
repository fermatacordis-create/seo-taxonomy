#!/usr/bin/env python3
"""Publish only the designated TaxoCommerce Ticketing information page in Focazul.

Uses WordPress's native authenticated REST API. Never modifies other pages,
settings, or other WordPress sites. Fails closed if login or page identity
cannot be verified. Content remains editable in the WordPress page editor.
"""
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

HOSTS = {"asociacionfocazul.com", "www.asociacionfocazul.com"}
BASE = "https://asociacionfocazul.com/"
SLUG = "taxocommerce-ticketing"
TITLE = "TaxoCommerce Ticketing — Gestión de tickets y solicitudes"
SOURCE = Path(__file__).resolve().parents[2] / "docs/focazul-taxocommerce-ticketing.html"


class SameSiteRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, url):
        parsed = urllib.parse.urlparse(url)
        if parsed.scheme != "https" or parsed.hostname not in HOSTS:
            raise RuntimeError("Focazul redirected to an unauthorized site or insecure scheme")
        return super().redirect_request(request, response, code, message, headers, url)


class SiteClient:
    def __init__(self):
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
            SameSiteRedirect(),
        )

    def request(self, path, data=None, nonce="", method=None):
        url = urllib.parse.urljoin(BASE, path)
        headers = {"User-Agent": "TaxoCommerce-Focazul-PagePublisher/1.0"}
        if nonce:
            headers["X-WP-Nonce"] = nonce
        if isinstance(data, dict):
            data = json.dumps(data, ensure_ascii=False).encode("utf-8")
            headers["Content-Type"] = "application/json; charset=utf-8"
        request = urllib.request.Request(url, data=data, headers=headers, method=method)
        with self.opener.open(request, timeout=35) as response:
            final = urllib.parse.urlparse(response.geturl())
            if final.scheme != "https" or final.hostname not in HOSTS:
                raise RuntimeError("Focazul request left the permitted HTTPS host")
            return response.read(4 * 1024 * 1024).decode("utf-8", errors="replace")

    def login(self, username, password):
        self.request("wp-login.php")  # Obtain WordPress test cookie.
        form = urllib.parse.urlencode({
            "log": username, "pwd": password, "wp-submit": "Log In",
            "redirect_to": urllib.parse.urljoin(BASE, "wp-admin/"),
            "testcookie": "1",
        }).encode()
        self.request("wp-login.php", data=form)
        nonce = self.request("wp-admin/admin-ajax.php?action=rest-nonce").strip()
        if not re.fullmatch(r"[0-9a-f]{10}", nonce):
            raise RuntimeError("Focazul login did not yield an authenticated WordPress REST nonce")
        return nonce


def save_page(client, nonce, content):
    existing = json.loads(client.request(
        "wp-json/wp/v2/pages?context=edit&slug=" + SLUG + "&per_page=2",
        nonce=nonce,
    ))
    if not isinstance(existing, list) or len(existing) > 1:
        raise RuntimeError("Ambiguous Ticketing page search; refusing changes")
    payload = {"title": TITLE, "content": content, "status": "publish"}
    if existing:
        current = existing[0]
        current_title = html.unescape(str(current.get("title", {}).get("raw", "")))
        if str(current.get("slug")) != SLUG or not current_title.startswith("TaxoCommerce Ticketing"):
            raise RuntimeError("Existing page does not belong to Ticketing; refusing overwrite")
        page_id = int(current["id"])
        result = json.loads(client.request("wp-json/wp/v2/pages/" + str(page_id), payload, nonce, method="POST"))
        action = "UPDATED"
    else:
        payload["slug"] = SLUG
        result = json.loads(client.request("wp-json/wp/v2/pages", payload, nonce, method="POST"))
        page_id = int(result["id"])
        action = "CREATED"
    confirmed = json.loads(client.request("wp-json/wp/v2/pages/" + str(page_id) + "?context=edit", nonce=nonce))
    if confirmed.get("slug") != SLUG or confirmed.get("status") != "publish":
        raise RuntimeError("Focazul page was not verified as published")
    saved = confirmed.get("content", {}).get("raw", "")
    if "Atención al cliente y gestión de solicitudes" not in saved:
        raise RuntimeError("WordPress changed the critical page body during save")
    link = confirmed.get("link", "")
    if not link.startswith("https://") or urllib.parse.urlparse(link).hostname not in HOSTS:
        raise RuntimeError("Unexpected published Ticketing page URL")
    print(f"{action} Focazul Ticketing page #{page_id}: {link}", flush=True)
    return link


def main():
    username = os.environ.get("TC_FOCAZUL_WP_USER", "")
    password = os.environ.get("TC_FOCAZUL_WP_PASSWORD", "")
    if not username or not password:
        raise RuntimeError("Focazul WordPress credentials unavailable")
    content = SOURCE.read_text(encoding="utf-8")
    if len(content.encode("utf-8")) > 160000 or "TaxoCommerce Ticketing" not in content:
        raise ValueError("Invalid or excessive Ticketing marketing page")
    client = SiteClient()
    nonce = client.login(username, password)
    save_page(client, nonce, content)


if __name__ == "__main__":
    try:
        main()
    except (urllib.error.URLError, ValueError, RuntimeError, KeyError, OSError) as exc:
        # Only the error class is printed for HTTP failures: never leak cookies or credentials.
        if isinstance(exc, urllib.error.HTTPError):
            print(f"::error::Focazul page publication HTTP {exc.code}", file=sys.stderr)
        else:
            print("::error::Focazul page publication failed: " + str(exc), file=sys.stderr)
        sys.exit(1)
