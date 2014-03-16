#!/bin/sh

if [ ! -d swig ]; then
	echo "Should be executed from the same directory!"
	exit 1
fi

cp ./swig/digidoc.so /usr/lib/php5/*+lfs/
echo "extension=digidoc.so" > /etc/php5/apache2/conf.d/digidoc.ini

chgrp www-data .
chmod g+rwx .
perl -pi'.orig' -e "s{^DIGIDOC_OCSP_URL=.*$}{DIGIDOC_OCSP_URL=http://www.openxades.org/cgi-bin/ocsp.cgi}" /etc/digidoc.conf
echo "DIGIDOC_FORMAT=DIGIDOC-XML" >> /etc/digidoc.conf
echo "DIGIDOC_VERSION=1.3" >> /etc/digidoc.conf
