<?php

namespace App\DTO;

class LoginResponseDTO implements \JsonSerializable
{
    private string $accessToken;
    private string $refreshToken;

    /**
     * @param string $accessToken
     * @param string $refreshToken
     */
    public function __construct(string $accessToken, string $refreshToken)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }

}