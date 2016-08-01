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

class RequestNeedsResendException extends RequestException
{
    const REASON_AUTH = 1;
    const REASON_HANDSHAKE = 2;
    const REASON_UNKNOWN = 3;

    /**
     * RequestNeedsResendException constructor.
     *
     * @param int $reason See the consts of this class
     */
    public function __construct(int $reason)
    {
        parent::__construct(null, $reason);
    }
}
