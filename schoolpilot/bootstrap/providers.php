<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
];


return [
    App\Providers\AppServiceProvider::class,
        App\Providers\TenancyServiceProvider::class,  // <-- add this
        ];
        