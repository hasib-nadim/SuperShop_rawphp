<?php
session_start();  
require_once __DIR__ . '/app/config/env.php'; 
// Load helper functions
require_once __DIR__ . '/app/helpers/Helpers.php';
require_once __DIR__ . '/app/helpers/Request.php';
require_once __DIR__ . '/app/helpers/Session.php';
// Load database functions


require_once __DIR__ . '/app/database/db.php';

function pageHead($pageTitle="Bigshop", $stylesheets=[]) {
    require_once __DIR__ . '/partials/head.php'; 
}

function pageFooter() {
    require_once __DIR__ . '/partials/footer.php';
}


function component($pathName, $vars=[]){
    // Safe component include helper.
    // Usage: echo component('admin/nav', ['foo'=>'bar']);
    // Accepts 'folder/file', 'folder.file' or '/folder/file.php' and prevents directory traversal.
    extract($vars);
    include_once __DIR__ . "/partials"."/".$pathName;
}

function redirect($url) {
    header("Location: $url"); 
}

function imageUrl($relativePath): string {
    return "/public/images/" . ltrim($relativePath, '/');
}
function url($url){
    $root = env("APP_URL");
    return $root.$url;
}