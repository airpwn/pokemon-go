<?php

/*
 * This file is part of drdelay/pokemon-go.
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.md .
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\PokemonGo\Cache;

use Psr\Cache\CacheItemPoolInterface;

interface CacheAwareInterface
{
    /**
     * Sets a cache instance on the object.
     *
     * @param CacheItemPoolInterface $cache
     *
     * @return CacheAwareInterface|$this
     */
    public function setCache(CacheItemPoolInterface $cache):self;
}
