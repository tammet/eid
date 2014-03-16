<?php
// vim: set ts=4 sw=4 et:
/**
 * Functions for submitting signed claims and fetching responses.
 * Borrows heavily from https://www.openxades.org/ddservice
 */

require_once "../conf.php";
require_once "../DigiDoc.class.php";

/* Create the service class based on the WSDL and load it. */
DigiDoc_WSDL::load_WSDL();

/**
 * Prepares the given data for signing.
 * @return string The message to display to the user.
 */
function start_signing($given, $surname, $code, $content, $cert_hex, $cert_id) {
    $dds = new WebService_DigiDocService_DigiDocService();

    $session_status = start_session($dds, $given, $surname, $code, $content);
    $signature_status = prepare_signature($dds, $cert_hex, $cert_id);
    return "Session status: $session_status. Signing status: $signature_status.";
}

/**
 * Starts a new DigiDocService session, where the claim is prepared for
 * signing.
 * @return string StartSessionResponse Status field or error message.
 */
function start_session($dds, $given, $surname, $code, $content) {
    reset_php_session();
    $claim = create_claim($given, $surname, $code, $content);
    $ret = $dds->StartSession("", "", TRUE, $claim);
    if (PEAR::isError($ret)) {
        return "ERROR: " . $ret->getMessage();
    }
    $_SESSION["dds_session"] = $ret["Sesscode"];
    return $ret["Status"];
}

/**
 * Deletes the session's files and variables.
 */
function reset_php_session() {
    foreach (glob(File::getFilePrefix("*")) as $f) {
        @unlink($f);
    }
    session_unset();
}

/**
 * Creates a DataFileData representation of the data given.
 * @return array The DataFileData form of the claim.
 */
function create_claim($given, $surname, $code, $content) {
    $buf = "$given $surname, $code\n$content";

    $claim["name"] = "claim.txt";
    $claim["size"] = strlen($buf);
    $claim["MIME"] = "text/plain";
    $claim["content"] = $buf;

    $parser = new DigiDoc_Parser();
    return $parser->getFileHash($claim);
}

/**
 * Asks the service for the hash to be signed and the signature's id.
 * @return string PrepareSignature Status field or error message.
 */
function prepare_signature($dds, $cert_hex, $cert_id) {
    $dds_session = intval($_SESSION["dds_session"]);

    /* There is no point in adding extra inout boxes to demonstrate reading
     * the following values, since it is trivial. */
    $ret = $dds->PrepareSignature($dds_session, $cert_hex, $cert_id,
            "role", "city", "county", "zip", "country", "");
    if (PEAR::isError($ret)) {
        $dds->CloseSession($dds_session);
        return "ERROR: " . $ret->getMessage();
    }
    $_SESSION["dds_signature_id"] = $ret["SignatureId"];
    $_SESSION["dds_signature_hash"] = $ret["SignedInfoDigest"];
    return $ret["Status"];
}

/**
 * Finished the signing in progress and downloads the result.
 * @return string The message to display to the user.
 */
function finish_signing($signature) {
    $dds = new WebService_DigiDocService_DigiDocService();
    $dds_session = intval($_SESSION["dds_session"]);

    $finalize_status = finalize_signature($dds, $dds_session, $signature);
    $get_status = download_ddoc($dds, $dds_session);

    $dds->CloseSession($dds_session);

    /* Kustutame kõik liigsed failid. */
    foreach (glob(File::getFilePrefix("D*")) as $f) {
        @unlink($f);
    }
    return "Signature status: $finalize_status. Claim status: $get_status.";
}

/**
 * Sends the signed hash to the service.
 * @return string FinalizeSignatureResponse Status field or error message.
 */
function finalize_signature($dds, $dds_session, $signature) {
    $ret = $dds->FinalizeSignature($dds_session,
            $_SESSION["dds_signature_id"], $signature);
    if (PEAR::isError($ret)) {
        $dds->CloseSession($dds_session);
        return "ERROR: " . $ret->getMessage();
    }
    return $ret["Status"];
}

/**
 * Downloads the signed container to the server.
 * @return string GetSignedDocResponse Status field or error message.
 */
function download_ddoc($dds, $dds_session) {
    $ret = $dds->GetSignedDoc($dds_session);
    if (PEAR::isError($ret)) {
        $dds->CloseSession($dds_session);
        return "ERROR: " . $ret->getMessage();
    }

    $parser = new DigiDoc_Parser($ret["SignedDocData"]);
    $filename = get_claim_prefix() . ($parser->format === "BDOC" ? ".bdoc" : ".ddoc");
    $_SESSION["dd_claim_path"] = $filename;
    $ddoc = $parser->getDigiDoc(FALSE);
    File::saveLocalFile($filename, $ddoc);

    return $ret["Status"];
}

/**
 * Sends the claim to the processing service and saves the response.
 * @return string The message to display to the user.
 */
function fetch_response() {
    $ret = "OK";
    $dest = get_response_prefix() . ".cdoc";
    $claim = $_SESSION["dd_claim_path"];
    $cert = get_cert();

    $ch = curl_init(DD_RESPONSE_SERVER);
    $fp = fopen($dest, "wb");
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array("cert"=>"$cert",
            "claim"=>"@$claim;type="
            . get_mime_from_ext(pathinfo($claim, PATHINFO_EXTENSION))));

    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if ($status != 200) {
        $ret = file_get_contents($dest);
        @unlink($dest);
    }
    return "Response status: $ret";
}

/**
 * Reads the client's certificate and removes the header and footer.
 * @return string The client's certificate in PEM form.
 */
function get_cert() {
    $cert = getenv("SSL_CLIENT_CERT");
    /* Delete all lines starting with a hyphen. */
    return $cert ? trim(preg_replace("/^-.*/m", "", $cert)) : "";
}

/**
 * Offers the signed claim for download.
 */
function save_claim() {
    save_file($_SESSION["dd_claim_path"], "claim");
}

/**
 * Offers the response for download.
 */
function save_response() {
    save_file(get_response_prefix() . ".cdoc", "response");
}

/**
 * Offers a file for download.
 * @param string $path The file to download.
 * @param string $name The name to display to the user.
 */
function save_file($path, $name) {
    $content = File::readLocalFile($path);
    if (!$content) {
        header("HTTP/1.1 404 Not Found");
        return;
    }
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $mimetype = get_mime_from_ext($ext);
    File::saveAs("$name.$ext", $content, $mimetype, "utf-8");
}

/** @return string The claim's path without an extension. */
function get_claim_prefix() {
    return File::getFilePrefix("claim");
}

/** @return string The response's path without an extension. */
function get_response_prefix() {
    return File::getFilePrefix("response");
}

/** @return string The MIME type corresponding to an extension. */
function get_mime_from_ext($ext) {
    if ($ext === "bdoc") {
        return "application/vnd.bdoc-1.0";
    } else if ($ext === "ddoc") {
        return "application/x-ddoc";
    } else if ($ext === "cdoc") {
        return "application/x-cdoc";
    }
    return FALSE;
}

?>
