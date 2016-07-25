<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.md .
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo\Test;

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
     * Test that an auth mechanism needs to be set.
     */
    public function testAuthRequired()
    {
        $this->expectException(\BadMethodCallException::class);
        $client = new Client();
        $client->login();
    }
}
