<?php
// vim: set ts=4 sw=4 et:
/**
 * Claim handling service. This checks the claim's signatures and sends a
 * signed and encrypted response.
 * POST requests made to this service need to have a "cert" field, where the
 * recipient's identification certificate is stored, and a "claim" field, where
 * the signed claim is attached.
 * Optionally, the request can have a field called "nocrypt", which will make
 * the service return an unencrypted response - this is necesary for the .NET
 * client. If "nocrypt" is given, then "cert" can be omitted. The content of
 * "nocrypt" is unused.
 * The response is in DIGIDOC-XML format, since the C library does not support
 * BDOC.
 */
require_once "conf.php";
require_once "digidoc.php";
require_once "DigiDoc.class.php";

/* Create a class based on the WSDL definition and load it. */
DigiDoc_WSDL::load_WSDL();

session_start();

if (handle_claim()) {
    send_response();
}

/* Cleanup */
foreach (glob(File::getFilePrefix("*")) as $f) {
    @unlink($f);
}
session_destroy();

/**
 * Checks if the file uploaded successfully and if the signatures are valid.
 * @return boolean Did the checks succeed?
 */
function handle_claim() {
    $file = $_FILES["claim"];

    if (!check_upload(&$file)) {
        header("HTTP/1.1 400 Bad Request");
        echo "Uploaded file missing or of wrong type!\n";
        return false;
    }

    if (($err = verify_signature(&$file)) !== "OK") {
        header("HTTP/1.1 400 Bad Request");
        echo "The claim's signature is invalid!\n$err\n";
        return false;
    }

    extract_claim($file);
    return true;
}

/**
 * Checks the file type and if the upload succeeded.
 * @return boolean Did the checks succeed?
 */
function check_upload($file) {
    if (preg_match(":^application/vnd.bdoc-:", $file["type"])) {
        $file["format"] = "bdoc";
    } else if ($file["type"] === "application/x-ddoc") {
        $file["format"] = "ddoc";
    } else {
        return FALSE;
    }

    return is_uploaded_file($file["tmp_name"]);
}

/**
 * Checks the signatures on the claim.
 * @return string SignatureInfo Status field ("OK") or an error message.
 */
function verify_signature($file) {
    $dds = new WebService_DigiDocService_DigiDocService();
    $file["content"] = File::readLocalFile($file["tmp_name"]);
    unlink($file["tmp_name"]);

    if ($file["format"] === "bdoc") {
        $ret = $dds->StartSession("", base64_encode($file["content"]), FALSE, "");
    } else {
        $parser = new DigiDoc_Parser($file["content"]);
        $ret = $dds->StartSession("", $parser->getDigiDoc(TRUE), FALSE, "");
    }

    if (PEAR::isError($ret)) {
        return "ERROR: " . $ret->getMessage();
    }
    $sig_info = $ret["SignedDocInfo"]->SignatureInfo;
    if (is_array($sig_info)) {
        return "ERROR: The claim can have only one signature.";
    }

    if ($sig_info->Status !== "OK") {
        $error = $sig_info->Error;
        return "ERROR: The signature is invalid - $error->Description ($error->Code).";
    }
    return "OK";
}

/**
 * Extracts the claim from the signature container.
 */
function extract_claim($file) {
    /* Deserialize the DDOC document into an array. */
    $us = new XML_Unserializer(array("parseAttributes" => TRUE));
    $us->unserialize($file["content"]);
    $ddoc = $us->getUnserializedData();

    /* Write each data file to disk. */
    foreach ($ddoc["DataFile"] as $df) {
        File::saveLocalFile(File::getFilePrefix($df["Filename"]),
                base64_decode($df["_content"]));
    }
}

/**
 * Creates, signs, encrypts and sends the response.
 */
function send_response() {
    $nocrypt = isset($_POST["nocrypt"]);
    $cert = isset($_POST["cert"]) ? trim($_POST["cert"]) : "";
    if (!$nocrypt && !$cert) {
        header("HTTP/1.1 400 Bad Request");
        echo "No recipient certificate specified!\n";
        return;
    }

    if (!create_response()) {
        header("HTTP/1.1 500 Internal Server Error");
        echo "Error creating response";
        return;
    }

    $err = digidoc::initialize();
    if ($err != digidoc::ERR_OK) {
        header("HTTP/1.1 500 Internal Server Error");
        echo "Error initializing libdigidoc: " . digidoc::get_error($err);
        return;
    }
    $err = sign_response();
    if ($err != digidoc::ERR_OK) {
        header("HTTP/1.1 500 Internal Server Error");
        echo "Error signing response: " . digidoc::get_error($err);
        digidoc::finalize();
        return;
    }
    if (!$nocrypt) {
        $err = encrypt_response($cert);
        if ($err != digidoc::ERR_OK) {
            header("HTTP/1.1 500 Internal Server Error");
            echo "Error encrypting response: " . digidoc::get_error($err);
            digidoc::finalize();
            return;
        }
    }
    digidoc::finalize();

    if ($err == digidoc::ERR_OK) {
        $ext = $nocrypt ? "ddoc" : "cdoc";
        $resp = File::readLocalFile(get_response_prefix() . ".$ext");
        File::saveAs("response.$ext", $resp, "application/x-$ext", "utf-8");
    }
}

/**
 * Saves the response file to disk.
 */
function create_response() {
    return File::saveLocalFile(get_response_prefix(), "A response.");
}

/**
 * Signs the response using the SWIG wrapper.
 * @return int The returned error code.
 */
function sign_response() {
    $err = -1;
    $sdoc = digidoc::new_signature_container();
    if ($sdoc != NULL) {
        $err = digidoc::add_data_file($sdoc, get_response_prefix(), "text/plain");
        if ($err == digidoc::ERR_OK && DD_SIGN_RESPONSE) {
            /* PIN2 can either be given here as a string or added to the
             * libdigidoc configuration file as the value of AUTOSIGN_PIN. */
            $err = digidoc::sign_container($sdoc, NULL, "role", "city",
                    "county", "zip", "country");
        }
        if ($err == digidoc::ERR_OK) {
            $err = digidoc::save_signed_document($sdoc, get_response_prefix() . ".ddoc");
        }
    }
    digidoc::free_signature_container($sdoc);
    return $err;
}

/**
 * Encrypts the response using the SWIG wrapper.
 * @return int The returned error code.
 */
function encrypt_response($cert) {
    $err = -1;
    $encd = digidoc::new_encryption_container();
    if ($encd != NULL) {
        $err = digidoc::encrypt_ddoc($encd, get_response_prefix() . ".ddoc");
        if ($err == digidoc::ERR_OK) {
            $err = digidoc::add_recipient($encd, $cert, strlen($cert));
        }
        if ($err == digidoc::ERR_OK) {
            $err = digidoc::save_encrypted_data($encd, get_response_prefix() . ".cdoc");
        }
    }
    digidoc::free_encryption_container($encd);
    return $err;
}

/**
 * @return string Prefix of the response's path.
 */
function get_response_prefix() {
    return File::getFilePrefix("response");
}

?>
