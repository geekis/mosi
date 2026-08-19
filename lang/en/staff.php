<?php

declare(strict_types=1);

return [

    'sync_aborted' => [
        'subject' => 'Mosi: staff sync aborted',
        'body' => 'The staff sync was aborted because the API returned :current records, down from :previous on the last successful run — more than a 15% drop. No accounts were changed.',
        'action' => 'Check the staff API before the next scheduled run.',
    ],

];
