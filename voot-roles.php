<?php
/*
    Plugin Name: VOOT Roles
    Plugin URI: https://github.com/fkooman/wordpress-voot-role-plugin/
    Description: Sets the role of the user based on VOOT group memberships.
    Author: François Kooman <fkooman@tuxed.net>
    Version: 0.2
    Author URI: http://fkooman.wordpress.com/
    License: GPLv3
 */

add_action('wp_login',          'vr_set_fetch_voot_role_meta', 10, 2);
add_action('auth_cookie_valid', 'vr_set_role',                 10, 2);

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
 * Set the user meta/key value to fetch the VOOT role, this is set at login time
 */
function vr_set_fetch_voot_role_meta($username, WP_User $user)
{
    update_user_meta($user->ID, "fetch_voot_role", TRUE);
}

/**
 * Fetch and set the role the user has according to the VOOT membership and
 * the role to group membership mapping
 */
function vr_set_role($cookie, WP_User $user)
{
    error_log("vr_set_role");

    // only fetch the VOOT role if the user just logged in...
    $fetchVootRole = get_user_meta($user->ID, "fetch_voot_role", TRUE);
    if (TRUE === $fetchVootRole) {
        return;
    }

    $config = parse_ini_file("config/vr.ini", TRUE);

    $clientPath = $config['OAuth']['clientPath'];
    require_once $clientPath . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "_autoload.php";

    $groups = array();

    try {
        $appId = $config['OAuth']['appId'];

        $client = new \OAuth\Client\Api($appId);
        $client->setUserId($user->ID);
        $client->setScope("read");
        $client->setReturnUri(vr_determine_return_uri());

        $apiEndpoint = $config['Voot']['apiEndpoint'];

        $response = $client->makeRequest($apiEndpoint . "/groups/@me");

        // FIXME: verify the response code/content from the VOOT service
        $response = json_decode($response->getContent(), TRUE);
        $groups = $response['entry'];

    } catch (\OAuth\Client\ApiException $e) {
        // FIXME: we probably should just return, if it didn't work out...well
        $message = "ERROR (" . $e->getMessage() . ")";
        error_log($message);
        die($message);
    }

    // FIXME: use WP_Roles to go through all registered roles
    if (vr_is_member_of($config['Voot']['administratorRoleGroup'], $groups)) {
        $role = "administrator";
    } elseif (vr_is_member_of($config['Voot']['editorRoleGroup'], $groups)) {
        $role = "editor";
    } elseif (vr_is_member_of($config['Voot']['authorRoleGroup'], $groups)) {
        $role = "author";
    } elseif (vr_is_member_of($config['Voot']['contributorRoleGroup'], $groups)) {
        $role = "contributor";
    } else {
        // everyone who succesfully authenticates will become a subscriber
        $role = "subscriber";
    }

    if (!in_array($role, $user->roles)) {
        $user->set_role($role);
        wp_update_user(array('ID' => $user->ID, 'role' => $role));
    }

    // we fetched the role of the user and set the role accordingly, now set
    // it to FALSE until next wp_login
    update_user_meta($user->ID, "fetch_voot_role", FALSE);
}
