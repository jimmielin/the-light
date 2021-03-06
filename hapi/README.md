# Next Generation Hole Backend (`the-light`)

From -removed-, with love. Godspeed.

`the-light` is designed to power `the-seed` frontend. This backend was developed completely from scratch and all (c) the authors and licensed under the GNU GPL 2.0, excluding later versions.

This backend is developed as a proof-of-concept. It is not designed for any particular website and environmental configurations need to be performed.

**License: GNU GPL 2.0 - all changes must be open-sourced under the same license.**


## Components and dependencies
* PHP 7.4+
* `illuminate/framework`
* `doctrine/dbal` for ORM Schema
* `league/flysystem-cached-adapter` for Cached High-Performance Virtual Filesystem
* `league/flysystem-aws-s3-v3` for Amazon S3
* `matthewbdaly/laravel-azure-storage` for Microsoft Azure Blob
* `league/flysystem-sftp ~1.0` for SFTP Virtual Filesystem
* `phpseclib/phpseclib` for RSA Encryption
* `predis/predis` for Redis
* `rebing/graphql-laravel` for GraphQL API Support
* `rennokki/laravel-eloquent-query-cache` for Model Caching
* `sentry/sentry-laravel` for Error Reporting
* `fruitcake/laravel-cors` for CORS Header Control

### Development Tools
* `mpociot/laravel-apidoc-generator` for API Documentation Generator
* `phpunit/phpunit` for Unit Testing
* `itsgoingd/clockwork` for Kitchen Sink Debugger

## Installation
* Requires **Gateway**. Install `ng-gateway` first.
* Ensure `.env` is correctly configured
* `memcached` is in UNIX socket mode
* `php artisan migrate`

### Upgrading
* `php artisan migrate`
* `php artisan config:cache`
* `php artisan route:cache`
* `php artisan cache:clear`
* `composer dump-autoload`