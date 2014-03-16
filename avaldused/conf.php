<?php

/* Included PEAR extensions. */
define('PEAR_PATH', dirname(__FILE__).'/inc/'); 
require_once PEAR_PATH . 'SOAP/Client.php';
require_once PEAR_PATH . 'XML/Unserializer.php';

/* Local file storage path (must end with a /) */
define('DD_FILES', dirname(__FILE__). '/tmp/');

/* The service's WSDL file. */
define('DD_WSDL', 'https://www.openxades.org:8443/?wsdl');

/* The service's CA certificate. */
define('DD_SERVER_CA_FILE', dirname(__FILE__) . '/service_certs.crt');

/* Service request timeout. */
define('DD_TIMEOUT', '9000');

/* Where to store the generated WSDL class. If the service or it's location
 * changes, then this file needs to be deleted so it will be regenerated. */
define('DD_WSDL_FILE', dirname(__FILE__) . '/wsdl.class.php');

/* Location of the claim handling service. */
define('DD_RESPONSE_SERVER', 'http://'. $_SERVER['SERVER_NAME'] .'/avaldused/submit.php');

?>
