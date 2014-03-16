<?php
// vim: set ts=4 sw=4 et:
/**
 * Page for signing a hash.
 */

session_start();

$given = isset($_POST["given"]) ? trim($_POST["given"]) : "";
$surname = isset($_POST["surname"]) ? trim($_POST["surname"]) : "";
$code = isset($_POST["code"]) ? trim($_POST["code"]) : "";
$content = isset($_POST["content"]) ? $_POST["content"] : "";
$cert_hex = isset($_POST["cert_hex"]) ? $_POST["cert_hex"] : "";
$cert_id = isset($_POST["cert_id"]) ? $_POST["cert_id"] : "";

$message = FALSE;
if ($given && $surname && $code && $content && $cert_hex && $cert_id) {
    require_once "claims.php";
    $message = start_signing($given, $surname, $code, $content, $cert_hex, $cert_id);
} else {
    header("HTTP/1.1 400 Bad Request");
    exit;
}

header("Content-Type: text/html; charset=utf-8");
$signature_hash = $_SESSION["dds_signature_hash"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>DigiDocService demo</title>
    <link rel="stylesheet" href="css/style.css">
    <!--[if lt IE 9]>
    <script type="text/javascript" src="js/html5shiv.js"></script>
    <![endif]-->
    <script type="text/javascript" src="js/idCard.js"></script>
    <script type="text/javascript" src="js/eid.js"></script>
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

        <form method="post" action="download.php"
                onsubmit="return sign_hash('<?= $cert_id ?>', '<?= $signature_hash ?>', this);">
            <fieldset>
                <legend>Signing</legend>

                <p>Certificate to sign with: <code><?= $cert_id ?></code></p>
                <p>Hash to sign: <code><?= $signature_hash ?></code></p>

                <input name="signature" type="hidden">
                <button type="submit">Sign</button>
            </fieldset>
        </form>
    </section>
    <div id="pluginLocation"></div><!-- constant id required for the ID-card library -->
</body>
</html>
