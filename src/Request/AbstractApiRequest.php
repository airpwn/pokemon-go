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

use POGOProtos\Networking\Envelopes\ResponseEnvelope;

class AbstractApiRequest implements ApiRequestInterface
{
    /** @var int|\POGOProtos\Networking\Requests\Request */
    protected $request;

    /** @var \ProtobufMessage */
    protected $responsePrototype;

    public function __construct($requestType, \ProtobufMessage $responsePrototype)
    {
        $this->request = $requestType;
        $this->responsePrototype = $responsePrototype;
    }

    public function getRequestType()
    {
        return $this->request;
    }

    public function getResponse(ResponseEnvelope $responseEnvelope):\ProtobufMessage
    {
        $response = clone $this->responsePrototype;
        $response->read(current($responseEnvelope->getReturnsArray()));

        return $response;
    }
}
