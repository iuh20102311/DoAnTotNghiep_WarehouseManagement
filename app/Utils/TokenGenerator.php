<?php

namespace App\Utils;

use App\Models\User;
use App\Models\Role;
use DateTimeImmutable;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

class TokenGenerator
{
    public static function generateAccessToken(int $userId): string
    {
        $tokenBuilder = new Builder(new JoseEncoder(), ChainedFormatter::default());
        $signingKey = InMemory::plainText($_ENV['PRIVATE_KEY']);
        $algorithm = new Sha256();
        $now = new DateTimeImmutable();

        $user = User::find($userId);

        $role = Role::find($user->role_id);

        $profile = $user->profile;

        $token = $tokenBuilder
            ->issuedBy('http:/localhost:3000')
            ->permittedFor('http://localhost:8000')
            ->relatedTo($userId)
            ->issuedAt($now)
            ->expiresAt($now->modify('+24 hour'))
            ->withClaim('id', $user->id)
            ->withClaim('name', $user->name)
            ->withClaim('email', $user->email)
            ->withClaim('role', $role->name)
            ->withClaim('profile_id', $profile->id)
            ->withClaim('type', 'Access')
            ->getToken($algorithm, $signingKey);

        return $token->toString();
    }

    public static function generateRefreshToken(int $userId): string
    {
        $tokenBuilder = new Builder(new JoseEncoder(), ChainedFormatter::default());
        $signingKey = InMemory::plainText($_ENV['PRIVATE_KEY']);
        $algorithm = new Sha256();
        $now = new DateTimeImmutable();

        $user = User::find($userId);

        $role = Role::find($user->role_id);

        $profile = $user->profile;


        $token = $tokenBuilder
            ->issuedBy('http:/localhost:3000')
            ->permittedFor('http://localhost:8000')
            ->relatedTo($userId)
            ->issuedAt($now)
            ->expiresAt($now->modify('+14 day'))
            ->withClaim('id', $user->id)
            ->withClaim('name', $user->name)
            ->withClaim('email', $user->email)
            ->withClaim('role', $role->name)
            ->withClaim('profile_id', $profile->id)
            ->withClaim('type', 'Refresh')
            ->getToken($algorithm, $signingKey);

        return $token->toString();
    }
}
