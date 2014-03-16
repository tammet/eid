<?php header("Content-Type: text/html; charset=utf-8");
// vim: set ts=4 sw=4 et:
/**
 * A webform for submitting claims.
 */

$cn_arr = array("", "", "");
$cn = getenv("SSL_CLIENT_S_DN_CN");
if ($cn) {
    $cn_arr = explode(",", $cn);
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
    <script type="text/javascript" src="js/idCard.js"></script>
    <script type="text/javascript" src="js/eid.js"></script>
</head>
<body>
    <header>
        <h1>DigiDocService PHP client demo</h1>
        <?= $_SERVER["SERVER_SIGNATURE"] ?>
    </header>
    <section>
        <form method="post" action="sign.php" onsubmit="return read_certificate(this);">
            <fieldset>
                <legend>Claim</legend>

                <p>
                <label for="given">Given name: </label>
                <input id="given" name="given" type="text"
                    value="<?= $cn_arr[1] ?>" required>
                </p>

                <p>
                <label for="surname">Last name: </label>
                <input id="surname" name="surname" type="text"
                    value="<?= $cn_arr[0] ?>" required>
                </p>

                <p>
                <label for="code">Personal code: </label>
                <input id="code" name="code" type="text"
                    value="<?= $cn_arr[2] ?>" required>
                </p>

                <p>
                <label for="content">Content: </label>
                <input id="content" name="content" type="text" required autofocus>
                </p>

                <input name="cert_hex" type="hidden">
                <input name="cert_id" type="hidden">
                <button type="submit">Submit</button>
            </fieldset>
        </form>
    </section>
    <div id="pluginLocation"></div><!-- constant id required for the ID-card library -->
</body>
</html>
