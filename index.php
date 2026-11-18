<?php

/**
 * Convenience redirect for the XAMPP folder URL.
 *
 * The Laravel front controller lives in public/, so opening the project root
 * should not expose Apache's directory listing.
 */
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim(dirname($scriptName), '/');

if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}

header('Location: '.$basePath.'/public/', true, 302);
exit;
