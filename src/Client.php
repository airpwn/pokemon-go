<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.md .
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo;

use DrDelay\PokemonGo\Auth\AuthException;
use DrDelay\PokemonGo\Auth\AuthInterface;
use DrDelay\PokemonGo\Cache\CacheAwareInterface;
use DrDelay\PokemonGo\Geography\Coordinate;
use DrDelay\PokemonGo\Http\ClientAwareInterface;
use DrDelay\PokemonGo\Request\ApiRequestInterface;
use DrDelay\PokemonGo\Request\ApiRequests\GetPlayerRequest;
use DrDelay\PokemonGo\Request\Endpoint;
use DrDelay\PokemonGo\Request\RequestBuilder;
use DrDelay\PokemonGo\Request\RequestException;
use Fig\Cache\Memory\MemoryPool;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\StreamWrapper;
use League\Container\Argument\RawArgument;
use League\Container\Container;
use League\Container\Exception\NotFoundException as AliasNotFound;
use POGOProtos\Data\PlayerData;
use POGOProtos\Networking\Envelopes\AuthTicket;
use POGOProtos\Networking\Envelopes\ResponseEnvelope;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client implements CacheAwareInterface, LoggerAwareInterface
{
    const USER_AGENT = 'Niantic App';
    const AUTH_TICKET_CACHE_NAMESPACE = 'AuthTicket';
    const INITIAL_API_URL = 'https://pgorelease.nianticlabs.com/plfe/rpc';

    const AUTH_ERROR_CODE = 102;
    const HANDSHAKE_CODE = 53;
    const UNKNOWN_CODE = 52;

    /** @var Container */
    protected $container;

    /** @var string|null */
    protected $accessToken;

    /** @var string|null */
    protected $authType;

    /** @var Coordinate|null */
    protected $location;

    /** @var string|null */
    protected $authTicketCacheKey;

    /** @var string */
    protected $endpoint = self::INITIAL_API_URL;

    /** @var AuthTicket|null */
    protected $authTicket;

    public function __construct()
    {
        $this->container = new Container();

        $this->container->share(LoggerInterface::class, NullLogger::class);
        $this->container->inflector(LoggerAwareInterface::class)->invokeMethod('setLogger', [LoggerInterface::class]);

        $this->container->share(GuzzleClient::class, GuzzleClient::class)->withArgument(new RawArgument([
            'headers' => [
                'User-Agent' => static::USER_AGENT,
                'Connection' => 'keep-alive',
                'Accept' => '*/*',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'cookies' => true,
            'allow_redirects' => false,
        ]));
        $this->container->inflector(ClientAwareInterface::class)->invokeMethod('setHttpClient', [GuzzleClient::class]);

        $this->container->share(CacheItemPoolInterface::class, MemoryPool::class);
        $this->container->inflector(CacheAwareInterface::class)->invokeMethod('setCache', [CacheItemPoolInterface::class]);
    }

    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     *
     * @return Client|LoggerAwareInterface|$this
     */
    public function setLogger(LoggerInterface $logger):LoggerAwareInterface
    {
        $this->container->share(LoggerInterface::class, $logger);

        return $this;
    }

    /**
     * Sets an auth mechanism.
     *
     * @param AuthInterface $auth
     *
     * @return Client|$this
     */
    public function setAuth(AuthInterface $auth):Client
    {
        $this->container->add(AuthInterface::class, $auth);

        return $this;
    }

    /**
     * Sets a cache.
     *
     * @param CacheItemPoolInterface $cache
     *
     * @return Client|CacheAwareInterface|$this
     */
    public function setCache(CacheItemPoolInterface $cache):CacheAwareInterface
    {
        $this->container->share(CacheItemPoolInterface::class, $cache);

        return $this;
    }

    /**
     * Perform the login.
     *
     * @return PlayerData
     */
    public function login():PlayerData
    {
        /** @var AuthInterface $auth */
        $auth = null;
        try {
            $auth = $this->container->get(AuthInterface::class);
        } catch (AliasNotFound $e) {
            throw new \BadMethodCallException('You need to set an auth mechanism with setAuth', 0, $e);
        }

        /** @var CacheItemPoolInterface $cache */
        $cache = $this->container->get(CacheItemPoolInterface::class);
        /** @var LoggerInterface $logger */
        $logger = $this->container->get(LoggerInterface::class);

        $cacheKey = [$auth->getAuthType(), $auth->getUniqueIdentifier()];
        $item = $cache->getItem(static::cacheKey($cacheKey));
        if ($item->isHit()) {
            $logger->debug('Login Cache hit');
            $this->accessToken = $item->get();
        } else {
            $logger->info('Cache miss -> Doing login');
            $accessToken = $auth->invoke();
            $this->accessToken = $accessToken->getToken();
            $cache->save($item
                ->set($this->accessToken)
                ->expiresAfter($accessToken->getLifetime()));
        }

        array_unshift($cacheKey, static::AUTH_TICKET_CACHE_NAMESPACE);
        $this->authType = $auth->getAuthType();
        $this->authTicketCacheKey = static::cacheKey($cacheKey);

        $logger->notice('Using AccessToken '.$this->accessToken);

        $playerData = $this->initialize();

        $logger->notice('Login completed. Logged in as '.$playerData->getUsername());

        return $playerData;
    }

    /**
     * Prefixes a cache key.
     *
     * @param string[] $keys
     *
     * @return string
     */
    public static function cacheKey(array $keys):string
    {
        array_unshift($keys, __NAMESPACE__);

        return implode('_', $keys);
    }

    /**
     * Set a location.
     *
     * @param Coordinate $coordinate
     *
     * @return Client|$this
     */
    public function setLocation(Coordinate $coordinate):Client
    {
        $this->location = $coordinate;

        return $this;
    }

    /**
     * Initial communication with the API.
     *
     * @return PlayerData
     *
     * @throws RequestException If the request does not succeed
     */
    protected function initialize():PlayerData
    {
        /** @var \POGOProtos\Networking\Responses\GetPlayerResponse $player */
        $player = $this->sendRequest(GetPlayerRequest::factory());

        if (!$player->getSuccess()) {
            throw new RequestException('Initial player data request failed');
        }

        return $player->getPlayerData();
    }

    /**
     * Send a request given by an ApiRequestInterface.
     *
     * @param ApiRequestInterface $request
     *
     * @return \ProtobufMessage
     */
    public function sendRequest(ApiRequestInterface $request):\ProtobufMessage
    {
        return $request->getResponse($this->sendRequestRaw([$request->getRequestType()]));
    }

    /**
     * Get/Set the AuthTicket (Uses cache).
     *
     * @param AuthTicket|null $ticket
     * @param bool|false      $forceClear Clear the ticket
     *
     * @return AuthTicket|null
     */
    public function authTicket(AuthTicket $ticket = null, bool $forceClear = false)
    {
        if ($forceClear) {
            $this->authTicket = null;
            $this->endpoint = static::INITIAL_API_URL;
        }

        $modeSet = (bool) $ticket;
        if (!$modeSet && $this->authTicket) {
            return $this->authTicket;
        }

        /** @var CacheItemPoolInterface $cache */
        $cache = $this->container->get(CacheItemPoolInterface::class);

        if ($forceClear) {
            $cache->deleteItem($this->authTicketCacheKey);
        }

        $authTicketCacheItem = $cache->getItem($this->authTicketCacheKey);
        if ($modeSet) {
            $this->authTicket = $ticket;
            $cache->save($authTicketCacheItem
                ->set(new Endpoint($this->endpoint, $this->authTicket))
                ->expiresAt((new \DateTime())->setTimestamp($this->authTicket->getExpireTimestampMs() / 1000)));
        } else {
            /** @var Endpoint $endpoint */
            $endpoint = $authTicketCacheItem->get();
            if ($endpoint) {
                $this->authTicket = $endpoint->getAuthTicket();
                $this->endpoint = $endpoint->getApiUrl();
            }
        }

        return $this->authTicket;
    }

    /**
     * Sends a request, saves a possibly returned AuthTicket and endpoint.
     *
     * @param array $requestTypes An array of \POGOProtos\Networking\Requests\RequestType consts or \POGOProtos\Networking\Requests\Request objects
     *
     * @return ResponseEnvelope
     *
     * @throws AuthException
     * @throws RequestException
     *
     * @see AuthTicket
     * @see \POGOProtos\Networking\Requests\RequestType
     * @see \POGOProtos\Networking\Requests\Request
     */
    public function sendRequestRaw(array $requestTypes):ResponseEnvelope
    {
        /** @var LoggerInterface $logger */
        $logger = $this->container->get(LoggerInterface::class);

        $authTicket = $this->authTicket();
        $hasAuthTicket = (bool) $authTicket;
        $requestEnvelope = null;
        if ($hasAuthTicket) {
            $logger->debug('Auth ticket exists');
            $requestEnvelope = RequestBuilder::getRequest($authTicket, $this->location, $requestTypes);
        } else {
            $logger->info('No auth ticket, doing initial request');
            $requestEnvelope = RequestBuilder::getInitialRequest($this->accessToken, $this->authType, $this->location, $requestTypes);
        }

        /** @var GuzzleClient $client */
        $client = $this->container->get(GuzzleClient::class);

        $response = $client->post($this->endpoint, ['body' => $requestEnvelope->toProtobuf()]);
        $responseEnv = new ResponseEnvelope(StreamWrapper::getResource($response->getBody()));
        $responseCode = $responseEnv->getStatusCode();

        if ($responseCode == static::AUTH_ERROR_CODE) {
            if (!$hasAuthTicket) {
                throw new AuthException('Received AUTH_ERROR_CODE in initial request');
            }

            $logger->warning('Received Auth error, trying to obtain a new AuthTicket');
            $this->authTicket(null, true);

            return $this->sendRequestRaw($requestTypes);
        }

        if ($responseCode == static::UNKNOWN_CODE) {
            throw new RequestException('Server responded with "unknown" status code '.static::UNKNOWN_CODE);
        }
        $resend = false;

        $apiUrl = $responseEnv->getApiUrl();
        if ($apiUrl) {
            $apiUrl = sprintf('https://%s/rpc', $apiUrl);
            if ($apiUrl != $this->endpoint) {
                $logger->info('Received new Api URL '.$apiUrl);
                $this->endpoint = $apiUrl;
                $resend = true;
            }
        }

        /** @var AuthTicket|null $authTicket */
        $authTicket = $responseEnv->getAuthTicket();
        if ($authTicket) {
            $logger->info('Received AuthTicket');
            $this->authTicket($authTicket);
            $resend = true;
        }

        if ($resend || $responseCode == static::HANDSHAKE_CODE) {
            $logger->debug('Resending request');

            return $this->sendRequestRaw($requestTypes);
        }

        if (!$hasAuthTicket && $responseCode != static::HANDSHAKE_CODE) {
            throw new RequestException('Did not receive Handshake from server');
        }

        return $responseEnv;
    }
}
