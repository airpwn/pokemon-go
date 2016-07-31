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

use DrDelay\PokemonGo\Auth\AuthInterface;
use DrDelay\PokemonGo\Cache\CacheAwareInterface;
use DrDelay\PokemonGo\Cache\CacheAwareTrait;
use DrDelay\PokemonGo\Http\ClientAwareInterface;
use DrDelay\PokemonGo\Http\ClientAwareTrait;
use Fig\Cache\Memory\MemoryPool;
use GuzzleHttp\Client as GuzzleClient;
use League\Container\Container;
use League\Container\Exception\NotFoundException as AliasNotFound;
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

    /** @var Container */
    protected $container;

    /** @var string|null */
    protected $accessToken;

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

        $item = $this->cache->getItem(static::cacheKey([$auth->getAuthType(), $auth->getUniqueIdentifier()]));
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

        $this->logger->notice('Using AccessToken '.$this->accessToken);
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
}
