<?php

$configuredScopes = preg_split('/\s+/', trim((string) env(
    'CANVA_SCOPES',
    'design:content:write design:meta:read brandtemplate:meta:read brandtemplate:content:read profile:read',
))) ?: [];

return [
    'enabled' => filter_var(env('CANVA_ENABLED', false), FILTER_VALIDATE_BOOL)
        && trim((string) env('CANVA_CLIENT_ID')) !== ''
        && trim((string) env('CANVA_CLIENT_SECRET')) !== '',
    'client_id' => env('CANVA_CLIENT_ID'),
    'client_secret' => env('CANVA_CLIENT_SECRET'),
    'authorize_url' => env('CANVA_AUTHORIZE_URL', 'https://www.canva.com/api/oauth/authorize'),
    'base_url' => rtrim((string) env('CANVA_API_BASE_URL', 'https://api.canva.com/rest/v1'), '/'),
    'redirect_uri' => env('CANVA_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/api/integraciones/canva/callback'),
    'scopes' => array_values(array_filter($configuredScopes)),
    'connect_timeout_seconds' => (int) env('CANVA_CONNECT_TIMEOUT', 8),
    'timeout_seconds' => (int) env('CANVA_TIMEOUT', 30),
    'state_ttl_minutes' => (int) env('CANVA_OAUTH_STATE_TTL_MINUTES', 10),
    'token_refresh_leeway_seconds' => (int) env('CANVA_TOKEN_REFRESH_LEEWAY_SECONDS', 300),
    'template_cache_seconds' => (int) env('CANVA_TEMPLATE_CACHE_SECONDS', 300),
    'autofill_poll_seconds' => (int) env('CANVA_AUTOFILL_POLL_SECONDS', 5),
    'autofill_deadline_minutes' => (int) env('CANVA_AUTOFILL_DEADLINE_MINUTES', 15),
    'autofill_trial_enabled' => filter_var(env('CANVA_AUTOFILL_TRIAL_ENABLED', false), FILTER_VALIDATE_BOOL),
    'max_field_characters' => (int) env('CANVA_MAX_FIELD_CHARACTERS', 5000),
];
