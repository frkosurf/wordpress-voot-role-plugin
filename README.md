# Introduction
This Wordpress plugin makes it possible to use VOOT membership information as 
source for the role the user has in Wordpress.

For each role in Wordpress you can configure a VOOT group that maps to that
role. When a user is a member of this group the role associated with it is
set in Wordpress for that user.

# Configuration
Perform the following commands:

    $ sh docs/install_dependencies.sh
    $ sh docs/configure.sh

Now you can modify the `config/config.ini` file to configure:
* The OAuth authorization server
* The VOOT API endpoint
* The mapping between Wordpress roles and VOOT group membership
