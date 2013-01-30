<?php
namespace Icecave\Testing\Travis;

use Icecave\Testing\Support\Isolator;

class TravisClient
{
    public function __construct(Isolator $isolator = null)
    {
        $this->keyCache = array();
        $this->isolator = Isolator::get($isolator);
    }

    public function publicKey($repoOwner, $repoName, $forceCacheRefresh = false)
    {
        $cacheKey = $repoOwner . '/' . $repoName;

        if (array_key_exists($cacheKey, $this->keyCache) && !$forceCacheRefresh) {
            return $this->keyCache[$cacheKey];
        }

        $url  = sprintf(
            'https://api.travis-ci.org/repos/%s/%s/key',
            urlencode($repoOwner),
            urlencode($repoName)
        );

        $response = $this->isolator->file_get_contents($url);
        $response = json_decode($response);

        return $this->keyCache[$cacheKey] = $response->key;
    }

    public function encrypt($repoOwner, $repoName, $plainText)
    {
        $publicKey = $this->publicKey($repoOwner, $repoName);
        $cipherText = null;

        $this->isolator->openssl_public_encrypt(
            $plainText,
            $cipherText,
            str_replace('RSA PUBLIC KEY', 'PUBLIC KEY', $publicKey)
        );

        return $cipherText;
    }

    private $keyCache;
    private $isolator;
}
