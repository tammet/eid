// digidoc.i - SWIG PHP interface for libdigidoc
%module digidoc
%{
#include "digidoc.h"
%}

%include "digidoc.h"
#define ERR_OK 0

%pragma(php) phpinfo="
  php_info_print_table_start();
  php_info_print_table_row(2, \"libdigidoc support\", \"enabled\");
  php_info_print_table_end();
"
