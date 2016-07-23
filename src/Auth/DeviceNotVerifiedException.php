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

class DeviceNotVerifiedException extends AuthException
{
    /** @var string */
    protected $verificationUrl;

    /** @var string */
    protected $userCode;

    /**
     * DeviceNotVerifiedException constructor.
     *
     * @param string $verificationUrl
     * @param string $userCode
     */
    public function __construct(string $verificationUrl, string $userCode)
    {
        parent::__construct('Please visit '.$verificationUrl.' and enter '.$userCode);
        $this->verificationUrl = $verificationUrl;
        $this->userCode = $userCode;
    }

    /**
     * Get the verification URL.
     *
     * @return string
     */
    public function getVerificationUrl(): string
    {
        return $this->verificationUrl;
    }

    /**
     * Get the user code.
     *
     * @return string
     */
    public function getUserCode(): string
    {
        return $this->userCode;
    }
}
