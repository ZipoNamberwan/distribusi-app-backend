<?php
// app/Services/KeycloakProvider.php

namespace App\Services;

use League\OAuth2\Client\Provider\GenericProvider;

class KeycloakProvider
{
    public static function make()
    {
        $base = config('services.keycloak.url')
            . '/realms/' . config('services.keycloak.realm')
            . '/protocol/openid-connect';

        return new GenericProvider([
            'clientId'                => config('services.keycloak.client_id'),
            'clientSecret'            => config('services.keycloak.client_secret'),
            'redirectUri'             => route('sso.callback'),

            'urlAuthorize'            => $base . '/auth',
            'urlAccessToken'          => $base . '/token',
            'urlResourceOwnerDetails' => $base . '/userinfo',

            // 🔥 important
            'scopes' => explode(' ', config('services.keycloak.scope')),
        ]);
    }
}
