<!DOCTYPE html>
<html lang="en-US">
<head>
    <!-- Metas -->
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <!-- Mobile metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">

    <!-- Page title and icon -->
    <title>Server Error</title>
    <link rel="icon" type="image/png" href="public/images/icons/bf2.png">
    <base href="{{ app.base_url }}/">

    <!-- JavaScript Plugins -->
    <script type="text/javascript" src="public/js/modernizr.custom.min.js"></script>
    <script type="text/javascript" src="public/js/jquery-3.6.3.min.js" nonce="{{ app.csp_nonce }}"></script>
    <script type="text/javascript" src="public/themes/default/js/common.js"></script>
    <script type="text/javascript" src="public/themes/default/js/standard.js"></script>
    <script type="text/javascript" src="public/themes/default/js/lists.js"></script>
    <script type="text/javascript" src="public/js/modules/Error/error.js"></script>

    <!-- Required Stylesheets -->
    <link rel="stylesheet" type="text/css" href="public/css/core/reset.css" media="screen">
    <link rel="stylesheet" type="text/css" href="public/css/core/common.css" media="screen">
    <link rel="stylesheet" type="text/css" href="public/css/core/form.css" media="screen">
    <link rel="stylesheet" type="text/css" href="public/css/core/standard.css" media="screen">
    <link rel="stylesheet" type="text/css" href="public/css/core/special-pages.css" media="screen">
    <link rel="stylesheet" type="text/css" href="public/css/core/simple-lists.css" media="screen">
</head>
<body class="special-page error-bg red dark with-log">
    <section id="error-desc">
        <ul class="action-tabs with-children-tip children-tip-left">
            <li>
                <a href="#" id="go-back" title="Go back">
                    <img src="public/images/icons/fugue/navigation-180.png" width="16" height="16" alt="Go Back">
                </a>
            </li>
            <li>
                <a href="#" id="reload-page" title="Reload page">
                    <img src="public/images/icons/fugue/arrow-circle.png" width="16" height="16" alt="Reload Page">
                </a>
            </li>
        </ul>
        <ul class="action-tabs right with-children-tip children-tip-right">
            <li>
                <a href="#" id="toggle-log" title="Show/hide<br>error details">
                    <img src="public/images/icons/fugue/application-monitor.png" width="16" height="16" alt="Toggle Log">
                </a>
            </li>
        </ul>
        <div class="block-border">
            <div class="block-content no-title">
                <div class="block-header">{{headline}}</div>
                <h2>Error description</h2>
                <h5>Message</h5>
                <p>An error occurred while processing your request. Please return to the previous page and check everything before trying again. If the same error occurs again, please contact your system administrator or report error (see below).</p>
                <p>
                    <b>Event type:</b> {{type}}<br>
                    <b>Page:</b>
                </p>
                <form class="form" id="send-report" method="post" action="#">
                    <input type="hidden" name="report" id="report" value="">
                    <fieldset class="grey-bg no-margin collapse">
                        <legend><a href="#">Report error</a></legend>
                        <p>
                            <label for="description" class="light float-left">To report this error, please explain how it happened and click below:</label>
                            <textarea name="description" id="description" class="full-width" rows="4"></textarea>
                        </p>
                        <p>
                            <label for="report-sender" class="grey">Your e-mail address (optional)</label>
                            <span class="float-left"><button type="submit" class="full-width">Report</button></span>
                            <input type="text" name="sender" id="sender" value="" class="full-width">
                        </p>
                    </fieldset>
                </form>
            </div>
        </div>
    </section>
    <section id="error-log">
        <div class="block-border">
            <div class="block-content">
                <h1>Error details</h1>
                <div class="fieldset grey-bg with-margin">
                    <p><b>Message</b><br>
                        {{message}}
                    </p>
                </div>

                <ul class="picto-list">
                    <li class="icon-type-small"><span class="bold">Type:</span> {{type}}</li>
                    <li class="icon-tag-small"><span class="bold">Code:</span> {{code}}</li>
                    <li class="icon-doc-small"><span class="bold">File:</span> {{file}}</li>
                    <li class="icon-pin-small"><span class="bold">Line:</span> {{line}}</li>
                </ul>
            {% if has_context %}
                <ul class="collapsible-list with-bg">
                    <li class="close">
                        <b class="toggle"></b>
                        <span><b>Context:</b></span>
                        <ul class="with-icon no-toggle-icon">
                            {{ context }}
                        </ul>
                    </li>
                </ul>
            {% endif %}
                <h2>Stack Trace</h2>
                <ul class="picto-list icon-top with-line-spacing">
                    {% foreach trace in stacktrace %}
                    <li>
                        <b>{{trace.file}}</b> @ line <b>{{trace.line}}</b>:
                        {{trace.func}}({{trace.args}})
                    </li>
                    {% endforeach %}
                </ul>
            </div>
        </div>
    </section>
</body>
</html>