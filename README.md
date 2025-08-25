# Laravel Baseline

[![Latest Version on Packagist](https://img.shields.io/packagist/v/limenet/laravel-baseline.svg?style=flat-square)](https://packagist.org/packages/limenet/laravel-baseline)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/limenet/laravel-baseline/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/limenet/laravel-baseline/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/limenet/laravel-baseline/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/limenet/laravel-baseline/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/limenet/laravel-baseline.svg?style=flat-square)](https://packagist.org/packages/limenet/laravel-baseline)

Checks your Laravel installation against a highly opinionated baseline.


## Installation

You can install the package via composer:

```bash
composer require limenet/laravel-baseline
```


You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-baseline-config"
```

## Usage

```json
"post-update-cmd": [
    "@php artisan limenet:laravel-baseline"
],
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Linus Metzler](https://github.com/limenet)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
