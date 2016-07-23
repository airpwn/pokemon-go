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

namespace DrDelay\PokemonGo\Auth;

use DrDelay\PokemonGo\Cache\CacheAwareInterface;
use DrDelay\PokemonGo\Cache\CacheAwareTrait;
use DrDelay\PokemonGo\Client;
use DrDelay\PokemonGo\Enum\AuthType;
use Fig\Cache\Memory\MemoryPool;
use GuzzleHttp\Exception\RequestException;
use function GuzzleHttp\json_decode;

class GoogleAuth extends AbstractAuth implements CacheAwareInterface
{
    use CacheAwareTrait;

    const GOOGLE_OAUTH_DEVICE_CODE_URL = 'https://accounts.google.com/o/oauth2/device/code';
    const GOOGLE_OAUTH_TOKEN_URL = 'https://www.googleapis.com/oauth2/v4/token';
    const GOOGLE_OAUTH_CLIENT_ID = '848232511240-73ri3t7plvk96pj4f85uj8otdat2alem.apps.googleusercontent.com';
    const GOOGLE_OAUTH_CLIENT_SECRET = 'NCjF1TLi2CcY6t5mt0ZveuL7';
    const SCOPE = 'openid email https://www.googleapis.com/auth/userinfo.email';

    const REFRESH_TOKEN_CACHE = 'refreshToken';
    const DEVICE_CODE_CACHE = 'deviceCode';

    /** @var string|null */
    protected $username;

    /** @var string|null */
    protected $password;

    /**
     * Sets the Google credentials.
     *
     * @param string $username
     * @param string $password
     *
     * @return GoogleAuth|$this
     */
    public function setCredentials(string $username, string $password):GoogleAuth
    {
        $this->username = $username;
        $this->password = $password;

        return $this;
    }

    public function getAuthType():string
    {
        return AuthType::GOOGLE;
    }

    public function getUniqueIdentifier():string
    {
        return $this->username;
    }

    public function invoke(): AccessToken
    {
        $cacheNamespace = [$this->getAuthType(), __NAMESPACE__];

        $refreshTokenCacheItem = $this->cache->getItem(Client::cacheKey(array_merge($cacheNamespace, [static::REFRESH_TOKEN_CACHE, $this->getUniqueIdentifier()])));
        $refreshToken = $refreshTokenCacheItem->get();
        if ($refreshTokenCacheItem->isHit()) {
            $this->logger->info('Refresh Token in Cache');
        } else {
            if ($this->cache instanceof MemoryPool) {
                throw new AuthException('A persistent cache implementation is necessary for Google login');
            }

            $deviceCodeCacheItem = $this->cache->getItem(Client::cacheKey(array_merge($cacheNamespace, [static::DEVICE_CODE_CACHE, $this->getUniqueIdentifier()])));
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
}
