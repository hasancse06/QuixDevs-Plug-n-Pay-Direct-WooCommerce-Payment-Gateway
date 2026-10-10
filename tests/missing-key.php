<?php
putenv('QPD_TEST_MISSING_KEY=1');
require __DIR__ . '/bootstrap.php';
qpd_reset();
$result=(new \QuixDevs\PlugNPayDirect\Gateway())->process_payment(42);
if ($result !== array('result'=>'failure') || $GLOBALS['qpd_requests']) { exit(1); }
echo "Missing verification key: fails before any mocked transport call.\n";
