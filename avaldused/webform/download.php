<?php
// vim: set ts=4 sw=4 et:
/**
 * A page for downloading the signed claim and its response.
 */

session_start();

$signature = isset($_POST["signature"]) ? $_POST["signature"] : "";
$get = isset($_GET["get"]) ? $_GET["get"] : "";

$signing_message = FALSE;
$response_message = FALSE;
if ($signature) {
    require_once "claims.php";
    $signing_message = finish_signing($signature);
    $response_message = fetch_response();
} else if ($get) {
    require_once "claims.php";
    switch ($get) {
    case "claim":
        save_claim();
        break;
    case "response":
        save_response();
        break;
    default:
        header("HTTP/1.1 400 Bad Request");
    }
    exit;
} else {
    header("HTTP/1.1 400 Bad Request");
    exit;
}

header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>DigiDocService demo</title>
    <link rel="stylesheet" href="css/style.css">
    <!--[if lt IE 9]>
    <script type="text/javascript" src="js/html5shiv.js"></script>
    <![endif]-->
</head>
<body>
    <header>
        <h1>DigiDocService PHP client demo</h1>
        <?= $_SERVER["SERVER_SIGNATURE"] ?>
    </header>
    <section>
        <?php
            if ($signing_message) {
                echo '<div class="msg">' . htmlspecialchars($signing_message) . "</div>";
            }
            if ($response_message) {
                echo '<div class="msg">' . htmlspecialchars($response_message) . "</div>";
            }
        ?>

        <h2>Download</h2>

        <p><a href="?get=claim">Claim</a></p>
        <p><a href="?get=response">Response</a></p>
    </section>
</body>
</html>
