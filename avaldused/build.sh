#!/bin/sh

# tmp dir should be writable for Apache
chmod a+rwxt tmp

# SWIG
cd swig
sh ./build.sh
cd ..

