<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.md .
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo\Http;

use DrDelay\PokemonGo\Geography\Coordinate;
use POGOProtos\Networking\Envelopes\AuthTicket;
use POGOProtos\Networking\Envelopes\RequestEnvelope;
use POGOProtos\Networking\Envelopes\RequestEnvelope_AuthInfo;
use POGOProtos\Networking\Envelopes\RequestEnvelope_AuthInfo_JWT;
use POGOProtos\Networking\Requests\Request;

abstract class RequestBuilder
{
    // TODO: Random?
    const RPC_ID = 1469378659230941192;

    /**
     * Request generation by token and type (Initial).
     *
     * @param string     $accessToken
     * @param string     $authType
     * @param Coordinate $location
     * @param array      $requestTypes An array of RequestType consts
     *
     * @return RequestEnvelope
     *
     * @see RequestType
     */
    public static function getInitialRequest(string $accessToken, string $authType, Coordinate $location, array $requestTypes):RequestEnvelope
    {
        $auth = new RequestEnvelope_AuthInfo();
        $auth->setProvider($authType);
        $token = new RequestEnvelope_AuthInfo_JWT();
        $token->setContents($accessToken);
        $token->setUnknown2(14);
        $auth->setToken($token);

        return static::_request($auth, $location, $requestTypes);
    }

    /**
     * Request generation by AuthTicket.
     *
     * @param AuthTicket $authTicket
     * @param Coordinate $location
     * @param array      $requestTypes An array of RequestType consts
     *
     * @return RequestEnvelope
     *
     * @see RequestType
     */
    public static function getRequest(AuthTicket $authTicket, Coordinate $location, array $requestTypes):RequestEnvelope
    {
        return static::_request($authTicket, $location, $requestTypes);
    }

    /**
     * Generic request generation.
     *
     * @param RequestEnvelope_AuthInfo|AuthTicket $auth
     * @param Coordinate                          $location
     * @param array                               $requestTypes An array of RequestType consts
     *
     * @return RequestEnvelope
     *
     * @see RequestType
     */
    protected static function _request($auth, Coordinate $location, array $requestTypes):RequestEnvelope
    {
        $env = new RequestEnvelope();
        if ($auth instanceof RequestEnvelope_AuthInfo) {
            $env->setAuthInfo($auth);
        } elseif ($auth instanceof AuthTicket) {
            $env->setAuthTicket($auth);
        } else {
            throw new \BadMethodCallException('Auth must be an instance of RequestEnvelope_AuthInfo or AuthTicket');
        }

        $env->setStatusCode(2);
        $env->setRequestId(static::RPC_ID);
        $env->setLatitude($location->getLatitude());
        $env->setLongitude($location->getLongitude());
        $env->setAltitude($location->getAltitude());
        $env->setUnknown12(989);

        $requests = [];
        foreach ($requestTypes as $requestType) {
            if ($requestType instanceof Request) {
                $requests[] = $requestType;
                continue;
            }
            $request = new Request();
            $request->setRequestType($requestType);
            $requests[] = $request;
        }
        $env->addAllRequests($requests);

        return $env;
    }

    /**
     * Converts a float to ulong
     * Requires a 64bit PHP Environment.
     *
     * @param float|int $value
     *
     * @return int
     */
    public static function floatAsUlong($value):int
    {
        assert(PHP_INT_SIZE === 8, '64bit PHP required');

        return unpack('Q', pack('d', $value))[1];
    }
}
