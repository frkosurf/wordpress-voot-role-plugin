<?php
/*
    Plugin Name: VOOT Roles
    Plugin URI: https://github.com/fkooman/wordpress-voot-role-plugin/
    Description: Sets the role of the user based on VOOT group memberships.
    Author: François Kooman <fkooman@tuxed.net>
    Version: 0.1
    Author URI: http://fkooman.wordpress.com/
 */

add_action('wp_login', 'vr_set_role', 10, 2);
//add_action('auth_cookie_valid', 'vr_handle_authorization_code_response', 10, 2);

/**
 * Determine the URI the user wants to return to after succesfully obtaining
 * the group membership information from the VOOT API
 */
function vr_determine_return_uri() 
{
    // determine where the user wants to go after logging in...
    $returnUri = NULL;
    if (array_key_exists("HTTP_REFERER", $_SERVER)) {
        $referrer = $_SERVER['HTTP_REFERER'];
        $query = parse_url($referrer, PHP_URL_QUERY);
        if (FALSE !== $query && NULL !== $query) {
            parse_str($query, $queryArray);
            if (is_array($queryArray) && !empty($queryArray)) {
                if (array_key_exists("redirect_to", $queryArray)) {
                    $returnUri = urldecode($queryArray["redirect_to"]);
                }
            }
        }
    }
    if (NULL === $returnUri) {
        $returnUri = admin_url();
    }
    error_log("returnUri: $returnUri");
    return $returnUri;
}

/**
 * Figure out whether a group identifier is contained within a VOOT result set
 */
function vr_is_member_of($group, array $groups)
{
    foreach ($groups as $g) {
        if ($g['id'] === $group) {
            return TRUE;
        }
    }

    return FALSE;
}

/**
 *
 */
function vr_set_role($username, WP_User $user)
{
    error_log("ACTION: wp_login");
    $config = parse_ini_file("config/config.ini", TRUE);

    $clientPath = $config['OAuth']['clientPath'];
    require_once $clientPath . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "_autoload.php";

    $groups = array();

    try {
        $appId = $config['OAuth']['appId'];

        $client = new \OAuth\Client\Api($appId);
        $client->setUserId($user->ID);
        $client->setScope("read");
        $client->setReturnUri(vr_determine_return_uri());

        $apiEndpoint = $config['OAuth']['apiEndpoint'];

        $response = $client->makeRequest($apiEndpoint . "/groups/@me");

        // FIXME: verify the response
        $response = json_decode($response, TRUE);
        $groups = $response['entry'];

    } catch (\OAuth\Client\ApiException $e) {
        // FIXME: figure out how to throw nice error, maybe some Wordpress exception?
        $message = "ERROR (" . $e->getMessage() . ")";
        error_log($message);
        die($message);
    }

    // FIXME: is there a way to enumerate all possible roles?
    // Yes there is: use WP_Roles class
    if (vr_is_member_of($config['administratorRoleGroup'], $groups)) {
        $role = "administrator";
    } elseif (vr_is_member_of($config['editorRoleGroup'], $groups)) {
        $role = "editor";
    } elseif (vr_is_member_of($config['authorRoleGroup'], $groups)) {
        $role = "author";
    } elseif (vr_is_member_of($config['contributorRoleGroup'], $groups)) {
        $role = "contributor";
    } else {
        // everyone who succesfully authenticates will become a subscriber
        $role = "subscriber";
    }

    if (!in_array($role, $user->roles) ) {
        $user->set_role($role);
        wp_update_user(array('ID' => $user->ID, 'role' => $role));
    }
}
