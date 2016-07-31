<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.md .
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo\Request;

use POGOProtos\Networking\Envelopes\AuthTicket;

class Endpoint
{
    /** @var string */
    protected $apiUrl;

    /** @var AuthTicket */
    protected $authTicket;

    public function __construct(string $apiUrl, AuthTicket $authTicket)
    {
        $this->apiUrl = $apiUrl;
        $this->authTicket = $authTicket;
    }

    /**
     * Get the endpoint URL.
     *
     * @return string
     */
    public function getApiUrl():string
    {
        return $this->apiUrl;
    }

    /**
     * Get the AuthTicket.
     *
     * @return AuthTicket
     */
    public function getAuthTicket():AuthTicket
    {
        return $this->authTicket;
    }
}
