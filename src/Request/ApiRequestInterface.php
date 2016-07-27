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
     * @param int|\POGOProtos\Networking\Requests\Request $requestType       A \POGOProtos\Networking\Requests\RequestType const or \POGOProtos\Networking\Requests\Request object
     * @param \ProtobufMessage                            $responsePrototype
     *
     * @see \POGOProtos\Networking\Requests\RequestType
     * @see \POGOProtos\Networking\Requests\Request
     */
    public function __construct($requestType, \ProtobufMessage $responsePrototype);

    /**
     * Get the request type.
     *
     * @return int|\POGOProtos\Networking\Requests\Request
     */
    public function getRequestType();

    /**
     * Get the Protobuf message of this request's response.
     *
     * @param ResponseEnvelope $response
     *
     * @return \ProtobufMessage
     */
    public function getResponse(ResponseEnvelope $response):\ProtobufMessage;
}
