<?php

return [
    'driver'  => getenv('QUEUE_DRIVER') ?: 'redis',
    'default' => getenv('WORKER_QUEUE') ?: 'scan_jobs',
];