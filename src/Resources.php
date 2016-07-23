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

abstract class Resources
{
    const USER_AGENT = 'Niantic App';
    const PTC_LOGIN_URL = 'https://sso.pokemon.com/sso/login?service=https%3A%2F%2Fsso.pokemon.com%2Fsso%2Foauth2.0%2FcallbackAuthorize';
    const PTC_OAUTH_URL = 'https://sso.pokemon.com/sso/oauth2.0/accessToken';
    const PTC_OAUTH_CLIENT_ID = 'mobile-app_pokemon-go';
    const PTC_OAUTH_REDIRECT = 'https://www.nianticlabs.com/pokemongo/error';
    const PTC_OAUTH_CLIENT_SECRET = 'w8ScCUXJQc6kXKw8FiOhd8Fixzht18Dq3PEVkUCP5ZPxtgyWsbTvWHFLm2wNY0JR';
}
