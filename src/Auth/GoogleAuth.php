<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.md .
 */

/**
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 *
 * Based on the implementation from https://github.com/simon-weber/gpsoauth
 * Client signature + android id from https://github.com/tejado/pgoapi
 */

namespace DrDelay\PokemonGo\Auth;

use DrDelay\PokemonGo\Enum\AuthType;

class GoogleAuth extends AbstractAuth
{
    const GOOGLE_AUTH_URL = 'https://android.clients.google.com/auth';
    const GOOGLE_LOGIN_ANDROID_ID = '9774d56d682e549c';
    const GOOGLE_LOGIN_SERVICE= 'audience:server:client_id:848232511240-7so421jotr2609rmqakceuu1luuq0ptb.apps.googleusercontent.com';
    const GOOGLE_LOGIN_APP = 'com.nianticlabs.pokemongo';
    const GOOGLE_LOGIN_CLIENT_SIG = '321187995bc7cdc2b5fc91b11a96e2baa8602c62';
    const SDK_VERSION = 17;

    /** @var string|null */
    protected $email;

    /** @var string|null */
    protected $password;

    /** @var  string */
    protected $deviceCountry;

    /**
     * Sets the Google credentials.
     *
     * @param string $email
     * @param string $password
     * @param string $deviceCountry (default: us)
     * @return GoogleAuth
     */
    public function setCredentials(string $email, string $password, $deviceCountry = 'us') : GoogleAuth
    {
        $this->email = $email;
        $this->password = $password;
        $this->deviceCountry = $deviceCountry;

        return $this;
    }

    public function getAuthType() : string
    {
        return AuthType::GOOGLE;
    }

    public function getUniqueIdentifier() : string
    {
        return $this->email;
    }

    public function invoke() : AccessToken
    {
        $token = $this->getAuthorizationToken();

        $this->logger->info('Got authorization token ' . $token);

        return $this->exchangeAccessToken($token);
    }

    protected function getAuthorizationToken()
    {
        $data = [
            'accountType' => 'HOSTED_OR_GOOGLE',
            'Email' => $this->email,
            'has_permission' => 1,
            'add_account' => 1,
            'Passwd' => $this->password,
            'service' => 'ac2dm',
            'source' => 'android',
            'androidId' => static::GOOGLE_LOGIN_ANDROID_ID,
            'device_country' => $this->deviceCountry,
            'operatorCountry' => $this->deviceCountry,
            'lang' => 'en',
            'sdk_version' => static::SDK_VERSION,
        ];

        $response = $this->client->post(static::GOOGLE_AUTH_URL, [
            'form_params' => $data,
        ])->getBody();

        $result = parse_ini_string($response);

        if (!isset($result['Token'])) {
            throw new AuthException('No Authorization Token returned from Google');
        }

        return $result['Token'];
    }

    public function exchangeAccessToken($token)
    {
        $data = [
            'accountType' => 'HOSTED_OR_GOOGLE',
            'Email' => $this->email,
            'has_permission' => 1,
            'EncryptedPasswd' => $token,
            'service' => static::GOOGLE_LOGIN_SERVICE,
            'source' => 'android',
            'androidId' => static::GOOGLE_LOGIN_ANDROID_ID,
            'app' => static::GOOGLE_LOGIN_APP,
            'client_sig' => static::GOOGLE_LOGIN_CLIENT_SIG,
            'device_country' => $this->deviceCountry,
            'operatorCountry' => $this->deviceCountry,
            'lang' => 'en',
            'sdk_version' => static::SDK_VERSION,
        ];

        $response = $this->client->post(static::GOOGLE_AUTH_URL, [
            'form_params' => $data,
        ])->getBody();

        $result = parse_ini_string($response);

        if (!isset($result['Auth'])) {
            throw new AuthException('No Access Token returned from Google');
        }

        return new AccessToken($result['Auth'], strtotime('+30 minutes'));
    }
}
