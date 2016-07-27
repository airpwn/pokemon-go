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
use DrDelay\PokemonGo\Http\RequestBuilder;
use DrDelay\PokemonGo\Http\RequestException;
use Fig\Cache\Memory\MemoryPool;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\StreamWrapper;
use League\Container\Argument\RawArgument;
use League\Container\Container;
use League\Container\Exception\NotFoundException as AliasNotFound;
use POGOProtos\Networking\Envelopes\AuthTicket;
use POGOProtos\Networking\Envelopes\ResponseEnvelope;
use POGOProtos\Networking\Requests\RequestType;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client implements CacheAwareInterface, LoggerAwareInterface
{
    const USER_AGENT = 'Niantic App';
    const AUTH_TICKET_CACHE_NAMESPACE = 'AuthTicket';

    const AUTH_ERROR_CODE = 102;
    const HANDSHAKE_CODE = 53;

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
    protected $endpoint = 'https://pgorelease.nianticlabs.com/plfe/rpc';

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
     */
    public function login()
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

        $this->initialize();

        $logger->notice('Login completed');
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
     */
    protected function initialize()
    {
        $this->sendRequest([RequestType::GET_PLAYER]);

        // TODO: Process response
    }

    /**
     * Sends a request, saves a possibly returned AuthTicket and endpoint.
     *
     * @param array $requestTypes An array of RequestType consts or Request objects
     *
     * @return ResponseEnvelope
     *
     * @throws AuthException
     *
     * @see AuthTicket
     * @see RequestType
     * @see Request
     */
    public function sendRequest(array $requestTypes):ResponseEnvelope
    {
        /** @var CacheItemPoolInterface $cache */
        $cache = $this->container->get(CacheItemPoolInterface::class);
        /** @var LoggerInterface $logger */
        $logger = $this->container->get(LoggerInterface::class);

        $authTicketCacheItem = $cache->getItem($this->authTicketCacheKey);
        $requestEnvelope = null;
        $cachedTicket = $authTicketCacheItem->isHit();
        if ($cachedTicket) {
            $logger->debug('Auth ticket exists');
            $requestEnvelope = RequestBuilder::getRequest($authTicketCacheItem->get(), $this->location, $requestTypes);
        } else {
            $logger->info('No auth ticket, doing initial request');
            $requestEnvelope = RequestBuilder::getInitialRequest($this->accessToken, $this->authType, $this->location, $requestTypes);
        }

        /** @var GuzzleClient $client */
        $client = $this->container->get(GuzzleClient::class);

        $response = $client->post($this->endpoint, ['body' => $requestEnvelope->toProtobuf()]);
        $responseEnv = new ResponseEnvelope(StreamWrapper::getResource($response->getBody()));
        $responseCode = $requestEnvelope->getStatusCode();

        if ($responseCode == static::AUTH_ERROR_CODE) {
            if (!$cachedTicket) {
                throw new AuthException('Received AUTH_ERROR_CODE in initial request');
            }
            $logger->warning('Received Auth error, trying to obtain a new AuthTicket');
            $cache->deleteItem($this->authTicketCacheKey);

            return $this->sendRequest($requestTypes);
        }
        $resend = false;

        $apiUrl = $responseEnv->getApiUrl();
        if ($apiUrl) {
            $apiUrl = sprintf('https://%s/rpc', $apiUrl);
            $logger->info('Received Api URL '.$apiUrl);
            $this->endpoint = $apiUrl;
            $resend = true;
        }

        /** @var AuthTicket|null $authTicket */
        $authTicket = $responseEnv->getAuthTicket();
        if ($authTicket) {
            $logger->info('Received AuthTicket');
            $cache->save($authTicketCacheItem
                ->set($authTicket)
                ->expiresAt((new \DateTime())->setTimestamp($authTicket->getExpireTimestampMs() / 1000)));
            $resend = true;
        }

        if ($resend || $responseCode == static::HANDSHAKE_CODE) {
            $logger->debug('Resending request');

            return $this->sendRequest($requestTypes);
        }

        if (!$cachedTicket && $responseCode != static::HANDSHAKE_CODE) {
            throw new RequestException('Did not receive Handshake from server');
        }

        return $responseEnv;
    }
}
