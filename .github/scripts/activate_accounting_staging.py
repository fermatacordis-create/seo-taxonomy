#!/usr/bin/env python3
"""Activate only TaxoCommerce Accounting after its explicitly approved STAGING install.

Uses the existing test WordPress user, follows only same-host HTTPS redirects,
and verifies the plugin's active state. Does not modify any other plugin or site.
"""
import html
import http.cookiejar
import os
import re
import urllib.parse
import urllib.request
from html.parser import HTMLParser

BASE = "https://staging.distribuidordeherramientas.es/"
DOMAIN = "staging.distribuidordeherramientas.es"
PLUGIN = "taxocommerce-accounting/taxocommerce-accounting.php"
SLUG = "taxocommerce-accounting"


class LocalRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        target = urllib.parse.urlparse(newurl)
        if target.scheme != "https" or target.hostname != DOMAIN:
            raise RuntimeError("Refusing external/non-HTTPS redirect during STAGING login")
        return super().redirect_request(req, fp, code, msg, headers, newurl)


class PluginRows(HTMLParser):
    def __init__(self):
        super().__init__()
        self.active = False
        self.found = False

    def handle_starttag(self, tag, attrs):
        if tag != "tr":
            return
        fields = dict(attrs)
        if fields.get("data-slug") == SLUG or fields.get("id") == "plugin-" + SLUG:
            self.found = True
            if "active" in fields.get("class", "").split():
                self.active = True


def activate():
    user = os.environ.get("TC_STAGING_WP_USER", "")
    password = os.environ.get("TC_STAGING_WP_PASSWORD", "")
    if not user or not password:
        raise RuntimeError("Cannot activate Accounting: STAGING WordPress test credentials unavailable")
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
        LocalRedirect(),
    )

    def visit(url, data=None):
        request = urllib.request.Request(url, data=data, headers={"User-Agent": "TaxoCommerce-STAGING-Accounting/1.0"})
        with opener.open(request, timeout=25) as response:
            final = urllib.parse.urlparse(response.geturl())
            if final.scheme != "https" or final.hostname != DOMAIN:
                raise RuntimeError("STAGING HTTP response left the authorized domain")
            return response.read(2 * 1024 * 1024).decode("utf-8", errors="replace")

    login_url = urllib.parse.urljoin(BASE, "wp-login.php")
    plugins_url = urllib.parse.urljoin(BASE, "wp-admin/plugins.php")
    visit(login_url)  # WordPress test cookie.
    form = urllib.parse.urlencode({
        "log": user,
        "pwd": password,
        "wp-submit": "Log In",
        "redirect_to": plugins_url,
        "testcookie": "1",
    }).encode("utf-8")
    visit(login_url, form)
    page = visit(plugins_url)
    row = PluginRows()
    row.feed(page)
    if row.active:
        print("PASS: TaxoCommerce Accounting already active on STAGING")
        return
    if not row.found:
        raise RuntimeError("Accounting not found in STAGING plugins list or administrator authentication failed")

    activation_url = None
    for raw in re.findall(r'href=["\']([^"\']+)["\']', page):
        link = html.unescape(raw)
        url = urllib.parse.urljoin(plugins_url, link)
        parts = urllib.parse.urlparse(url)
        query = urllib.parse.parse_qs(parts.query)
        if (
            parts.scheme == "https"
            and parts.hostname == DOMAIN
            and parts.path.endswith("/wp-admin/plugins.php")
            and query.get("action") == ["activate"]
            and query.get("plugin") == [PLUGIN]
            and query.get("_wpnonce")
        ):
            activation_url = url
            break
    if not activation_url:
        raise RuntimeError("Administrator activation nonce/link unavailable for Accounting")
    visit(activation_url)
    current = visit(plugins_url)
    verified = PluginRows()
    verified.feed(current)
    if not verified.active:
        raise RuntimeError("Accounting activation was requested but is not confirmed active on STAGING")
    print("PASS: TaxoCommerce Accounting activated and verified in STAGING plugins list")


if __name__ == "__main__":
    activate()
