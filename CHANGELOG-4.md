# Changelog for the versions 4.x

## 4.0.0 unreleased

* Add support for Symfony 8.0
* Drop support for PHP < 8.2
* Drop support for Symfony < 7.3
* Drop support for Monolog < 3.5
* Remove abstract `monolog.activation_strategy.not_found` and `monolog.handler.fingers_crossed.error_level_activation_strategy` service definitions
* Remove `excluded_404s` option, use `excluded_http_codes` instead
* Remove `console_formater_options` option, use `console_formatter_options` instead
* Remove `elasticsearch` type, use `elastica` or `elastic_search` instead
* Remove `sentry` and `raven` types, use a `service` type with [`sentry/sentry-symfony`](https://docs.sentryio/platforms/php/guides/symfony/logs/) instead
* Remove `DebugHandlerPass`
* Remove support for the `DebugHandler`
