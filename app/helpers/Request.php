<?php

namespace Req;


function session($key, $default = null)
{
    return $_SESSION[$key] ?? $default;
}
function hasError($key)
{
    return isset($_SESSION['_req_errors'][$key]);
}
function error($key)
{
    if (isset($_SESSION['_req_errors'][$key])) {
        $msg = $_SESSION['_req_errors'][$key];
        unset($_SESSION['_req_errors'][$key]);
        return $msg;
    }
    return null;
}
function setError($key, $message)
{
    $_SESSION['_req_errors'][$key] = $message;
}

function input($key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function isPost()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}
function validate(array $spec): array
{
    // spec: ['field' => 'rule1|rule2', ...]
    $values = [];
    $errors = [];

    foreach ($spec as $key => $rules) {
        $raw = $_POST[$key] ?? $_GET[$key] ?? null;
        $rulesArr = array_filter(array_map('trim', explode('|', (string)$rules)));

        if ($raw === null) {
            if (in_array('required', $rulesArr, true)) {
                $errors[$key] = 'The field "' . $key . '" is required.';
            }
            $values[$key] = null;
            continue;
        }

        $val = $raw;

        foreach ($rulesArr as $rule) {
            if ($rule === '') continue;
            $param = null;
            if (strpos($rule, ':') !== false) {
                list($ruleName, $param) = explode(':', $rule, 2);
                $rule = $ruleName;
            }

            // If an error already recorded for this field, skip further checks
            if (isset($errors[$key])) break;

            switch ($rule) {
                case 'trim':
                    if (is_string($val)) $val = trim($val);
                    break;
                case 'sanitize':
                    if (is_string($val)) $val = trim(strip_tags($val));
                    break;
                case 'sanitize_html':
                    if (is_string($val)) {
                        $val = preg_replace('#<script.*?>.*?</script>#is', '', $val);
                        $val = preg_replace('#<style.*?>.*?</style>#is', '', $val);
                        $val = trim($val);
                    }
                    break;
                case 'int':
                case 'integer':
                    if (is_numeric($val)) $val = (int)$val;
                    else $errors[$key] = 'The field "' . $key . '" must be an integer.';
                    break;
                case 'float':
                case 'numeric':
                    if (is_numeric($val)) $val = $val + 0;
                    else $errors[$key] = 'The field "' . $key . '" must be numeric.';
                    break;
                case 'bool':
                case 'boolean':
                    $parsed = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($parsed === null) $errors[$key] = 'The field "' . $key . '" must be boolean.';
                    else $val = $parsed;
                    break;
                case 'email':
                    $val = filter_var($val, FILTER_SANITIZE_EMAIL);
                    if (!filter_var($val, FILTER_VALIDATE_EMAIL)) $errors[$key] = 'The field "' . $key . '" must be a valid email address.';
                    break;
                case 'url':
                    $val = filter_var($val, FILTER_SANITIZE_URL);
                    if (!filter_var($val, FILTER_VALIDATE_URL)) $errors[$key] = 'The field "' . $key . '" must be a valid URL.';
                    break;
                case 'alpha':
                    if (!is_string($val) || !preg_match('/^[A-Za-z]+$/', $val)) $errors[$key] = 'The field "' . $key . '" must contain only letters.';
                    break;
                case 'alnum':
                    if (!is_string($val) || !preg_match('/^[A-Za-z0-9]+$/', $val)) $errors[$key] = 'The field "' . $key . '" must be alphanumeric.';
                    break;
                case 'required':
                    $tmp = is_string($val) ? trim($val) : $val;
                    if ($tmp === '' || $tmp === null) $errors[$key] = 'The field "' . $key . '" is required.';
                    break;
                case 'min':
                    if ($param !== null) {
                        if (is_string($val)) {
                            if (mb_strlen($val) < (int)$param) $errors[$key] = 'The field "' . $key . '" must be at least ' . (int)$param . ' characters.';
                        } elseif (is_numeric($val)) {
                            if ($val < $param) $errors[$key] = 'The field "' . $key . '" must be >= ' . $param . '.';
                        }
                    }
                    break;
                case 'max':
                    if ($param !== null) {
                        if (is_string($val)) {
                            if (mb_strlen($val) > (int)$param) $errors[$key] = 'The field "' . $key . '" must be at most ' . (int)$param . ' characters.';
                        } elseif (is_numeric($val)) {
                            if ($val > $param) $errors[$key] = 'The field "' . $key . '" must be <= ' . $param . '.';
                        }
                    }
                    break;
                case 'in':
                    if ($param !== null) {
                        $options = array_map('trim', explode(',', $param));
                        if (!in_array((string)$val, $options, true)) $errors[$key] = 'The field "' . $key . '" must be one of: ' . implode(', ', $options) . '.';
                    }
                    break;
                case 'array':
                    if (!is_array($val)) $errors[$key] = 'The field "' . $key . '" must be an array.';
                    break;
                default:
                    // unknown rule, ignore
                    break;
            }
        }

        // final sanitation for strings: remove control characters
        if (!isset($errors[$key]) && is_string($val)) {
            $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $val);
        }

        $values[$key] = $val;
    }

    if (!empty($errors)) {
        if (!isset($_SESSION)) {
            @session_start();
        }
        $_SESSION['_req_errors'] = $errors;
        return [$values, $errors];
    }

    return [$values, null];
}
