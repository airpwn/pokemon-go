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

use DrDelay\PokemonGo\Auth\AbstractAuth;
use DrDelay\PokemonGo\Auth\AuthException;
use DrDelay\PokemonGo\Auth\AuthInterface;
use DrDelay\PokemonGo\Http\ClientAwareInterface;
use GuzzleHttp\Client as GuzzleClient;
use League\Container\Argument\RawArgument;
use League\Container\Container;
use League\Container\Exception\NotFoundException as AliasNotFound;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client implements LoggerAwareInterface
{
    /** @var Container */
    protected $container;

    /** @var string|null */
    protected $accessToken;

    public function __construct()
    {
        $this->container = new Container();

        $this->container->share(LoggerInterface::class, new NullLogger());
        $this->container->inflector(LoggerAwareInterface::class)->invokeMethod('setLogger', [LoggerInterface::class]);

        $this->container->share(GuzzleClient::class, GuzzleClient::class)->withArgument(new RawArgument([
            'headers' => [
                'User-Agent' => Resources::USER_AGENT,
                'Connection' => 'keep-alive',
                'Accept' => '*/*',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'cookies' => true,
            'allow_redirects' => false,
        ]));
        $this->container->inflector(ClientAwareInterface::class)->invokeMethod('setHttpClient', [GuzzleClient::class]);
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
        if ($auth instanceof AbstractAuth) {
            $auth->setLogger($this->container->get(LoggerInterface::class));
            $auth->setHttpClient($this->container->get(GuzzleClient::class));
        }
        $this->container->add(AuthInterface::class, $auth);

        return $this;
    }

    /**
     * Perform the login.
     *
     * @throws AuthException
     */
    public function login()
    {
        // TODO: Cache Access Token?

        /** @var LoggerInterface $logger */
        $logger = $this->container->get(LoggerInterface::class);
        $logger->debug('Doing login');

        try {
            $this->accessToken = $this->container->get(AuthInterface::class);
        } catch (AliasNotFound $e) {
            throw new AuthException('You need to set an auth mechanism with setAuth', 0, $e);
        }

        $logger->notice('Got AccessToken '.$this->accessToken);
    }
}
