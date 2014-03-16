<?php header("Content-Type: text/html; charset=utf-8");
// vim: set ts=4 sw=4 et:
/**
 * A webform for submitting claims with Mobile-ID.
 * This first page sets up authentication.
 */
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
        <form method="post" action="auth.php">
            <fieldset>
                <legend>Authentication</legend>

                <p>
                <label for="phone">Phone number: </label>
                <input id="phone" name="phone" type="text" required>
                </p>

                <p>
                <label for="language">Language: </label>
                <select id="language" name="language">
                    <option>EST</option>
                    <option>ENG</option>
                    <option>RUS</option>
                </select>
                </p>
                <button type="submit">Authenticate</button>
            </fieldset>
        </form>
    </section>
</body>
</html>
