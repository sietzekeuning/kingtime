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

];
