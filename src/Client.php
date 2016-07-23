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
use DrDelay\PokemonGo\Http\ClientAwareInterface;
use Fig\Cache\Memory\MemoryPool;
use GuzzleHttp\Client as GuzzleClient;
use League\Container\Argument\RawArgument;
use League\Container\Container;
use League\Container\Exception\NotFoundException as AliasNotFound;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client implements LoggerAwareInterface
{
    const USER_AGENT = 'Niantic App';

    /** @var Container */
    protected $container;

    /** @var string|null */
    protected $accessToken;

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
    }

    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     *
     * @return Client|$this
     */
    public function setLogger(LoggerInterface $logger):Client
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
     * @return Client|$this
     */
    public function setCache(CacheItemPoolInterface $cache):Client
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

        $logger->notice('Using AccessToken '.$this->accessToken);
    }

    /**
     * Prefixes a cache key.
     *
     * @param string[] $keys
     *
     * @return string
     */
    protected static function cacheKey(array $keys):string
    {
        array_unshift($keys, __NAMESPACE__);

        return implode('_', $keys);
    }
}
