<?php
// Compatibility entry point for the optional SVG renderer; no payment/runtime dependency.
$node = getenv('QPD_NODE_BINARY') ?: 'node';
$command = escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/render-branding.mjs');
passthru($command, $status);
exit($status);
