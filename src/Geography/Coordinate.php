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

namespace DrDelay\PokemonGo\Geography;

class Coordinate
{
    /** @var float */
    protected $latitude;

    /** @var float */
    protected $longitude;

    /** @var int */
    protected $altitude;

    /**
     * Coordinate constructor.
     *
     * @param float $latitude
     * @param float $longitude
     * @param int   $altitude
     */
    public function __construct(float $latitude, float $longitude, int $altitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->altitude = $altitude;
    }

    /**
     * Get the latitude.
     *
     * @return float
     */
    public function getLatitude(): float
    {
        return $this->latitude;
    }

    /**
     * Get the longitude.
     *
     * @return float
     */
    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * Get the altitude.
     *
     * @return int
     */
    public function getAltitude(): int
    {
        return $this->altitude;
    }
}
