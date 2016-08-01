<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.md .
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo\Test\Request;

use DrDelay\PokemonGo\Request\RequestBuilder;
use POGOProtos\Networking\Envelopes\AuthTicket;

class RequestBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test the isInitialRequest method.
     */
    public function testInitialRequestTest()
    {
        $initial = RequestBuilder::getInitialRequest('someToken', 'authType', null, []);
        $withTicket = RequestBuilder::getRequest(new AuthTicket(), null, []);
        $this->assertTrue(RequestBuilder::isInitialRequest($initial), 'Initial request');
        $this->assertFalse(RequestBuilder::isInitialRequest($withTicket), 'Request with AuthTicket');
    }
}
