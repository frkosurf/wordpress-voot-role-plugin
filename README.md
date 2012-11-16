# Introduction
This Wordpress plugin makes it possible to use VOOT membership information as 
source for the role the user has in Wordpress.

# Configuration
Perform the following commands:

    $ sh docs/install_dependencies.sh
    $ sh docs/configure.sh

Initialize the database:

    $ php docs/init_oauth_database.php

Now you can modify the `config/config.ini` file to match your OAuth server
configuration and group memberships.


