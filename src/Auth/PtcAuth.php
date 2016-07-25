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

use DrDelay\PokemonGo\Enum\AuthType;
use function GuzzleHttp\json_decode;

class PtcAuth extends AbstractAuth
{
    const PTC_LOGIN_URL = 'https://sso.pokemon.com/sso/login?service=https%3A%2F%2Fsso.pokemon.com%2Fsso%2Foauth2.0%2FcallbackAuthorize';
    const PTC_OAUTH_URL = 'https://sso.pokemon.com/sso/oauth2.0/accessToken';
    const PTC_OAUTH_CLIENT_ID = 'mobile-app_pokemon-go';
    const PTC_OAUTH_REDIRECT = 'https://www.nianticlabs.com/pokemongo/error';
    const PTC_OAUTH_CLIENT_SECRET = 'w8ScCUXJQc6kXKw8FiOhd8Fixzht18Dq3PEVkUCP5ZPxtgyWsbTvWHFLm2wNY0JR';

    /** @var string|null */
    protected $username;

    /** @var string|null */
    protected $password;

    /**
     * Sets the Ptc credentials.
     *
     * @param string $username
     * @param string $password
     *
     * @return PtcAuth|$this
     */
    public function setCredentials(string $username, string $password):PtcAuth
    {
        $this->username = $username;
        $this->password = $password;

        return $this;
    }

    public function getAuthType():string
    {
        return AuthType::PTC;
    }

    public function getUniqueIdentifier():string
    {
        return $this->username;
    }

    public function invoke(): AccessToken
    {
        $session = json_decode($this->client->get(static::PTC_LOGIN_URL)->getBody());

        $login = $this->client->post(static::PTC_LOGIN_URL, [
            'form_params' => [
                'lt' => $session->lt,
                'execution' => $session->execution,
                '_eventId' => 'submit',
                'username' => $this->username,
                'password' => $this->password,
            ],
        ]);
        $ticketRedirect = $login->getHeaderLine('Location');
        if (!$ticketRedirect) {
            throw new AuthException('Did not receive Location Header. PTC Offline or login data incorrect');
        }
        parse_str(parse_url($ticketRedirect, PHP_URL_QUERY), $queryParts);
        if (!array_key_exists('ticket', $queryParts)) {
            throw new AuthException('No ticket returned from PTC');
        }
        $ticket = $queryParts['ticket'];
        $this->logger->info('Got ticket '.$ticket);

        $token = $this->client->post(static::PTC_OAUTH_URL, [
            'form_params' => [
                'client_id' => static::PTC_OAUTH_CLIENT_ID,
                'redirect_uri' => static::PTC_OAUTH_REDIRECT,
                'client_secret' => static::PTC_OAUTH_CLIENT_SECRET,
                'grant_type' => 'refresh_token',
                'code' => $ticket,
            ],
        ]);
        parse_str($token->getBody(), $tokenParts);
        if (!array_key_exists('access_token', $tokenParts)) {
            throw new AuthException('No access_token returned from PTC');
        }

        return new AccessToken($tokenParts['access_token'], (int) $tokenParts['expires']);
    }
}
