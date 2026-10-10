#!/usr/bin/env bash
set -eu
: "${QPD_PLUGIN_CHECK_SITE:?Set QPD_PLUGIN_CHECK_SITE to an explicitly disposable WordPress site.}"
: "${QPD_ALLOW_DISPOSABLE_CHECK:?Set QPD_ALLOW_DISPOSABLE_CHECK=1 for this read-only plugin review.}"
if [ "$QPD_ALLOW_DISPOSABLE_CHECK" != 1 ]; then exit 1; fi
if ! command -v wp >/dev/null; then echo 'WP-CLI unavailable'; exit 2; fi
qpd_root="$(cd "$(dirname "$0")/.." && pwd)"
wp --path="$QPD_PLUGIN_CHECK_SITE" --require="$QPD_PLUGIN_CHECK_SITE/wp-content/plugins/plugin-check/cli.php" plugin check "$qpd_root" --include-experimental
