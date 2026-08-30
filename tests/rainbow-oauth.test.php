<?php

define('PUOCK', 'puock');

$GLOBALS['rainbow_api_base'] = 'https://custom.example.test';
$GLOBALS['rainbow_get_url'] = '';

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function __($text, $domain = null)
{
    return $text;
}

function sanitize_key($value)
{
    return strtolower(preg_replace('/[^a-z0-9_-]/', '', (string)$value));
}

function add_query_arg($key, $value = null, $url = null)
{
    if (is_array($key)) {
        $args = $key;
        $base = (string)$value;
    } else {
        $args = [$key => $value];
        $base = (string)$url;
    }

    return $base . (strpos($base, '?') === false ? '?' : '&') . http_build_query($args);
}

function pk_get_option($key)
{
    return $key === 'oauth_ccy_api' ? $GLOBALS['rainbow_api_base'] : '';
}

function esc_url_raw($url, $protocols = null)
{
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($protocols !== null && !in_array($scheme, $protocols, true)) {
        return '';
    }

    return (string)$url;
}

function is_wp_error($value)
{
    return false;
}

function wp_remote_retrieve_body($response)
{
    return $response['body'];
}

function wp_safe_remote_get($url, $args = [])
{
    $GLOBALS['rainbow_get_url'] = $url;
    return ['body' => json_encode([
        'code' => 0,
        'url' => 'https://provider.example.test/auth',
    ])];
}

function wp_remote_post($url, $args = [])
{
    return ['body' => '<!doctype html><title>Method Not Allowed</title>'];
}

require __DIR__ . '/../inc/oauth/RainbowOAuth.php';

$_GET['pk_type'] = 'ccy_qq';
$oauth = new Puock\Theme\oauth\RainbowOAuth(
    'app-id',
    'app-key',
    'https://site.example.test/callback'
);

try {
    $actual = $oauth->getAuthUrl(null, 'fixed-state');
} catch (Throwable $e) {
    fwrite(STDERR, 'Expected a GET-compatible endpoint to return its authorization URL; got: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

assert_same(
    'https://provider.example.test/auth',
    $actual,
    'Rainbow OAuth should return the authorization URL from a GET-compatible endpoint.'
);

parse_str((string)parse_url($GLOBALS['rainbow_get_url'], PHP_URL_QUERY), $sent);
assert_same([
    'act' => 'login',
    'appid' => 'app-id',
    'appkey' => 'app-key',
    'type' => 'qq',
    'redirect_uri' => 'https://site.example.test/callback?state=fixed-state',
], $sent, 'Rainbow OAuth should follow the documented GET query contract.');

$GLOBALS['rainbow_api_base'] = 'http://custom.example.test';
try {
    $oauth->getAuthUrl(null, 'fixed-state');
    fwrite(STDERR, 'Rainbow OAuth should reject a non-HTTPS API endpoint.' . PHP_EOL);
    exit(1);
} catch (InvalidArgumentException $e) {
    assert_same('接口地址必须使用 HTTPS', $e->getMessage(), 'Rainbow OAuth should preserve HTTPS enforcement.');
}

echo "rainbow oauth tests passed\n";
