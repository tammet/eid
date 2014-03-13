eid authentication/isikutuvastus
================================

Examples for using Estonian eID for authentication.

eID kasutamise koodinäiteid.

Juhendid leiad lehelt http://eid.eesti.ee/index.php/Kasutaja_tuvastamine_veebis

Cgi programmide näited, mis loevad veebiserveri keskkonnamuutujast vajalikud väärtused, 
teisendavad tähestikud ja harutavad välja isiku eesnime, perenime ja isikukoodi, 
mis seejärel veebilehel kuvatakse. 

autendi.php : Php variant
autendi.py  : Pythoni variant
autendi.rb  : Ruby variant

Serveri serfikaadifailide kokkupaneku skript Linuxis:

serveriserdid.sh

Tühistusnimekirjade kokkupanek ja uuendamine Linuxis:

tyhistusnimekirjad.sh : Tühistusnimekirjade esialgse kokkupaneku skript Linuxis
renew.sh              : nimekirjade automaatse uuendamise skript 
                        id.ee lehelt http://id.ee/public/renew.sh

Tühistusnimekirjade haldamiseks vaata ka 
http://code.google.com/p/esteid/wiki/AuthConfApache#Apache_seadistamine






