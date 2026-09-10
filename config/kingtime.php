<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    |
    | The first visitor registers and becomes the owner, after which the
    | sign-up page is closed. Set this to true to keep it open for more users
    | on the same installation; every user only sees their own data.
    |
    */

    'allow_registration' => (bool) env('KINGTIME_ALLOW_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Kingtime for Mac
    |--------------------------------------------------------------------------
    |
    | The menu bar app lives in its own repository and is published as GitHub
    | releases. The homepage links the newest disk image, and the app's
    | updater (Sparkle) reads the appcast through kingtime.nl/download.
    |
    */

    'mac' => [
        'repository' => env('KINGTIME_MAC_REPOSITORY', 'sietzekeuning/kingtime-mac'),
        'appcast_url' => env('KINGTIME_MAC_APPCAST_URL', 'https://raw.githubusercontent.com/sietzekeuning/kingtime-mac/main/appcast.xml'),
    ],

];
