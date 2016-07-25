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

interface AuthInterface
{
    /**
     * The auth mechanism the implementation represents.
     *
     * @return string
     */
    public function getAuthType():string;

    /**
     * Get the identifier for the account this tries to login to (most likely a username).
     *
     * @return string
     */
    public function getUniqueIdentifier():string;

    /**
     * Use this AuthInterface to get an access token.
     *
     * @return AccessToken
     *
     * @throws AuthException
     */
    public function invoke(): AccessToken;
}
