<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? "Supershop"; ?></title>
    <?php
    if (!empty($stylesheets)) {
        foreach ($stylesheets as $stylesheet) {
            echo '<link rel="stylesheet" href="/public/css/' . htmlspecialchars($stylesheet) . '">' . PHP_EOL;
        }
    }
    ?>
</head>

<body>