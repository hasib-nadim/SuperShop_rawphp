<?php

require_once __DIR__ . '/../_imports.php';
// create config.php file in config folder by reading the loaded $config (env.php already parsed .env)

// -------------- Sync .env variables ---------------------
// this writes app/config/config.php which returns the $config array so other scripts can include it.

$configDir = __DIR__ . '/../app/config';
$outFile = $configDir . '/config.php';
if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}
// $config is loaded from env.php
$php = "<?php\nreturn " . var_export($config ?? [], true) . ";\n";

// write only when changed to avoid touching file timestamps unnecessarily
if (!file_exists($outFile) || file_get_contents($outFile) !== $php) {
    file_put_contents($outFile, $php);
    @chmod($outFile, 0644);
    echo "Wrote config file: $outFile<br/>";
} else {
    echo "Config file is up-to-date.<br/>";
}

// -------------------------Sync and seed database ----------------
$db = DB\getConnection();
// sync schema
DB\syncSchema();
echo "Synced Database Schema<br />";
// seed
// insert one super admin and one admin and one regular user

// insert adminuser if missing
$superUsername = 'superadmin';
$superEmail = 'superadmin@mail.com';
$superPass = password_hash('spadmin123', PASSWORD_DEFAULT);
if (!DB\exists_by('adminuser', 'username', $superUsername) && !DB\exists_by('adminuser', 'email', $superEmail)) {
    $stmt = $db->prepare("INSERT INTO `adminuser` (`username`,`password_hash`,`email`,`role`,`is_super`,`is_active`) VALUES (?,?,?,?,?,1)");
    if ($stmt) {
        $role = 'superadmin';
        $is_super = 1;
        $stmt->bind_param('ssssi', $superUsername, $superPass, $superEmail, $role, $is_super);
        $stmt->execute();
        $stmt->close();
        echo "Inserted super admin: $superUsername<br/>";
    }
} else {
    echo "Super admin already exists.<br/>";
}

// insert a normal admin
$adminUsername = 'admin';
$adminEmail = 'admin@example.com';
$adminPass = password_hash('admin123', PASSWORD_DEFAULT);
if (!DB\exists_by('adminuser', 'username', $adminUsername) && !DB\exists_by('adminuser', 'email', $adminEmail)) {
    $stmt = $db->prepare("INSERT INTO `adminuser` (`username`,`password_hash`,`email`,`role`,`is_super`,`is_active`) VALUES (?,?,?,?,?,1)");
    if ($stmt) {
        $role = 'admin';
        $is_super = 0;
        $stmt->bind_param('ssssi', $adminUsername, $adminPass, $adminEmail, $role, $is_super);
        $stmt->execute();
        $stmt->close();
        echo "Inserted admin: $adminUsername<br/>";
    }
} else {
    echo "Admin user already exists.<br/>";
}

// insert one regular user
$userEmail = 'user@example.com';
if (!DB\exists_by('users', 'email', $userEmail)) {
    $userFirst = 'John';
    $userLast = 'Doe';
    $userPass = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO `users` (`first_name`,`last_name`,`email`,`password_hash`,`phone`,`is_active`) VALUES (?,?,?,?,?,1)");
    if ($stmt) {
        $phone = '';
        $stmt->bind_param('sssss', $userFirst, $userLast, $userEmail, $userPass, $phone);
        $stmt->execute();
        $stmt->close();
        echo "Inserted user: $userEmail<br/>";
    }
} else {
    echo "User already exists.<br/>";
}




DB\closeConnection();
