<?php

namespace App\Utils;

class UrlEncryption {
    private $key;

    public function __construct($key) {
        $this->key = $key;
    }

    public function encrypt($url) {
        $encodedUrl = base64_encode($url);
        return strtr($encodedUrl, '+/', '-_');
    }

    public function decrypt($encryptedUrl) {
        $base64 = strtr($encryptedUrl, '-_', '+/');
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return false; // Invalid base64 string
        }
        return $decoded;
    }
}