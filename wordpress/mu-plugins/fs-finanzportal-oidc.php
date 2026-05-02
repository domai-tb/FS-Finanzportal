<?php
/**
 * Local Docker adjustments for OpenID Connect Generic.
 *
 * WordPress must connect to Keycloak over Docker DNS (`keycloak:8080`), while
 * browser-facing OIDC tokens use the external authority (`localhost:8180`).
 * Keycloak validates the token issuer against the request Host header for
 * UserInfo, so internal HTTP requests must keep the Docker URL but present the
 * external Host header.
 */

add_filter(
    'openid-connect-generic-alter-request',
    static function (array $request, string $operation): array {
        if (!in_array($operation, ['get-authentication-token', 'refresh-token', 'get-userinfo'], true)) {
            return $request;
        }

        if (!isset($request['headers']) || !is_array($request['headers'])) {
            $request['headers'] = [];
        }

        $external_url = getenv('KC_EXTERNAL_URL') ?: 'http://localhost:8180';
        $host = parse_url($external_url, PHP_URL_HOST);
        $port = parse_url($external_url, PHP_URL_PORT);

        if ($host && $port) {
            $request['headers']['Host'] = $host . ':' . $port;
        }

        return $request;
    },
    10,
    2
);

add_filter(
    'http_request_args',
    static function (array $args, string $url): array {
        $internal_url = getenv('KC_INTERNAL_URL') ?: 'http://keycloak:8080';
        $internal_host = parse_url($internal_url, PHP_URL_HOST);

        if (!$internal_host || parse_url($url, PHP_URL_HOST) !== $internal_host) {
            return $args;
        }

        $external_url = getenv('KC_EXTERNAL_URL') ?: 'http://localhost:8180';
        $host = parse_url($external_url, PHP_URL_HOST);
        $port = parse_url($external_url, PHP_URL_PORT);

        if (!$host || !$port) {
            return $args;
        }

        if (!isset($args['headers']) || !is_array($args['headers'])) {
            $args['headers'] = [];
        }

        $args['headers']['Host'] = $host . ':' . $port;

        return $args;
    },
    10,
    2
);
