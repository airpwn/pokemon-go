# pokemon-go - Pokemon Go API PHP Library
This library aims to provide the tools to communicate with the Pokemon Go API.
It is still work in progress.

Install / Use
-------------
~~`composer require drdelay/pokemon-go`~~
```
$auth = (new \DrDelay\PokemonGo\Auth\PtcAuth())
    ->setCredentials('PTCUser', 'PTCPass');

$client = (new \DrDelay\PokemonGo\Client())
    ->setCache(...) // Optional, any PSR-6 compliant cache
    ->setAuth($auth)
    ->setLogger(...); // Optional, any PSR-3 compliant logger

$client->login();

// More to come
```

Credits
-------
* FeroxRev/Pokemon-Go-Rocket-API (A lot has been transcoded from there)

License
-------
MIT
