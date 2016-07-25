<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.md .
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo\Auth;

class AccessToken
{
    /** @var string */
    protected $token;

    /** @var int */
    protected $lifetime;

    /**
     * AccessToken constructor.
     *
     * @param string $token
     * @param int    $lifetime
     */
    public function __construct(string $token, int $lifetime)
    {
        $this->token = $token;
        $this->lifetime = $lifetime;
    }

    /**
     * Get the access token.
     *
     * @return string
     */
    public function getToken():string
    {
        return $this->token;
    }

    /**
     * Get the lifetime.
     *
     * @return int
     */
    public function getLifetime():int
    {
        return $this->lifetime;
    }
}
