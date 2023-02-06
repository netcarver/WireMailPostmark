# **WireMailPostmark Changelog**

This package uses [Semantic Versioning] and tries to follow the guidelines at [Keep a Changelog].

## 0.5.1 - 2023-02-06

### Fixed

-   Call to undefined function \_()

### Added

-   More sanitisation of values pulled from the postmark stats API

## 0.5.1 - 2023-02-06

### Fixed

-   Failed reads from postmark Status API

## 0.5.0 - 2021-07-23

## Added

-   Server token for sandbox server to be used when site in debug mode.

## 0.4.0 - 2021-07-09

### Added

-   Images to Readme

### Changed

-   Log messages on success/failure

## 0.3.0 - 2021-07-08

### Added

-   Record API hand-off results on email send.
-   Record Postmark email ID in email body to allow later
    follow-up of email actions via the Postmark API.

## 0.2.0 - 2021-07-08

### Added

-   Readme
-   MIT License

### Removed

-   Nifty License

## 0.1.0 - 2021-07-08

### Added

-   Postmark service status checking
-   User's server stat summary

## 0.0.0 - 2021-07-07

-   Initial implementation of module.

[semantic versioning]: https://semver.org/spec/v2.0.0.html
[keep a changelog]: http://keepachangelog.com/en/1.0.0/
