# pokemon-go - Pokemon Go API PHP Library
This library aims to provide the tools to communicate with the Pokemon Go API.
It is still work in progress.

Install / Use
-------------
~~`composer require drdelay/pokemon-go`~~
```
$auth = (new \DrDelay\PokemonGo\Auth\PtcAuth())
    ->setCredentials('PTCUser', 'PTCPass');
// Check the folder ./src/Auth for more Auth implementations

$client = (new \DrDelay\PokemonGo\Client())
    ->setCache(...) // Any PSR-6 compliant cache (Optional for Ptc, necessary for Google, recommended always)
    ->setAuth($auth) // Anything implementing \DrDelay\PokemonGo\Auth\AuthInterface
    ->setLogger(...); // Optional, any PSR-3 compliant logger

$client->login();

// More to come
```

Google OAuth login
------------------
If you login with GoogleOAuth you should catch `\DrDelay\PokemonGo\Auth\DeviceNotVerifiedException` at the login call. It gets raised if a verification on the google website is needed.

See the message, and the `getVerificationUrl` / `getUserCode` methods

Credits
-------
* FeroxRev/Pokemon-Go-Rocket-API (A lot has been transcoded from there)

License
-------
MIT
