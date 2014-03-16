<?php header("Content-Type: text/html; charset=utf-8");
// vim: set ts=4 sw=4 et:
/**
 * Create the claim to submit.
 */
session_start();

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
        <form method="post" action="sign.php">
            <fieldset>
                <legend>Claim</legend>

                <p>
                <label for="user">User: </label>
                <input id="user" name="user" type="text" size=32
                    value="<?= $_SESSION["user_given"] . " "
                        . $_SESSION["user_surname"] . ", "
                        . $_SESSION["user_id"] ?>">
                </p>

                <p>
                <label for="content">Content: </label>
                <input id="content" name="content" type="text" required autofocus>
                </p>

                <p>
                <label for="phone">Phone Number: </label>
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
                <button type="submit">Submit</button>
            </fieldset>
        </form>
    </section>
</body>
</html>
