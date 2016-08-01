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
use DrDelay\PokemonGo\Cache\CacheAwareTrait;
use DrDelay\PokemonGo\Geography\Coordinate;
use DrDelay\PokemonGo\Http\ClientAwareInterface;
use DrDelay\PokemonGo\Http\ClientAwareTrait;
use DrDelay\PokemonGo\Request\ApiRequestInterface;
use DrDelay\PokemonGo\Request\ApiRequests\GetPlayerRequest;
use DrDelay\PokemonGo\Request\Endpoint;
use DrDelay\PokemonGo\Request\RequestBuilder;
use DrDelay\PokemonGo\Request\RequestException;
use DrDelay\PokemonGo\Request\RequestNeedsResendException;
use Fig\Cache\Memory\MemoryPool;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\StreamWrapper;
use League\Container\Container;
use League\Container\Exception\NotFoundException as AliasNotFound;
use POGOProtos\Data\PlayerData;
use POGOProtos\Networking\Envelopes\AuthTicket;
use POGOProtos\Networking\Envelopes\RequestEnvelope;
use POGOProtos\Networking\Envelopes\ResponseEnvelope;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client implements CacheAwareInterface, ClientAwareInterface, LoggerAwareInterface
{
    use CacheAwareTrait {
        setCache as setCacheTrait;
    }
    use ClientAwareTrait {
        setHttpClient as setHttpClientTrait;
    }
    use LoggerAwareTrait {
        setLogger as setLoggerTrait;
    }

    const USER_AGENT = 'Niantic App';
    const AUTH_TICKET_CACHE_NAMESPACE = 'AuthTicket';
    const INITIAL_API_URL = 'https://pgorelease.nianticlabs.com/plfe/rpc';
    const MAX_REQUEST_RETRIES = 2;

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

        $this->setLogger(new NullLogger());
        $this->container->inflector(LoggerAwareInterface::class)->invokeMethod('setLogger', [LoggerInterface::class]);

        $this->setHttpClient(new GuzzleClient([
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

        $this->setCache(new MemoryPool());
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
        $this->setLoggerTrait($logger);

        return $this;
    }

    /**
     * Sets a HTTP client.
     *
     * @param GuzzleClient $client
     *
     * @return Client|ClientAwareInterface|$this
     */
    public function setHttpClient(GuzzleClient $client):ClientAwareInterface
    {
        $this->container->share(GuzzleClient::class, $client);

        return $this->setHttpClientTrait($client);
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

        return $this->setCacheTrait($cache);
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

        $cacheKey = [$auth->getAuthType(), $auth->getUniqueIdentifier()];
        $item = $this->cache->getItem(static::cacheKey($cacheKey));
        if ($item->isHit()) {
            $this->logger->debug('Login Cache hit');
            $this->accessToken = $item->get();
        } else {
            $this->logger->info('Cache miss -> Doing login');
            $accessToken = $auth->invoke();
            $this->accessToken = $accessToken->getToken();
            $this->cache->save($item
                ->set($this->accessToken)
                ->expiresAfter($accessToken->getLifetime()));
        }

        array_unshift($cacheKey, static::AUTH_TICKET_CACHE_NAMESPACE);
        $this->authType = $auth->getAuthType();
        $this->authTicketCacheKey = static::cacheKey($cacheKey);

        $this->logger->notice('Using AccessToken '.$this->accessToken);

        $playerData = $this->initialize();

        $this->logger->notice('Login completed. Logged in as '.$playerData->getUsername());

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
        $player = current($this->sendRequest(GetPlayerRequest::factory()));

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
     * @return array|\ProtobufMessage[]
     */
    public function sendRequest(ApiRequestInterface $request):array
    {
        return $request->getResponses($this->sendRequestRaw($request->getRequestTypes()));
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

        if ($forceClear) {
            $this->cache->deleteItem($this->authTicketCacheKey);
        }

        $authTicketCacheItem = $this->cache->getItem($this->authTicketCacheKey);
        if ($modeSet) {
            $this->authTicket = $ticket;
            $this->cache->save($authTicketCacheItem
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
     * Builds the RequestEnvelope.
     *
     * @param array $requestTypes An array of \POGOProtos\Networking\Requests\RequestType consts or \POGOProtos\Networking\Requests\Request objects
     *
     * @return RequestEnvelope
     */
    protected function createRequestEnvelope(array $requestTypes):RequestEnvelope
    {
        $authTicket = $this->authTicket();
        if ($authTicket) {
            $this->logger->debug('Auth ticket exists');

            return RequestBuilder::getRequest($authTicket, $this->location, $requestTypes);
        } else {
            $this->logger->info('No auth ticket, doing initial request');

            return RequestBuilder::getInitialRequest($this->accessToken, $this->authType, $this->location, $requestTypes);
        }
    }

    /**
     * Error/MetaInfo handling, determines resending.
     *
     * @param ResponseEnvelope $responseEnv
     *
     * @return ResponseEnvelope
     *
     * @throws RequestNeedsResendException
     */
    protected function processResponseEnvelope(ResponseEnvelope $responseEnv):ResponseEnvelope
    {
        $responseCode = $responseEnv->getStatusCode();

        if ($responseCode == static::AUTH_ERROR_CODE) {
            throw new RequestNeedsResendException(RequestNeedsResendException::REASON_AUTH);
        }

        if ($responseCode == static::UNKNOWN_CODE) {
            throw new RequestNeedsResendException(RequestNeedsResendException::REASON_UNKNOWN);
        }

        if ($this->processResponseMeta($responseEnv) || $responseCode == static::HANDSHAKE_CODE) {
            throw new RequestNeedsResendException(RequestNeedsResendException::REASON_HANDSHAKE);
        }

        return $responseEnv;
    }

    /**
     * Processes possibly returned AuthTicket / API Url.
     *
     * @param ResponseEnvelope $responseEnv
     *
     * @return bool Whether something has been updated
     */
    protected function processResponseMeta(ResponseEnvelope $responseEnv)
    {
        $resend = false;

        $apiUrl = $responseEnv->getApiUrl();
        if ($apiUrl) {
            $apiUrl = sprintf('https://%s/rpc', $apiUrl);
            if ($apiUrl != $this->endpoint) {
                $this->logger->info('Received new Api URL '.$apiUrl);
                $this->endpoint = $apiUrl;
                $resend = true;
            }
        }

        /** @var AuthTicket|null $authTicket */
        $authTicket = $responseEnv->getAuthTicket();
        if ($authTicket) {
            $this->logger->info('Received AuthTicket');
            $this->authTicket($authTicket);
            $resend = true;
        }

        return $resend;
    }

    /**
     * Sends a request, saves a possibly returned AuthTicket and endpoint.
     *
     * @param array $requestTypes An array of \POGOProtos\Networking\Requests\RequestType consts or \POGOProtos\Networking\Requests\Request objects
     * @param int   $retry        Internal, counts the retries
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
    public function sendRequestRaw(array $requestTypes, $retry = 0):ResponseEnvelope
    {
        $requestEnvelope = $this->createRequestEnvelope($requestTypes);

        $response = $this->client->post($this->endpoint, ['body' => $requestEnvelope->toProtobuf()]);

        try {
            return $this->processResponseEnvelope(new ResponseEnvelope(StreamWrapper::getResource($response->getBody())));
        } catch (RequestNeedsResendException $e) {
            $this->logger->debug('Request needs resending: Code '.$e->getCode());

            switch ($e->getCode()) {
                case RequestNeedsResendException::REASON_AUTH:
                    if (RequestBuilder::isInitialRequest($requestEnvelope)) {
                        throw new AuthException('Received AUTH_ERROR_CODE in initial request');
                    }
                    $this->logger->warning('Received Auth error, trying to obtain a new AuthTicket');
                    $this->authTicket(null, true);
                    break;
                case RequestNeedsResendException::REASON_HANDSHAKE:
                    $this->logger->info('Received Handshake');
                    break;
                case RequestNeedsResendException::REASON_UNKNOWN:
                    $this->logger->warning('Received "unknown" response code');
                    break;
            }

            if ($retry >= static::MAX_REQUEST_RETRIES) {
                throw new RequestException('Maximum number of retries exceeded', 0, $e);
            }

            return $this->sendRequestRaw($requestTypes, ++$retry);
        }
    }
}
