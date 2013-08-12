# DEPRECATED
This module is no longer deemed needed as there is now a `ssp-voot-groups` 
module that add the VOOT groups to the SAML attributes. This can then be 
used with the SAML module of Wordpress:

See: http://wordpress.org/plugins/simplesamlphp-authentication/

# Introduction
This Wordpress plugin makes it possible to use VOOT membership information as 
source for the role the user has in Wordpress.

For each role in Wordpress you can configure a VOOT group that maps to that
role. When a user is a member of this group the role associated with it is
set in Wordpress for that user.

The plugin depends on the external OAuth client written in PHP that is 
available [here](https://github.com/fkooman/php-oauth-client). Follow the 
instructions there to install and configure it. See below an example snippet
for the application configuration for Wordpress.

# Configuration
Copy the sample configuration file:

    $ cp config/vr.ini.default config/vr.ini

Now you can modify the `config/vr.ini` file to configure:
* The `php-oauth-client` configuration (installation location and `appId`)
* The VOOT `apiEndpoint` and mapping between Wordpress roles and VOOT group 
  membership

The configuration snippet for the `php-oauth-client` is like this, assuming
you left the `appId` at the default `wordpress` and want to use this plugin
with SURFconext:

    {
        "wordpress": {
            "authorize_endpoint": "https://api.surfconext.nl/v1/oauth2/authorize", 
            "client_id": "REPLACE_ME_WITH_CLIENT_ID", 
            "client_secret": "REPLACE_ME_WITH_CLIENT_SECRET", 
            "token_endpoint": "https://api.surfconext.nl/v1/oauth2/token"
        }
    }

See the `php-oauth-client` documentation on how to register an application.
