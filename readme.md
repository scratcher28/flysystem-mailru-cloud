# Flysystem adapter for the Mail.ru Cloud (mod)

This package contains a [Flysystem](https://flysystem.thephpleague.com/) adapter for Mail.ru Cloud. Under the hood, the [Friday14/mailru-cloud-php](https://github.com/Friday14/mailru-cloud-php) is used.

## Installation

You can install the package via composer:

``` bash
composer require freecod/flysystem-mailru-cloud
```

## Usage

This package used unofficial API client for cloud.mail.ru - [Friday14/mailru-cloud-php](https://github.com/Friday14/mailru-cloud-php)

To initialize the client, enter your login (without @domain), domain (mail.ru, list.ru, etc) and password to work with the cloud.

``` php
use Friday14\Mailru\Cloud;
use Freecod\FlysystemMailRuCloud\MailRuCloudAdapter;

$client = new Cloud('login', 'password', 'mail.ru');

$adapter = new MailRuCloudAdapter($client);

$filesystem = new Filesystem($adapter);
```

## Usage in Laravel

To used this package as driver for Laravel Storage drive, you must make Service Provider for extend storage drivers 

``` php
<?php

namespace App\Providers;

use Freecod\FlysystemMailRuCloud\MailRuCloudAdapter;
use Friday14\Mailru\Cloud;
use League\Flysystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class MailRuCloudServiceProvider extends ServiceProvider
{
    public function boot()
    {
        \Storage::extend('mailru', function ($app, $config) {
            $client = new Cloud(
                $config['login'],
                $config['password'],
                $config['domain']
            );
            
            return new Filesystem(new MailRuCloudAdapter($client));
        });
    }
}
```

Now add this Service Provider to config/app.php in section "providers":

``` php
App\Providers\MailRuCloudServiceProvider::class,
```

Final step - add to config/filesystems.php in section "disks"

``` php
    'mailru' => [
        'driver' => 'mailru',
        'login' => env('MAIL_RU_CLOUD_LOGIN'),
        'domain' => env('MAIL_RU_CLOUD_DOMAIN'),
        'password' => env('MAIL_RU_CLOUD_PASSWORD'),
    ],
```

Usage

``` php
    $data = \Storage::disk('mailru')->files();
```


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.