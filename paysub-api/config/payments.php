<?php

return [
    // A simulated payment must never be enabled silently on a production instance.
    'demo_mode' => env('PAYMENTS_DEMO_MODE', false),
];
