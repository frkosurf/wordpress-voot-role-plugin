<?php
/*
    Plugin Name: VOOT Role Management
    Plugin URI: https://github.com/fkooman/wordpress-voot-role-plugin/
    Description: Sets the role of the user based on group memberships.
    Author: François Kooman <fkooman@tuxed.net>
    Version: 0.1
    Author URI: http://fkooman.wordpress.com/
 */

require_once 'extlib/php-oauth-client/lib/OAuthTwoPdoCodeClient.php';

add_action('wp_login', 'setUserRole', 10, 2);
add_action('auth_cookie_valid', 'handleAuthorizationCodeResponse', 10, 2);

function handleAuthorizationCodeResponse($cookie_elements, WP_User $user)
{
    error_log("ACTION: auth_cookie_valid");

    if (array_key_exists("state", $_GET)) {
        if (array_key_exists("code", $_GET)) {
            // authorization code available, continue with OAuth token fetching
            setUserRole($user->user_login, $user);
        } elseif (array_key_exists("error", $_GET)) {
            // FIXME: figure out how to throw nice error, maybe some Wordpress exception?
            if (array_key_exists("error_description", $_GET)) {
                $error = "ERROR (" . $_GET["error"] . "): " . $_GET['error_description'];
            } else {
                $error = "ERROR (" . $_GET["error"] . ")";
            }
            error_log($error);
            die($error);
        }
    }
}

function setUserRole($username, WP_User $user)
{
    error_log("ACTION: wp_login");

    $config = parse_ini_file("config/config.ini");
    $groups = array();
    try {
        $client = new OAuthTwoPdoCodeClient($config);
        $client->setLogFile(__DIR__ . "/data/log.txt");
        $client->setScope("read");
        $client->setResourceOwnerId($user->ID); // use Wordpress userId
        $response = $client->makeRequest($config['apiEndpoint'] . "/groups/@me");
        $response = json_decode($response, TRUE);
        $groups = $response['entry'];
    } catch (OAuthTwoPdoCodeClientException $e) {
        $error = "ERROR (" . $e->getMessage() . ")";
        error_log($error);
        die($error);
    }

    // FIXME: is there a way to enumerate all possible roles?
    if (isMemberOf($config['administratorRoleGroup'], $groups)) {
        $role = "administrator";
    } elseif (isMemberOf($config['editorRoleGroup'], $groups)) {
        $role = "editor";
    } elseif (isMemberOf($config['authorRoleGroup'], $groups)) {
        $role = "author";
    } elseif (isMemberOf($config['contributorRoleGroup'], $groups)) {
        $role = "contributor";
    } else {
        // everyone who succesfully authenticates will become a subscriber
        $role = "subscriber";
    }

    if (!in_array($role, $user->roles) ) {
        $user->set_role($role);
        wp_update_user(array('ID' => $user->ID, 'role' => $role));
    }

    // FIXME: maybe we should return TRUE or FALSE?!
    return;
}

function isMemberOf($group, array $groups)
{
    foreach ($groups as $g) {
        if ($g['id'] === $group) {
            return TRUE;
        }
    }

    return FALSE;
}
