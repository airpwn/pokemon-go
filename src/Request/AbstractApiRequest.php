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
    /** @var array */
    protected $requests;

    /** @var array|\ProtobufMessage[] */
    protected $responsePrototypes;

    public function __construct(array $requestTypes, array $responsePrototypes)
    {
        $this->requests = $requestTypes;
        $this->responsePrototypes = $responsePrototypes;
    }

    /**
     * Get a single request.
     *
     * @param int|\POGOProtos\Networking\Requests\Request $requestType       A \POGOProtos\Networking\Requests\RequestType const or \POGOProtos\Networking\Requests\Request object
     * @param \ProtobufMessage                            $responsePrototype
     *
     * @return AbstractApiRequest|static
     */
    public static function getSingle($requestType, \ProtobufMessage $responsePrototype):AbstractApiRequest
    {
        return new static([$requestType], [$responsePrototype]);
    }

    public function getRequestTypes():array
    {
        return $this->requests;
    }

    public function getResponses(ResponseEnvelope $responseEnvelope):array
    {
        $responses = [];
        $returnsCount = $responseEnvelope->getReturnsCount();
        for ($i = 0; $i < $returnsCount; ++$i) {
            $response = clone $this->responsePrototypes[$i];
            $response->read($responseEnvelope->getReturns($i));
            $responses[] = $response;
        }

        return $responses;
    }
}
