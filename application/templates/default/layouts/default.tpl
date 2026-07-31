<!DOCTYPE html>
<html lang="en-US">
<head>
    {% insert 'head' %}
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <!-- Theme and Page specific Stylesheet -->
    <link rel="icon" type="image/png" href="public/images/icons/bf2.png">
    <link rel="stylesheet" type="text/css" href="{{ app.theme_url }}/css/style.css" media="screen" />
    {%  insert 'stylesheets' %}

    <!-- Required JavaScript Plugins -->
    <script type="text/javascript" src="public/js/modernizr.custom.min.js"></script>
    <script type="text/javascript" src="public/js/jquery-3.6.3.min.js"></script>
    <script type="text/javascript" src="public/js/app.js"></script>
    <script type="text/javascript" src="{{ app.theme_url }}/js/common.js"></script>
    <script type="text/javascript" src="{{ app.theme_url }}/js/standard.js"></script>
    <script type="text/javascript" src="public/js/libs/accessiblelist/jquery.accessibleList.js"></script>

    <!-- Controller defined Javascript vars -->
    {% insert 'jsvars' %}

    <!-- Core and Page specific Scripts -->
    {% insert 'scripts' %}
</head>
<body>
<!-- Header -->

<!-- Server version and status -->
<header>
    <div class="container_12">
        <p id="skin-name"><small>Battlefield 2<br> Supported Version</small> <strong>{{ app.config.bf2_version }}</strong></p>
        <div class="server-info">Service Status: <strong style="color: lime">Online</strong></div>
    </div>
</header>
<!-- End server status -->

<!-- Logo section -->
<div id="header-bg">
    <img src="{{ app.theme_url }}/images/logo.png" alt="{{ app.site_title }}" />
</div>

<!-- Sub nav -->
<div id="sub-nav">
    <div class="container_12">
        {% run 'site-nav.widget' %}
    </div>
</div>

<!-- Status bar -->
<div id="status-bar">
    <div class="container_12">
        <ul id="status-infos">
        {% if not app.user->isGuest() %}
            <li class="spaced">Logged in as: <img src="public/images/ranks/rank_{{ app.user.rank_id }}.gif"/>
                <strong><a href="{{ app.base_url }}/account">{{ app.user.username }}</a></strong>
            </li>
            <li>
                <a href="#" class="button" title="5 messages">
                    <img src="public/images/icons/fugue/mail.png" width="16" height="16"> <strong>5</strong>
                </a>
                <div id="messages-list" class="result-block">
                    <span class="arrow"><span></span></span>
                    <ul class="small-files-list icon-mail">
                        <li>
                            <a href="#"><strong>10:15</strong> Please update...<br>
                                <small>From: System</small></a>
                        </li>
                        <li>
                            <a href="#"><strong>Yest.</strong> Hi<br>
                                <small>From: Jane</small></a>
                        </li>
                        <li>
                            <a href="#"><strong>Yest.</strong> System update<br>
                                <small>From: System</small></a>
                        </li>
                        <li>
                            <a href="#"><strong>2 days</strong> Database backup<br>
                                <small>From: System</small></a>
                        </li>
                        <li>
                            <a href="#"><strong>2 days</strong> Re: bug report<br>
                                <small>From: Max</small></a>
                        </li>
                    </ul>
                    <p id="messages-info" class="result-info">
                        <a href="{{ app.base_url }}/account/messages">Go to inbox &raquo;</a>
                    </p>
                </div>
            </li>
            <li><a href="{{ app.base_url }}/account/logout" class="button red" title="Logout"><span class="smaller">LOGOUT</span></a></li>
        {% else %}
            <li class="spaced">Welcome
                <strong>Guest</strong>
            </li>
            <li><a href="{{ app.base_url }}/account/login" class="button" title="Login">Login</a></li>
            <li>or</li>
            <li><a href="{{ app.base_url }}/account/register" class="button" title="Register">Register</a></li>
        {% endif %}
        </ul>
        <ul id="breadcrumb">
            {% insert 'breadcrumb' %}
        </ul>
    </div>
</div>
<!-- End status bar -->

<div id="header-shadow"></div>
<!-- End header -->

<div id="control-bar" class="grey-bg clearfix">
    <div class="container_12">
    {% if app.user->isGranted('admin_access') %}
        <!-- Admin only buttons -->
        <div class="float-left">
            <button type="button" class="red" onclick="window.location.href='{{ app.base_url }}/admin'">
                <img src="public/images/icons/fugue/navigation-000-white.png" alt="Admin Panel" width="16" height="16">
                Admin Panel
            </button>
            &nbsp;
            <button type="button" onclick="window.location.href='{{ app.base_url }}/admin/support'">
                <img src="public/images/icons/fugue/mail.png" alt="Support Requests" width="16" height="16">
                12 Support Requests
            </button>
        </div>
    {% endif %}
        <!-- Player search bar -->
        <div class="float-right">
            <form class="form" name="search_player" id="search_player" method="post" action="players/search" style="width: 500px;">
                <p class="input-with-button">
                    <input type="text" name="search-player" id="search-player" value="" title="Search for player" placeholder="Search player by name or PID">
                    <button type="submit" style="width: 120px">Get Stats</button>
                </p>
            </form>
        </div>
    </div>
</div>

<!-- Main content container -->
<div class="container_12">
    <noscript>
        <p class="message error">Your browser does not have JavaScript enabled! This site will not function properly!</p>
    </noscript>
    {% if app.messages is not empty %}
        {% include 'partials/message_box' with { messages: app.messages } %}
    {% endif %}
    {% insert 'contents' %}
</div>
<!-- End content -->

<footer>
    <div class="float-left">
        <a href="{{ app.base_url }}/support" class="button">Help</a>
        <a id="about-btn" href="#" class="button">About</a>
    </div>
    <div class="float-right">
        <a href="#top" class="button"><img src="public/images/icons/fugue/navigation-090.png" width="16" height="16"> Page top</a>
    </div>
</footer>
</body>
</html>