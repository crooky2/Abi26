<?php

session_start();

?>

<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abi 26 - Account</title>
    <!-- Split stylesheets: global + account page -->
    <link rel="stylesheet" href="source/css/generalstyle.css">
    <link rel="stylesheet" href="source/css/accountstyle.css">
        
    </head>
    <body>
        <?php include "source/php/header.php"; ?>

        <?php 
            if (!empty($_SESSION['loggedIn'])) {
                include "source/php/account.php";
            } else {
                include "source/php/LoginRegister.php";
            }
        ?>
    </body>
</html>