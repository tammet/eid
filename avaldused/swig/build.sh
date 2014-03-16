#!/bin/sh
swig -php digidoc.i
gcc `php-config --includes` -fPIC -c digidoc.c digidoc_wrap.c
gcc -shared digidoc.o digidoc_wrap.o -ldigidoc -o digidoc.so

# copy digidoc.php to right place:
cp digidoc.php ..
