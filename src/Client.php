<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * (c) DrDelay <info@vi0lation.de>
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.
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
use League\Container\Argument\RawArgument;
use League\Container\Container;
use League\Container\Exception\NotFoundException as AliasNotFound;
use POGOProtos\Networking\Envelopes\AuthTicket;
use POGOProtos\Networking\Envelopes\RequestEnvelope;
use POGOProtos\Networking\Envelopes\ResponseEnvelope;
use POGOProtos\Networking\Requests\Messages\DownloadSettingsMessage;
use POGOProtos\Networking\Requests\Messages\GetInventoryMessage;
use POGOProtos\Networking\Requests\Request;
use POGOProtos\Networking\Requests\RequestType;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client implements CacheAwareInterface, LoggerAwareInterface
{
    const USER_AGENT = 'Niantic App';
    const API_URL = 'https://pgorelease.nianticlabs.com/plfe/rpc';

    const DOWNLOAD_SETTINGS_HASH = '4a2e9bc330dae60e7b74fc85b98868ab4700802e';

    /** @var Container */
    protected $container;

    /** @var string|null */
    protected $accessToken;

    /** @var string|null */
    protected $authType;

    /** @var Coordinate|null */
    protected $location;

    /** @var AuthTicket|null */
    protected $authTicket;

    /** @var string|null */
    protected $endpoint;

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
     * @throws AuthException
     */
    public function login()
    {
        /** @var AuthInterface $auth */
        $auth = null;
        try {
            $auth = $this->container->get(AuthInterface::class);
        } catch (AliasNotFound $e) {
            throw new AuthException('You need to set an auth mechanism with setAuth', 0, $e);
        }

        /** @var CacheItemPoolInterface $cache */
        $cache = $this->container->get(CacheItemPoolInterface::class);
        /** @var LoggerInterface $logger */
        $logger = $this->container->get(LoggerInterface::class);

        $item = $cache->getItem(static::cacheKey([$auth->getAuthType(), $auth->getUniqueIdentifier()]));
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

        $this->authType = $auth->getAuthType();

        $logger->notice('Using AccessToken '.$this->accessToken);

        $this->createEndpoint();
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
     * @throws RequestException
     */
    protected function createEndpoint()
    {
        $inventory = new Request();
        $inventory->setRequestType(RequestType::GET_INVENTORY);
        $inventoryMessage = new GetInventoryMessage();
        $inventoryMessage->setLastTimestampMs(0);
        $inventory->setRequestMessage($inventoryMessage->toProtobuf());

        $settings = new Request();
        $settings->setRequestType(RequestType::DOWNLOAD_SETTINGS);
        $settingsMessage = new DownloadSettingsMessage();
        $settingsMessage->setHash(static::DOWNLOAD_SETTINGS_HASH);
        $settings->setRequestMessage($settingsMessage->toProtobuf());

        $request = RequestBuilder::getInitialRequest($this->accessToken, $this->authType, $this->location, [
            RequestType::GET_PLAYER,
            RequestType::GET_HATCHED_EGGS,
            $inventory,
            RequestType::CHECK_AWARDED_BADGES,
            $settings,
        ]);

        $response = $this->sendRequest($request);

        /** @var LoggerInterface $logger */
        $logger = $this->container->get(LoggerInterface::class);

        $apiUrl = $response->getApiUrl();
        if (!$apiUrl) {
            // TODO: Remove Debug:
            var_dump($response->toProtobuf());
            throw new RequestException('No API Url returned');
        }
        $logger->info('Got API Url '.$apiUrl);
        $this->endpoint = $apiUrl;
    }

    /**
     * Sends a request, saves a possibly returned AuthTicket.
     *
     * @param RequestEnvelope $request
     *
     * @return ResponseEnvelope
     *
     * @see AuthTicket
     */
    public function sendRequest(RequestEnvelope $request):ResponseEnvelope
    {
        /** @var LoggerInterface $logger */
        $logger = $this->container->get(LoggerInterface::class);
        /** @var GuzzleClient $client */
        $client = $this->container->get(GuzzleClient::class);

        $logger->debug('Sending request');

        $response = $client->post(static::API_URL, ['body' => $request->toProtobuf()]);
        // TODO: Remove Debug:
        echo (string) $response->getBody();
        $responseEnv = new ResponseEnvelope((string) $response->getBody());
        /** @var AuthTicket|null $authTicket */
        $authTicket = $responseEnv->getAuthTicket();
        if ($authTicket) {
            $logger->info('Received AuthTicket');
            $this->authTicket = $authTicket;
        }

        return $responseEnv;
    }
}
