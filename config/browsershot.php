<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Browsershot (invoice PDFs)
    |--------------------------------------------------------------------------
    |
    | Invoice PDFs are rendered by headless Chrome through Puppeteer. Node and
    | Puppeteer must be installed on the machine; point these at them when
    | they are not on the PATH or not in the project's node_modules.
    |
    */

    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),

    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),

    'node_module_path' => env('BROWSERSHOT_NODE_MODULE_PATH', base_path('node_modules')),

    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),

    'timeout_seconds' => (int) env('BROWSERSHOT_TIMEOUT', 60),

];
