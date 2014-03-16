<?php
// vim: set ts=4 sw=4 et:
/**
 * Collection of functions used by the webform to submit a claim to the service
 * using Mobile-ID.
 */

require_once "../DigiDoc.class.php";

/* Create the service class based on the WSDL and load it. */
DigiDoc_WSDL::load_WSDL();

/**
 * Authenticates the user via Mobile-ID.
 * @return string An error message or NULL on success.
 */
function authenticate($phone_nr, $language) {
    $dds = new WebService_DigiDocService_DigiDocService();
    /* The service name _MUST_ be "Testimine" if using the openxades
     * testservice. Otherwise error 101 will be returned. */
    $ret = $dds->MobileAuthenticate("", "", $phone_nr, $language,
            "Testimine", "", generate_challenge(), "asynchClientServer",
            NULL, TRUE, FALSE);
    if (PEAR::isError($ret)) {
        return "ERROR: " . $ret->getMessage() . " - " . $ret->getUserInfo()->{"message"};
    }
    $_SESSION["dds_session"] = $ret["Sesscode"];
    if ($ret["Status"] !== "OK") {
        return "ERROR: " . $ret["Status"];
    }
    /* Save some data about the user. */
    $_SESSION["dds_challenge"] = $ret["ChallengeID"];
    $_SESSION["user_cn"] = $ret["UserCN"];
    $_SESSION["user_id"] = $ret["UserIDCode"];
    $_SESSION["user_given"] = $ret["UserGivenname"];
    $_SESSION["user_surname"] = $ret["UserSurname"];
    $_SESSION["user_cert"] = $ret["CertificateData"];
    return NULL;
}

/**
 * Generates a challenge for mobile authentication.
 * This will yield a 20 character hexadecimal string (10 bytes).
 * @return string The generated challenge.
 */
function generate_challenge() {
    /* Since PHP is unable to convert 10 bytes of data to hexadecimal in one go
     * we will generate the 20 characters one-by-one. */
    $buf = "";
    while (strlen($buf) < 20) {
        $buf = $buf . dechex(rand(0, 15));
    }
    return $buf;
}

/**
 * Checks the current status of an authentication request.
 * @return string Result of mobile authentication.
 */
function authenticate_result() {
    $dds = new WebService_DigiDocService_DigiDocService();
    $ret = $dds->GetMobileAuthenticateStatus($_SESSION["dds_session"], FALSE);
    if (PEAR::isError($ret)) {
        return $ret->getMessage() . " - " . $ret->getUserInfo()->{"message"};
    }
    /* Sometimes the response is in an array and sometimes not - check which
     * case we're dealing with. */
    if (is_array($ret)) {
        return $ret["Status"];
    }
    return $ret;
}

/**
 * Signs the given content via Mobile-ID.
 * @return string An error message or NULL on success.
 */
function sign($phone, $language, $content) {
    $dds = new WebService_DigiDocService_DigiDocService();
    /* Start is the same as with a smart card. */
    $claim = create_claim($content);
    $ret = start_session($dds, $claim);
    if ($ret !== "OK") {
        return $ret;
    }
    /* Use Mobile-ID to sign the created container. */
    $ret = sign_mid($dds, $phone, $language);
    if ($ret !== "OK") {
        return $ret;
    }
    return NULL;
}

/**
 * Creates a DataFileData representation of the data given.
 * @return array The DataFileData form of the claim.
 */
function create_claim($content) {
    $claim["name"] = "claim.txt";
    $claim["size"] = strlen($content);
    $claim["MIME"] = "text/plain";
    $claim["content"] = $content;

    $parser = new DigiDoc_Parser();
    return $parser->getFileHash($claim);
}

/**
 * Starts a new DigiDocService session.
 * @return string StartSessionResponse Status field or error message.
 */
function start_session($dds, $claim) {
    $ret = $dds->StartSession("", "", TRUE, $claim);
    if (PEAR::isError($ret)) {
        return "ERROR: " . $ret->getMessage();
    }
    $_SESSION["dds_session"] = $ret["Sesscode"];
    return $ret["Status"];;
}

/**
 * Send a MobileSign request to the service.
 * @return string MobileSignResponse Status field or error message.
 */
function sign_mid($dds, $phone, $language) {
    $ret = $dds->MobileSign($_SESSION["dds_session"], "", "", $phone,
            "Testimine", "", $language, "role", "city", "state", "postal",
            "country", "", "asynchClientServer", NULL, FALSE, FALSE);
    if (PEAR::isError($ret)) {
        return "ERROR: " . $ret->getMessage();
    }
    $_SESSION["dds_challenge"] = $ret["ChallengeID"];
    return $ret["Status"];
}

/**
 * Checks the current status of a signing request.
 * @return string Result of mobile signing.
 */
function sign_result() {
    $dds = new WebService_DigiDocService_DigiDocService();
    $ret = $dds->GetStatusInfo($_SESSION["dds_session"], FALSE, FALSE);
    if (PEAR::isError($ret)) {
        return $ret->getMessage() . " - " . $ret->getUserInfo()->{"message"};
    }
    return $ret["StatusCode"];
}

/**
 * Downloads the signed container to the server.
 * @return string GetSignedDocResponse Status field or error message.
 */
function download_ddoc() {
    $dds = new WebService_DigiDocService_DigiDocService();
    $ret = $dds->GetSignedDoc($_SESSION["dds_session"]);
    if (PEAR::isError($ret)) {
        return "ERROR: " . $ret->getMessage();
    }
    $dds->CloseSession($_SESSION["dds_session"]);

    /* Replace the hashes with the original content. */
    $parser = new DigiDoc_Parser($ret["SignedDocData"]);
    $filename = File::getFilePrefix("claim.ddoc");
    $_SESSION["dd_claim_path"] = $filename;
    $ddoc = $parser->getDigiDoc(FALSE);
    File::saveLocalFile($filename, $ddoc);

    return $ret["Status"];
}

/**
 * Submits the signed claim to the service and saves the response.
 * @return string Error message or NULL on success.
 */
function fetch_response() {
    $claim = File::getFilePrefix("claim.ddoc");;
    $dest = File::getFilePrefix("response.cdoc");
    $cert = $_SESSION["user_cert"];
    if ($cert) {
        /* Delete all lines starting with a hyphen. */
        $cert = trim(preg_replace("/^-.*/m", "", $cert));
    }

    $ch = curl_init(DD_RESPONSE_SERVER);
    if (!$ch) {
        return "ERROR: curl_init failed - " . curl_error($ch);
    }
    $fp = fopen($dest, "wb");
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array("cert"=>"$cert",
            "claim"=>"@$claim;type=application/x-ddoc"));

    if (!curl_exec($ch)) {
        return "ERROR: curl_exec failed - ". curl_error($ch);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if ($status != 200) {
        $ret = file_get_contents($dest);
        @unlink($dest);
        return "ERROR: HTTP Status $status\n$ret";
    }
    return NULL;
}

/**
 * Offers the signed claim for download.
 */
function save_claim() {
    save_file(File::getFilePrefix("claim.ddoc"), "claim.ddoc");
}

/**
 * Offers the response for download.
 */
function save_response() {
    save_file(File::getFilePrefix("response.cdoc"), "response.cdoc");
}

/**
 * Offers a file for download.
 */
function save_file($path, $name) {
    $content = File::readLocalFile($path);
    if (!$content) {
        header("HTTP/1.1 404 Not Found");
        return;
    }
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $mimetype = $ext === "cdoc" ? "application/x-cdoc" : "application/x-ddoc";
    File::saveAs("$name", $content, $mimetype, "utf-8");
}
