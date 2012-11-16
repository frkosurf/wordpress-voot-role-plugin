#!/bin/sh

rm -rf extlib/
mkdir -p extlib/

# php-oauth-client
(
cd extlib/
git clone https://github.com/fkooman/php-oauth-client.git
)
