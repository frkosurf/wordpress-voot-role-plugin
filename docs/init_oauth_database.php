<?php
$db = "sqlite:" . dirname(__DIR__) . "/data/client.sqlite";
$pdo = new PDO($db);

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `oauth2_tokens` (
            `access_token` VARCHAR(64) NOT NULL,
            `resource_owner_id` VARCHAR(64) NOT NULL,
            `issue_time` INT(11) NOT NULL,
            `expires_in` INT(11) NOT NULL,
            `scope` TEXT NOT NULL,
            `refresh_token` TEXT DEFAULT NULL,
            PRIMARY KEY (`access_token`))
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `oauth2_states` (
            `state` VARCHAR(64) NOT NULL,
            `request_uri` TEXT NOT NULL,
            PRIMARY KEY (`state`))
        ");
?>
