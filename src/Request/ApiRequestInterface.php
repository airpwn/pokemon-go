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

interface ApiRequestInterface
{
    /**
     * ApiRequestInterface constructor.
     *
     * @param array                    $requestTypes       An array of \POGOProtos\Networking\Requests\RequestType consts or \POGOProtos\Networking\Requests\Request objects
     * @param array|\ProtobufMessage[] $responsePrototypes
     *
     * @see \POGOProtos\Networking\Requests\RequestType
     * @see \POGOProtos\Networking\Requests\Request
     */
    public function __construct(array $requestTypes, array $responsePrototypes);

    /**
     * Get the request types.
     *
     * @return array An array of \POGOProtos\Networking\Requests\RequestType consts or \POGOProtos\Networking\Requests\Request objects
     */
    public function getRequestTypes():array;

    /**
     * Get the Protobuf messages of this request's response.
     *
     * @param ResponseEnvelope $response
     *
     * @return array|\ProtobufMessage[]
     */
    public function getResponses(ResponseEnvelope $response):array;
}
