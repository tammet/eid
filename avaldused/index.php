<!DOCTYPE html>
<html lang="en">
<head>
    <title>DigiDocService demo</title>
    <link rel="stylesheet" href="webform/css/style.css">
</head>
<body>
    <header>
        <h1>DigiDocService PHP client demo</h1>
        <?= $_SERVER["SERVER_SIGNATURE"] ?>
    </header>
    <div class="msg">
        <ul>
            <li><a href="<?='https://'. $_SERVER['SERVER_NAME'] .'/eid/avaldused/webform/'?>">Claim demo - signing with ID-card</a></li>
            <li><a href="<?='https://'. $_SERVER['SERVER_NAME'] .'/eid/avaldused/webform-mobile/'?>">Claim demo - signing with Mobile-ID</a></li>
        </ul>
    </div>
</body>
</html>
