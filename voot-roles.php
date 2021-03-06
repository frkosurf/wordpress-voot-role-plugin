<?php
/*
    Copyright (C) 2012  François Kooman <fkooman@tuxed.net>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/*
    Plugin Name: VOOT Roles
    Plugin URI: https://github.com/fkooman/wordpress-voot-role-plugin/
    Description: Sets the role of the user based on VOOT group memberships.
    Author: François Kooman <fkooman@tuxed.net>
    Version: 0.3
    Author URI: http://fkooman.wordpress.com/
    License: GPLv3 or later
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
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
    if (array_key_exists("HTTP_REFERER", $_SERVER) && !empty($_SERVER['HTTP_REFERER'])
            && array_key_exists("HTTP_HOST", $_SERVER) && !empty($_SERVER['HTTP_HOST'])) {
        $httpReferrer = $_SERVER['HTTP_REFERER'];
        $httpHost = $_SERVER['HTTP_HOST'];
        $httpReferrerQuery = parse_url($httpReferrer, PHP_URL_QUERY);
        if (FALSE !== $httpReferrerQuery && NULL !== $httpReferrerQuery) {
            parse_str($httpReferrerQuery, $queryArray);
            if (is_array($queryArray) && !empty($queryArray)) {
                if (array_key_exists("redirect_to", $queryArray)) {
                    // httpHost MUST be equal to redirect_to query host
                    $redirectTo = urldecode($queryArray['redirect_to']);
                    $redirectToHost = parse_url($redirectTo, PHP_URL_HOST);
                    if ($httpHost === $redirectToHost) {
                        $returnUri = $redirectTo;
                    }
                }
            }
        }
    }
    if (NULL === $returnUri) {
        $returnUri = admin_url();
    }

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
 * Set the user meta/key value to fetch the VOOT role, this is set at login
 * time
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
    $fetchVootRole = get_user_meta($user->ID, "fetch_voot_role", TRUE);
    if (!$fetchVootRole) {
        return;
    }

    $config = parse_ini_file("config/vr.ini", TRUE);
    if (!is_array($config) || empty($config)) {
        $message = "[voot-roles] ERROR: configuration file is malformed";
        error_log($message);
        die($message);
    }

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

        $message = "[voot-roles] INFO: performing VOOT call";
        error_log($message);

        $response = $client->makeRequest($apiEndpoint . "/groups/@me");

        if (200 !== $response->getStatusCode()) {
            $message = "[voot-roles] ERROR: unexpected status code from VOOT provider (" . $response->getStatusCode() . "): " . $response->getContent();
            error_log($message);
            die($message);
        }

        $content = $response->getContent();
        if (empty($content)) {
            $message = "[voot-roles] ERROR: empty response from VOOT provider";
            error_log($message);
            die($message);
        }

        $data = json_decode($content, TRUE);
        if (NULL === $data || !is_array($data)) {
            $message = "[voot-roles] ERROR: invalid/no JSON response from VOOT provider: " . $content;
            error_log($message);
            die($message);
        }

        if (!array_key_exists("entry", $data)) {
            $message = "[voot-roles] ERROR: invalid JSON response from VOOT provider, missing 'entry': " . $content;
            error_log($message);
            die($message);
        }
        $groups = $data['entry'];

    } catch (\OAuth\Client\ApiException $e) {
        // FIXME: we probably should just return, if it didn't work out...well
        $message = "[voot-roles] OAuth ERROR: " . $e->getMessage();
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
