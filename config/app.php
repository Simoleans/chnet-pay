<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'America/Caracas',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'bnc' => [
        'client_guid' => env('BNC_CLIENT_GUID', ''),
        'master_key' => env('BNC_MASTER_KEY', ''),
        'base_url' => env('BNC_BASE_URL', ''),
        'phone' => env('BNC_PHONE', ''),
        'client_id' => env('BNC_CLIENT_ID', ''),
        'account' => env('BNC_ACCOUNT', ''),
        'terminal' => env('BNC_TERMINAL', ''),
    ],

    'bnc_bnc2' => [
        'client_guid' => env('BNC_CLIENT_GUID', ''),
        'master_key' => env('BNC_MASTER_KEY', ''),
        'base_url' => env('BNC_BASE_URL', ''),
        'phone' => env('BNC_PHONE_FLA', ''),
        'account' => env('BNC_ACCOUNT_FLA', ''),
        'terminal' => env('BNC_TERMINAL_FLA', ''),
        //'client_id' => env('BNC_CLIENT_ID_FLA', ''),
    ],

    'bdv' => [
        'api_key' => env('API_KEY', ''),
        'base_url' => env('BDV_BASE_URL', ''),
    ],

    // Pago móvil actual: se usa para la empresa 1 de Wispro.
    'payment_mobile' => [
        'name' => env('PM_NAME', ''),
        'banco' => env('PM_BANCO', ''),
        'tlf' => env('PM_TLF', ''),
        'rif' => env('PM_RIF', ''),
    ],

    // Pago móvil nuevo: se usa para la empresa 2 de Wispro.
    'payment_mobile_bnc2' => [
        'name' => env('PM_NAME_BNC2', ''),
        'banco' => env('PM_BANCO_BNC2', ''),
        'tlf' => env('PM_TLF_BNC2', ''),
        'rif' => env('PM_RIF_BNC2', ''),
    ],

    // IDs de empresas de facturación que vienen en invoicing_firm_id.
    'invoicing_firms' => [
        'empresa_1' => env('WISPRO_INVOICING_FIRM_EMPRESA_1', '1f1f8229-0526-4104-819c-c264abe5e727'),//cablehogar
        'empresa_2' => env('WISPRO_INVOICING_FIRM_EMPRESA_2', 'd6db69fd-d5b4-4418-aff9-f662f78d8717'),//cablehogar FLA
    ],

    'bdv' => [
        'name' => env('PM_BDV_NAME', ''),
        'banco' => env('PM_BDV_BANCO', ''),
        'tlf' => env('PM_BDV_TLF', ''),
        'rif' => env('PM_BDV_RIF', ''),
        'base_url' => env('BDV_BASE_URL', ''),
        'api_key' => env('API_KEY', ''),
    ],

    'ipg2' => [
        'client_id' => env('IPG2_CLIENT_ID', ''),
        'client_secret' => env('IPG2_CLIENT_SECRET', ''),
        'base_url' => env('IPG2_BASE_URL', ''),
        'url_api_payments' => env('IPG2_URL_API_PAYMENTS', ''),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

];
