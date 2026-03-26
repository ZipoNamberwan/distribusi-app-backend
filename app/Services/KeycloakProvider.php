<?php
// app/Services/KeycloakProvider.php

namespace App\Services;

use League\OAuth2\Client\Provider\GenericProvider;

class KeycloakProvider
{
    private static function baseUrl(): string
    {
        return config('services.keycloak.url')
            . '/auth/realms/' . config('services.keycloak.realm')
            . '/protocol/openid-connect';
    }

    public static function make()
    {
        $base = self::baseUrl();

        return new GenericProvider([
            'clientId'                => config('services.keycloak.client_id'),
            'clientSecret'            => config('services.keycloak.client_secret'),
            'redirectUri'             => route('sso.callback.index'),

            'urlAuthorize'            => $base . '/auth',
            'urlAccessToken'          => $base . '/token',
            'urlResourceOwnerDetails' => $base . '/userinfo',

            'scopes' => explode(' ', config('services.keycloak.scope')),
        ]);
    }

    public static function getLogoutUrl(array $options = [])
    {
        $base = self::baseUrl() . '/logout';

        $default = [
            'redirect_uri' => route('login'),
        ];

        $params = array_merge($default, $options);

        return $base . '?' . http_build_query($params);
    }
}
