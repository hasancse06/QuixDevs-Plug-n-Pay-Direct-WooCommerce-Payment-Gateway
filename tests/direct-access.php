<?php
// A direct request must exit before WordPress functions are called.
require dirname(__DIR__) . '/quixdevs-plugnpay-direct.php';
fwrite(STDERR, "Direct access was not blocked.\n");
exit(1);
