<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.md .
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo\Request\ApiRequests;

use DrDelay\PokemonGo\Request\AbstractApiRequest;
use POGOProtos\Networking\Requests\RequestType;
use POGOProtos\Networking\Responses\GetPlayerResponse;

/**
 * @method array|GetPlayerResponse[] getResponse(\POGOProtos\Networking\Envelopes\ResponseEnvelope $responseEnvelope)
 */
class GetPlayerRequest extends AbstractApiRequest
{
    /**
     * Constructs a GetPlayerRequest.
     *
     * @return GetPlayerRequest
     */
    public static function factory():GetPlayerRequest
    {
        return parent::getSingle(RequestType::GET_PLAYER, new GetPlayerResponse());
    }
}
