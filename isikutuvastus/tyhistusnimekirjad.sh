 #!/bin/bash
 
 # Hangi AS Sertifitseerimiskeskusest tühistusnimekirjad. Vt ka http://sk.ee/repositoorium/CRL/ 
 
 wget http://www.sk.ee/crls/esteid/esteid2007.crl
 wget http://www.sk.ee/crls/juur/crl.crl
 wget http://www.sk.ee/crls/eeccrca/eeccrca.crl
 wget http://www.sk.ee/repository/crls/esteid2011.crl

# Konverteeri tühistusnimekirjad PEM formaati

 openssl crl -in esteid2007.crl -out esteid2007.crl -inform DER
 openssl crl -in crl.crl -out crl.crl -inform DER
 openssl crl -in eeccrca.crl -out eeccrca.crl -inform DER
 openssl crl -in esteid2011.crl -out esteid2011.crl -inform DER  

# Loo tühistusnimekirjade symlingid, mille failinimi baseerub CRL faili hashil:

 ln -s crl.crl `openssl crl -hash -noout -in crl.crl`.r0
 ln -s esteid2007.crl `openssl crl -hash -noout -in esteid2007.crl`.r0
 ln -s eeccrca.crl `openssl crl -hash -noout -in eeccrca.crl`.r0
 ln -s esteid2011.crl `openssl crl -hash -noout -in esteid2011.crl`.r0  
