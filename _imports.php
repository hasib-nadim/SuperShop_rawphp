<?php
session_start();
require_once __DIR__ . '/_config/env.php'; 

function pageHead($pageTitle="Bigshop", $stylesheets=[]) {
    $pageTitle = $pageTitle;
    $stylesheets = $stylesheets; 
    require_once __DIR__ . '/_views/head.php';
    require_once __DIR__ . '/_views/header.php';
}

function pageFooter() {
    require_once __DIR__ . '/_views/footer.php';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function imageUrl($relativePath): string {
    return "/public/images/" . ltrim($relativePath, '/');
}