# pokemon-go - Pokemon Go API PHP Library

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.0-8892BF.svg)](https://secure.php.net)
[![Latest Stable Version][ico-githubversion]][link-releases]
[![Total Downloads][ico-downloads]][link-packagist]
[![PHP7 Ready](https://img.shields.io/badge/PHP7-ready-green.svg)][link-packagist]

This library aims to provide the tools to communicate with the Pokemon Go API.
It is still work in progress.

This package is compliant with [PSR-1], [PSR-2] and [PSR-4]. If you notice compliance oversights,
please send a patch via pull request.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

## Install

Via Composer

``` bash
$ composer require drdelay/pokemon-go
```

## Usage

``` php
$auth = (new \DrDelay\PokemonGo\Auth\PtcAuth())
    ->setCredentials('PTCUser', 'PTCPass');
// Check the folder ./src/Auth for more Auth implementations

$client = (new \DrDelay\PokemonGo\Client())
    ->setCache(...) // Any PSR-6 compliant cache (Optional for Ptc/GoogleAuth, **necessary** for GoogleOAuth, recommended always)
    ->setAuth($auth) // Anything implementing \DrDelay\PokemonGo\Auth\AuthInterface
    ->setLogger(...); // Optional, any PSR-3 compliant logger

$client->login();

// More to come
```

## Google login

There are currently 2 implementations of the Google login (see [#1](https://github.com/DrDelay/pokemon-go/pull/1)):
- GoogleAuth: You have to provide your Google account credentials
- GoogleOAuth: You do **not** have to provide your Google account credentials, but do the Google OAuth process by entering a code on *google.com/device* (being logged in **there**)

## Google OAuth login

If you login with GoogleOAuth you should catch `\DrDelay\PokemonGo\Auth\DeviceNotVerifiedException` at the login call. It gets raised if a verification on the google website is needed.

See the message, and the `getVerificationUrl` / `getUserCode` methods

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email info@vi0lation.de instead of using the issue tracker.

## Credits

- [DrDelay][link-author]
- [FeroxRev](https://github.com/FeroxRev/Pokemon-Go-Rocket-API)
- [All Contributors][link-contributors]

## See also

- [NicklasWallgren/PokemonGoAPI-PHP](https://github.com/NicklasWallgren/PokemonGoAPI-PHP)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/drdelay/pokemon-go.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-githubversion]: https://poser.pugx.org/drdelay/pokemon-go/v/stable
[ico-downloads]: https://img.shields.io/packagist/dt/drdelay/pokemon-go.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/drdelay/pokemon-go
[link-releases]: https://github.com/DrDelay/pokemon-go/releases
[link-author]: https://github.com/DrDelay
[link-contributors]: ../../contributors
