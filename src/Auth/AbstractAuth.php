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

use DrDelay\PokemonGo\Http\ClientAwareInterface;
use DrDelay\PokemonGo\Http\ClientAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractAuth implements AuthInterface, ClientAwareInterface, LoggerAwareInterface
{
    use ClientAwareTrait, LoggerAwareTrait;
}
