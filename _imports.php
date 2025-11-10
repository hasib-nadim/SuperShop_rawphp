<?php
session_start();  
require_once __DIR__ . '/app/config/env.php'; 

function pageHead($pageTitle="Bigshop", $stylesheets=[]) {
    require_once __DIR__ . '/partials/head.php';
    require_once __DIR__ . '/partials/header.php';
}

function pageFooter() {
    require_once __DIR__ . '/partials/footer.php';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function imageUrl($relativePath): string {
    return "/public/images/" . ltrim($relativePath, '/');
}