Laravel Bird Driver
====

A Mail Driver with support for Bird Web API, using the original Laravel API.
This library extends the original Laravel classes, so it uses exactly the same methods.

To use this package required your [Bird Access Key](https://docs.bird.com/api/api-access/api-authorization).
Please make it [Here](https://app.bird.com/settings/access-keys).


### Compatibility

| Laravel   | laravel-bird-driver |
|-----------| ---- |
| 9, 10, 11 | ^4.0 |

# Install (for [Laravel](https://laravel.com/))

Add the package to your composer.json and run composer update.
```json
"require": {
    "foodticket/laravel-bird-driver": "^1.0"
},
```

or installed with composer
```bash
$ composer require foodticket/laravel-bird-driver
```

## Configure

.env
```bash
MAIL_DRIVER=bird
BIRD_API_ACCESS_KEY='YOUR_BIRD_ACCESS_KEY'
BIRD_API_WORKSPACE_ID='YOUR_WORKSPACE_ID'
BIRD_API_CHANNEL_ID='MAIL_CHANNEL_ID'
# Optional: for 7+ laravel projects
MAIL_MAILER=bird 
```

config/services.php (not required from 
Laravel 9+)
```php
    'bird' => [
        'mail' => [
            'access_key' => env('BIRD_API_ACCESS_KEY'),
            'workspace_id' => env('BIRD_API_WORKSPACE_ID'),
            'channel_id' => env('BIRD_API_CHANNEL_ID'),
        ],
    ],
```

config/mail.php
```php
    'mailers' => [
        'bird' => [
            'transport' => 'bird',
        ],
    ],
```
