<?php header("Content-Type: text/html; charset=utf-8");
// vim: set ts=4 sw=4 et:
/**
 * Sign a document via Mobile-ID.
 */
require_once "claims.php";

session_start();

$user = isset($_POST["user"]) ? trim($_POST["user"]) : "";
$content = isset($_POST["content"]) ? trim($_POST["content"]) : "";
$phone = isset($_POST["phone"]) ? trim($_POST["phone"]) : "";
$language = isset($_POST["language"]) ? trim($_POST["language"]) : "";
$outstanding_sign = isset($_GET["outstanding_sign"]);
$message = FALSE;

if (!$outstanding_sign) {
    if (!$user || !$content || !$phone || !$language) {
        header("HTTP/1.1 400 Bad Request");
        exit;
    }
    $message = sign($phone, $language, $user . "\n" . $content);
    $outstanding_sign = $message === NULL;
} else {
    $ret = sign_result();
    switch ($ret) {
    case "OUTSTANDING_TRANSACTION":
        /* Do nothing - we need to try again. */
        break;
    case "SIGNATURE":
        $ret = download_ddoc();
        if ($ret !== "OK") {
            $message = $ret;
        }
        $outstanding_sign = FALSE;
        break;
    default:
        $message = "ERROR: $ret";
        $outstanding_sign = FALSE;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>DigiDocService demo</title>
    <link rel="stylesheet" href="css/style.css">
    <!--[if lt IE 9]>
    <script type="text/javascript" src="js/html5shiv.js"></script>
    <![endif]-->

    <?php
    if ($outstanding_sign) {
        /* Check for auth result every 5 seconds. */
        echo '<meta http-equiv="refresh" content="5;url=?outstanding_sign=1">' . "\n";
    }
    ?>
</head>
<body>
    <header>
        <h1>DigiDocService PHP client demo</h1>
        <?= $_SERVER["SERVER_SIGNATURE"] ?>
    </header>
    <section>
        <?php
            if ($outstanding_sign) {
                echo "<p>Waiting for signature...</p>\n";
                echo "<p>Verification code <strong>" . $_SESSION["dds_challenge"]
                        . "</strong>. Make sure that it matches!</p>\n";
            } else {
                if ($message) {
                    echo '<div class="msg">' . htmlspecialchars($message) . "</div>\n";
                } else {
                    /* If we have no outstanding signings and no error
                     * messages, then everything succeeded. */
                    echo "<p>Claim successfully signed.</p>\n";
                    echo '<form action="download.php">' . "\n"
                            . '<input name="submit" value=1 type="hidden">' . "\n"
                            . '<button type="submit">Submit</button>' . "\n"
                            . "</form>\n";
                }
            }
        ?>
    </section>
</body>
</html>
