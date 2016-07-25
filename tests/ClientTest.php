<?php

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo\Test;

use DrDelay\PokemonGo\Auth\AuthException;
use DrDelay\PokemonGo\Client;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test the generation of cache keys.
     */
    public function testCacheKey()
    {
        $this->assertSame('DrDelay\PokemonGo_test_testing', Client::cacheKey(['test', 'testing']));
    }

    /**
     * Tests that an auth mechanism needs to be set.
     */
    public function testAuthRequired()
    {
        $this->expectException(AuthException::class);
        $client = new Client();
        $client->login();
    }
}
