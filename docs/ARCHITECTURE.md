# Plugin Architecture
The plugin uses Wordpress hooks to obtain group membership information for an
authenticated user by querying some VOOT provider. This plugin ONLY sets the
role based on group membership. The way the OAuth service authenticates the
user is out of scope. This may very well be the same authentication source as
the Wordpress instance, for example using SAML, but this is by no means a 
requirement. It is recommended however to obtain "Single Sign On".

Two hooks are currently used by the plugin. The `wp_login` hook and the 
`auth_cookie_valid` hook. The `wp_login` hook is only triggered when the 
user authenticates to Wordpress, i.e.: logs in. The `auth_cookie_valid` hook
is triggered at every page load. It will be explained below why this is needed.

First the functionality of what happens when the `wp_login` hook is executed:

1. Check if an OAuth "access token" is available for the authenticated use, 
   using the user ID;
2. Available: 
   * Fetch user's group membership
   * Set user's role based on group membership (as configured in 
     `config/config.ini`)
3. !Available:
   * Store the current request URL
   * Redirect to "Authorize" endpoint of OAuth server

After the redirect the Wordpress script execution will stop.

Next the functionality of what happens when the `auth_cookie_valid` hook is
executed:

1. If `state`, `code` or `error` GET parameter is set
   * Obtain "access token" using "authorization code"
   * Fetch user's group membership
   * Set user's role based on group membership (as configured in 
     `config/config.ini`)

This will make the execution of the `auth_cookie_valid` very quick as most of
the time the GET parameters will not be set and then nothing happens.
