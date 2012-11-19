#!/bin/sh

# create directories
mkdir -p data

# set permissions
chmod -R o+w data/
chcon -R -t httpd_sys_rw_content_t data/

# generate config files
(
cd config/
for DEFAULTS_FILE in `ls *.defaults`
do
    INI_FILE=`basename ${DEFAULTS_FILE} .defaults`
    if [ ! -f ${INI_FILE} ]
    then
        cat ${DEFAULTS_FILE} > ${INI_FILE}
    fi
done
)

