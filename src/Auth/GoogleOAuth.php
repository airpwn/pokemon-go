<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.md .
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo\Auth;

use DrDelay\PokemonGo\Cache\CacheAwareInterface;
use DrDelay\PokemonGo\Cache\CacheAwareTrait;
use DrDelay\PokemonGo\Client;
use DrDelay\PokemonGo\Enum\AuthType;
use Fig\Cache\Memory\MemoryPool;
use GuzzleHttp\Exception\RequestException;
use Psr\Cache\CacheItemInterface;
use function GuzzleHttp\json_decode;

class GoogleOAuth extends AbstractAuth implements CacheAwareInterface
{
    use CacheAwareTrait;

    const GOOGLE_OAUTH_DEVICE_CODE_URL = 'https://accounts.google.com/o/oauth2/device/code';
    const GOOGLE_OAUTH_TOKEN_URL = 'https://www.googleapis.com/oauth2/v4/token';
    const GOOGLE_OAUTH_CLIENT_ID = '848232511240-73ri3t7plvk96pj4f85uj8otdat2alem.apps.googleusercontent.com';
    const GOOGLE_OAUTH_CLIENT_SECRET = 'NCjF1TLi2CcY6t5mt0ZveuL7';
    const SCOPE = 'openid email https://www.googleapis.com/auth/userinfo.email';

    const CACHE_NAMESPACE = 'GoogleOAuth';
    const REFRESH_TOKEN_CACHE = 'refreshToken';
    const DEVICE_CODE_CACHE = 'deviceCode';

    /** @var string */
    protected $identifier = '';

    /**
     * Sets the Google identifier (solely for a cache namespace)
     * Doesn't need your real login credentials, as Google's OAuth service is used (you log in in your browser on google.com).
     *
     * Note: This is optional, but necessary if you're planning on logging in multiple accounts with the same cache instance
     *
     * @param string $identifier Can be your E-Mail, doesn't have to be
     *
     * @return GoogleOAuth|$this
     */
    public function setIdentifier(string $identifier):GoogleOAuth
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getAuthType():string
    {
        return AuthType::GOOGLE;
    }

    public function getUniqueIdentifier():string
    {
        return $this->identifier;
    }

    public function invoke(): AccessToken
    {
        $refreshTokenCacheItem = $this->cache->getItem(Client::cacheKey([static::CACHE_NAMESPACE, static::REFRESH_TOKEN_CACHE, $this->getUniqueIdentifier()]));
        $refreshToken = $refreshTokenCacheItem->get();
        if ($refreshTokenCacheItem->isHit()) {
            $this->logger->info('Refresh Token in Cache');
        } else {
            if ($this->cache instanceof MemoryPool) {
                throw new AuthException('A persistent cache implementation is necessary for Google login');
            }

            return $this->obtainRefreshToken($refreshTokenCacheItem);
        }

        $token = json_decode($this->client->post(static::GOOGLE_OAUTH_TOKEN_URL, [
            'form_params' => [
                'access_type' => 'offline',
                'client_id' => static::GOOGLE_OAUTH_CLIENT_ID,
                'client_secret' => static::GOOGLE_OAUTH_CLIENT_SECRET,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'scope' => static::SCOPE,
            ],
        ])->getBody());
        if (!$token->id_token) {
            throw new AuthException('No id_token returned from Google');
        }

        return new AccessToken($token->id_token, (int) $token->expires_in);
    }

    /**
     * If the refresh token is not in cache, get it by verifying the device.
     *
     * @param CacheItemInterface $refreshTokenCacheItem
     *
     * @return AccessToken
     *
     * @throws AuthException
     * @throws DeviceNotVerifiedException With the verification URL and user code
     */
    protected function obtainRefreshToken(CacheItemInterface $refreshTokenCacheItem):AccessToken
    {
        $deviceCodeCacheItem = $this->cache->getItem(Client::cacheKey([static::CACHE_NAMESPACE, static::DEVICE_CODE_CACHE, $this->getUniqueIdentifier()]));
        $deviceCode = $deviceCodeCacheItem->get();
        if ($deviceCodeCacheItem->isHit()) {
            $this->logger->info('Device Code in Cache');
            try {
                $accessToken = json_decode($this->client->post(static::GOOGLE_OAUTH_TOKEN_URL, [
                    'form_params' => [
                        'client_id' => static::GOOGLE_OAUTH_CLIENT_ID,
                        'client_secret' => static::GOOGLE_OAUTH_CLIENT_SECRET,
                        'code' => $deviceCode->device_code,
                        'grant_type' => 'http://oauth.net/grant_type/device/1.0',
                        'scope' => static::SCOPE,
                    ],
                ])->getBody());
                if ($accessToken->access_token && $accessToken->refresh_token && $accessToken->id_token) {
                    $this->cache->save($refreshTokenCacheItem->set($accessToken->refresh_token));

                    return new AccessToken($accessToken->id_token, (int) $accessToken->expires_in);
                }
                throw new AuthException('No access_token/refresh_token/id_token returned from Google');
            } catch (RequestException $e) {
                if (!$e->hasResponse() || json_decode($e->getResponse()->getBody())->error != 'authorization_pending') {
                    throw $e;
                }
            }
        } else {
            $deviceCode = json_decode($this->client->post(static::GOOGLE_OAUTH_DEVICE_CODE_URL, [
                'form_params' => [
                    'client_id' => static::GOOGLE_OAUTH_CLIENT_ID,
                    'scope' => static::SCOPE,
                ],
            ])->getBody());
            $this->cache->save($deviceCodeCacheItem
                ->set($deviceCode)
                ->expiresAfter((int) $deviceCode->expires_in));
        }
        throw new DeviceNotVerifiedException($deviceCode->verification_url, $deviceCode->user_code);
    }
}
