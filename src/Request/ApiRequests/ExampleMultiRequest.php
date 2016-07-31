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
use POGOProtos\Networking\Responses\GetHatchedEggsResponse;
use POGOProtos\Networking\Responses\GetPlayerProfileResponse;
use POGOProtos\Networking\Responses\GetPlayerResponse;

class ExampleMultiRequest extends AbstractApiRequest
{
    /**
     * Constructs an ExampleMultiRequest.
     *
     * @return ExampleMultiRequest
     */
    public static function factory():ExampleMultiRequest
    {
        return new static([
            RequestType::GET_PLAYER,
            RequestType::GET_HATCHED_EGGS,
            RequestType::GET_PLAYER_PROFILE,
        ], [
            new GetPlayerResponse(),
            new GetHatchedEggsResponse(),
            new GetPlayerProfileResponse(),
        ]);
    }
}
