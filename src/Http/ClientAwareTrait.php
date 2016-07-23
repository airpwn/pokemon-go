<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * (c) DrDelay <info@vi0lation.de>
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo\Http;

use GuzzleHttp\Client;

trait ClientAwareTrait
{
    /** @var Client|null */
    protected $client;

    /**
     * Sets a HTTP client.
     *
     * @param Client $client
     */
    public function setHttpClient(Client $client)
    {
        $this->client = $client;
    }
}
