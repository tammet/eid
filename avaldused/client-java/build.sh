#!/bin/sh

( mkdir jdigidoc; cd jdigidoc ; wget https://installer.id.ee/media/jdigidoc-3.8.1-709.zip ; unzip jdigidoc-3.8.1-709.zip )
( mkdir certs; cd certs ; wget https://installer.id.ee/media/esteidtestcerts.jar )

ant
