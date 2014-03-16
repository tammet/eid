#!/bin/sh

( mkdir jdigidoc; cd jdigidoc ; wget https://installer.id.ee/media/jdigidoc-3.8.1-709.zip ; unzip jdigidoc-3.8.1-709.zip )
( mkdir certs; cd certs ; wget https://installer.id.ee/media/esteidtestcerts.jar )

mkdir lib
cp	jdigidoc/jdigidoc-3.8.1-709.jar \
	jdigidoc/lib/bcprov-jdk15on-148.jar \
	jdigidoc/lib/commons-codec-1.6.jar \
	jdigidoc/lib/commons-compress-1.3.jar \
	jdigidoc/lib/iaikPkcs11Wrapper.jar \
	jdigidoc/lib/jakarta-log4j-1.2.6.jar lib

cp jdigidoc/jdigidoc.cfg .
cp jdigidoc/log4j.properties .
(cd build ; jar xvf ../certs/esteidtestcerts.jar)
perl -pi -e "s{^DIGIDOC_OCSP_RESPONDER_URL=.*$}{DIGIDOC_OCSP_RESPONDER_URL=http://www.openxades.org/cgi-bin/ocsp.cgi};s{^DIGIDOC_SIGN_PKCS11_DRIVER=.*$}{DIGIDOC_SIGN_PKCS11_DRIVER=/usr/lib/opensc-pkcs11.so};" jdigidoc.cfg

ant dist
