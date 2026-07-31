<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>BF2 Clan Manager</title>
    <base href="{base_url}/">
    <link rel="icon" type="image/png" href="public/images/icons/bf2.png">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <!-- Viewport Metatag -->
    <meta name="viewport" content="width=device-width,initial-scale=1.0">

    <!-- Theme and Page specific Stylesheet -->
    <link rel="stylesheet" type="text/css" href="public/css/style.css" media="screen" />
    <link rel="stylesheet" type="text/css" href="public/css/core/special-pages.css" media="screen" />

    <!-- Required JavaScript Plugins -->
    <script type="text/javascript" src="public/js/modernizr.custom.min.js"></script>
    <script type="text/javascript" src="public/js/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" src="public/js/common.js"></script>
    <script type="text/javascript" src="public/js/standard.js"></script>
    <script type="text/javascript" src="public/js/accessiblelist/jquery.accessibleList.js"></script>
</head>
<body class="special-page login-bg dark">

<section id="login-block">
    <div class="block-border">
        <div class="block-content">
            <h1>Admin</h1>
            <div class="block-header">Please login</div>
                <form class="form with-margin" name="login-form" id="login-form" method="post" action="">
                <input type="hidden" name="a" id="a" value="send">
                <p class="inline-small-label">
                    <label for="login"><span class="big">User name</span></label>
                    <input type="text" name="login" id="login" class="full-width" value="">
                </p>
                <p class="inline-small-label">
                    <label for="pass"><span class="big">Password</span></label>
                    <input type="password" name="pass" id="pass" class="full-width" value="">
                </p>

                <button type="submit" class="float-right">Login</button>
                <p class="input-height">
                    <input type="checkbox" name="keep-logged" id="keep-logged" value="1" class="mini-switch">
                    <label for="keep-logged" class="inline">Keep me logged in</label>
                </p>
            </form>

            <form class="form" id="password-recovery" method="post" action="">
                <fieldset class="grey-bg no-margin collapse">
                    <legend><a href="#">Lost password?</a></legend>
                    <p class="input-with-button">
                        <label for="recovery-mail">Enter your e-mail address</label>
                        <input type="text" name="recovery-mail" id="recovery-mail" value="">
                        <button type="button">Send</button>
                    </p>
                </fieldset>
            </form>
        </div>
    </div>
</section>

</body>
</html>