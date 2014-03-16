<?php
// vim: set ts=4 sw=4 et:
/**
 * A page for downloading the signed claim and its response.
 */
require_once "claims.php";

session_start();

$submit = isset($_GET["submit"]);
$get = isset($_GET["get"]) ? $_GET["get"] : "";

if ($submit) {
    $message = fetch_response();
} else {
    switch($get) {
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
            if ($message) {
                echo '<div class="msg">' . htmlspecialchars($message) . "</div>";
            }
        ?>

        <h2>Download</h2>

        <p><a href="?get=claim">Claim</a></p>
        <?php
            if (!$message) {
                echo '<p><a href="?get=response">Response</a></p>';
                echo "\n<p class=\"nb\"><strong>NB!</strong> The response from
                        the server is encrypted with the Mobile-ID
                        authentication certificate marked as a recipient. There
                        is currently no easy way to decrypt this as Mobile-ID
                        is not really meant for encryption. The fact though,
                        that we got a valid response, is enough to show that
                        submission succeeded and we have no need to decrypt the
                        response.</p>\n";
            }
        ?>
    </section>
</body>
</html>
