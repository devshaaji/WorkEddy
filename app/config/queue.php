<?php

use WorkEddy\Helpers\WorkerContract;

return [
    'driver'  => getenv('QUEUE_DRIVER') ?: 'redis',
    'default' => getenv('WORKER_QUEUE') ?: WorkerContract::videoQueueName(),
];
