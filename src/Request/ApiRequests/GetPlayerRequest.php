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

class GetPlayerRequest extends AbstractApiRequest
{
    public static function factory():GetPlayerRequest
    {
        return new static(RequestType::GET_PLAYER, new GetPlayerResponse());
    }
}
