<?php
// src/form_validation.php
declare(strict_types=1);

/** Společné kontroly hodnot používané ve formulářích akcí, klubů a kapel. */
function kf_form_valid_email(string $value): bool
{
    return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
}

function kf_form_valid_url(string $value): bool
{
    $value = trim($value);
    if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) return false;
    $scheme = strtolower((string)(parse_url($value, PHP_URL_SCHEME) ?: ''));
    return in_array($scheme, ['http', 'https'], true) && kf_safe_url($value) !== null;
}

function kf_form_valid_address(string $value): bool
{
    return mb_strlen(trim($value)) >= 2;
}

function kf_form_valid_city(string $value): bool
{
    return mb_strlen(trim($value)) >= 2;
}
