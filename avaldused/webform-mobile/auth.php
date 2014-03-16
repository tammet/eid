<?php header("Content-Type: text/html; charset=utf-8");
// vim: set ts=4 sw=4 et:
/**
 * Authenticate a user via Mobile-ID.
 */
require_once "claims.php";

session_start();

$phone = isset($_POST["phone"]) ? trim($_POST["phone"]) : "";
$language = isset($_POST["language"]) ? trim($_POST["language"]) : "";
$outstanding_auth = isset($_GET["outstanding_auth"]);
$message = FALSE;

if (!$outstanding_auth) {
    if (!$phone || !$language) {
        header("HTTP/1.1 400 Bad Request");
        exit;
    }
    $message = authenticate($phone, $language);
    $outstanding_auth = $message === NULL;
} else {
    $ret = authenticate_result();
    switch ($ret) {
    default:
        $message = "ERROR: $ret";
        /* Fall through */
    case "USER_AUTHENTICATED":
        $outstanding_auth = FALSE;
        break;
    case "OUTSTANDING_TRANSACTION":
        /* Do nothing - we need to try again. */
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
    if ($outstanding_auth) {
        /* Check for auth result every 5 seconds. */
        echo '<meta http-equiv="refresh" content="5;url=?outstanding_auth=1">' . "\n";
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
            if ($outstanding_auth) {
                echo "<p>Waiting for authentication...</p>\n";
                echo "<p>Verification code <strong>" . $_SESSION["dds_challenge"]
                        . "</strong>. Make sure that it matches!</p>\n";
            } else {
                if ($message) {
                    echo '<div class="msg">' . htmlspecialchars($message) . "</div>\n";
                } else {
                    /* If we have no outstanding authentications and no error
                     * messages, then everything succeeded. */
                    echo "<p>User " . $_SESSION["user_cn"] . " successfully authenticated.</p>\n";
                    echo '<form action="create.php"><button type="submit">Proceed</button></form>' . "\n";
                }
            }
        ?>
    </section>
</body>
</html>
