<?php

session_start();
?>

<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abi 26 - Startseite</title>

    <!-- <link rel="stylesheet" href="source/css/generalstyle.css">
    <link rel="stylesheet" href="source/css/indexstyle.css"> -->

    <link rel="stylesheet" href="source/css/stylesheet.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    </head>
    <body>
        <?php include "source/php/header.php"; ?>
        <div class="content">
            <?php 
                if (!empty($_SESSION['loggedIn'])) {
                    include "source/php/surveys.php";
                } else {
                    echo "<h1>Bitte anmelden um am Voting teilzunehmen</h1>";
                }
            ?>
        </div>
    </body>
</html>