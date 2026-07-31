<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">
<head>
    <title>BF2 Clan Manager</title>
    <base href="{{ app.base_url }}/">
    <link rel="icon" type="image/png" href="public/images/icons/bf2.png">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <!-- Viewport Metatag -->
    <meta name="viewport" content="width=device-width,initial-scale=1.0">

    <!-- Theme and Page specific Stylesheet -->
    <link rel="stylesheet" type="text/css" href="{{ app.theme_url }}/css/style.css" media="screen" />
    <link rel="stylesheet" type="text/css" href="{{ app.theme_url }}/css/block-lists.css" media="screen" />
    <link rel="stylesheet" type="text/css" href="{{ app.theme_url }}/css/special-pages.css" media="screen" />
    <link rel="stylesheet" type="text/css" href="{{ app.theme_url }}/css/wizard.css" media="screen" />
    <link rel="stylesheet" type="text/css" href="public/css/icons/icol32.css" media="screen" />
    <link rel="stylesheet" type="text/css" href="public/js/libs/select2/select2.css" media="screen" />
    <link rel="stylesheet" type="text/css" href="{{ app.theme_url }}/css/modules/Install/installer.css" media="screen" />

    <!-- Required JavaScript Plugins -->
    <script type="text/javascript" src="public/js/modernizr.custom.min.js"></script>
    <script type="text/javascript" src="public/js/jquery-3.6.3.min.js" nonce="{{ app.csp_nonce }}"></script>
    <script type="text/javascript" src="{{ app.theme_url }}/js/common.js"></script>
    <script type="text/javascript" src="{{ app.theme_url }}/js/standard.js"></script>
    <script type="text/javascript" src="public/js/libs/accessiblelist/jquery.accessibleList.js"></script>
    <script type="text/javascript" src="public/js/libs/jquery.modal.js"></script>

    <!-- Installer specific -->
    <script type="text/javascript" src="public/js/libs/select2/select2.min.js"></script>
    <script type="text/javascript" src="public/js/libs/validate/jquery.validate.min.js"></script>
    <script type="text/javascript" src="public/js/libs/form/jquery.form.min.js"></script>
    <script type="text/javascript" src="public/js/libs/wizard/wizard.js"></script>
    <script type="text/javascript" src="public/js/modules/Install/index.js"></script>
</head>
<!-- the 'special-page' class is only an identifier for scripts -->
<body class="special-page wizard-bg">
<section>
    <div id="install-container" class="block-border">
        <div class="block-content">

            <!-- Header block -->
            <div class="block-header">Battlefield 2 - Private Stats Content Management System</div>
            <p>
				Welcome, Commander. You are about to deploy the <strong>Plexis CMS</strong>, the ultimate private stats frontend solution for Battlefield 2. This wizard will guide you 
				through configuring your database, securing your environment, and linking directly with the BF2Statistics system by Wilson212. Follow the steps below to establish your 
				headquarters and bring the ranked experience back to your soldiers.
			</p>
            <ul class="message warning no-margin">
                <li>If you think you are seeing this page in error, it means you are missing the "installer.lock" file located in the application config folder!</li>
            </ul>
        </div>
        <div class="block-content no-title">

            <!-- Main form -->
            <form class="form inline-label" name="install-form" id="install-form" method="post">
                <input type="hidden" id="csrf_token" name="csrf_token" value="{{ app.session.csrf_token }}" />

                <!-- Ajax messages -->
                <div id="ajax-message" class="message error">
                    This is an <strong>error message</strong>, inside a form
                </div>

                <!-- Page 1 -->
                <div id="tab-page-1" class="wizard-step">
                    <span class="wizard-label">Settings</span>
                    <span class="number bigger">1</span>
                    <small>Setup wizard</small>
                    <h2 class="bigger">Website Settings</h2>

                    <fieldset>
                        <legend>Basic Settings</legend>
                        <p>
                            <span class="relative">
                                <label for="cfg__site_title"><span class="big">Website Name:</span></label>
                                <input type="text" name="cfg__site_title" id="cfg__site_title" class="full-width" value="{{site_title}}">
                                <span id="cfg__site_title-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__keywords"><span class="big">Keywords:</span></label>
                                <input type="text" name="cfg__keywords" id="cfg__keywords" class="full-width" value="{{keywords}}">
                                <span id="cfg__keywords-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__description"><span class="big">Description:</span></label>
                                <input type="text" name="cfg__description" id="cfg__description" class="full-width" value="{{description}}">
                                <span id="cfg__description-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__default_timezone"><span class="big">Default Timezone:</span></label>
                                <select name="cfg__default_timezone" id="cfg__default_timezone" class="full-width">
                                {% foreach region, list in timezones %}
                                    <optgroup label="{{region}}">
                                    {% foreach timezone, name in list %}
                                        <option value="{{timezone}}" {% if timezone is default_timezone %} selected="selected"{% endif %}>{{name}}</option>
                                    {% endforeach %}
                                    </optgroup>
                                {% endforeach %}
                                </select>
                                <span id="cfg__default_timezone-tick"></span>
                            </span>
                        </p>
                    </fieldset>

                    <fieldset>
                        <legend>Security Settings</legend>
                        <div class="box">
                            <p class="infos">
                                The security seed is used to hash passwords stored in your database.
                                Ensure that this is a strong and secure value to prevent unauthorized
                                access or password cracking attempts. This can be a random string of
                                characters or a complex phrase. If using the BattleSpy GameSpy emulator,
                                then this value will need to match the value in the BattleSpy config file.
                                Leave blank if not using the BattleSpy Emulator to enable md5 hashed passwords
                                (compatibility mode).
                                <strong>
                                    This cannot be changed once accounts start being created or all passwords will need to be reset!
                                </strong>
                            </p>
                        </div>
                        <br>
                        <p>
                            <span class="relative">
                                <label for="cfg__security_seed"><span class="big">Security Seed:</span></label>
                                <input type="text" name="cfg__security_seed" id="cfg__security_seed" class="full-width" value="{{security_seed}}">
                                <span id="cfg__security_seed-tick"></span>
                            </span>
                        </p>
                    </fieldset>
                </div>

                <!-- Page 2 -->
                <div id="tab-page-2" class="wizard-step">
                    <span class="wizard-label">Database</span>
                    <span class="number bigger">2</span>
                    <small>Setup wizard</small>
                    <h2 class="bigger">Database Settings</h2>

                    <fieldset id="stats" class="required">
                        <legend>Stats Database</legend>
                        <p>
                            <span class="relative">
                                <label for="cfg__stats_db_type"><span class="big">Database Type:</span></label>
                                <select name="cfg__stats_db_type" id="cfg__stats_db_type" class="full-width">
                                <option disabled selected>Select Database Type</option>
                            {% foreach dirName, metadata in db_drivers %}
                                <option value="{{dirName}}"{% if dirName is database.stats.driver %} selected{% endif %}>{{metadata->itemAt('name')}}</option>
                            {% endforeach %}
                                </select>
                                <span id="cfg__stats_db_type-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__stats_db_host"><span class="big">Database Host:</span></label>
                                <input type="text" name="cfg__stats_db_host" id="cfg__stats_db_host" class="full-width" value="{{ database.stats.host }}">
                                <span id="cfg__stats_db_host-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__stats_db_port"><span class="big">Database Port:</span></label>
                                <input type="text" name="cfg__stats_db_port" id="cfg__stats_db_port" class="full-width" value="{{ database.stats.port }}">
                                <span id="cfg__stats_db_port-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__stats_db_user"><span class="big">Database Username:</span></label>
                                <input type="text" name="cfg__stats_db_user" id="cfg__stats_db_user" class="full-width" value="{{ database.stats.username }}">
                                <span id="cfg__stats_db_user-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__stats_db_pass"><span class="big">Database Password:</span></label>
                                <span class="input-type-text full-width">
                                    <input type="password" name="cfg__stats_db_pass" id="cfg__stats_db_pass" value="{{ database.stats.password }}">
                                    <a id="toggle-pass-1" class="toggle-password" href="#">
                                        <img src="public/images/icons/fugue/magnifier.png" width="16" height="16">
                                    </a>
                                    <span id="cfg__stats_db_pass-tick"></span>
                                </span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__stats_db_name"><span class="big">Database Name:</span></label>
                                <input type="text" name="cfg__stats_db_name" id="cfg__stats_db_name" class="full-width" value="{{ database.stats.database }}">
                                <span id="cfg__stats_db_name-tick"></span>
                            </span>
                        </p>
                        <p>
                            <input type="checkbox" name="sameDatabase" id="sameDatabase" value="1" class="switch" checked="checked"> &nbsp;
                            <label for="sameDatabase" class="inline grey">Use same database for website tables?</label>
                        </p>
                    </fieldset>

                    <fieldset id="web" class="required">
                        <legend>Bf2 Web Database</legend>
                        <p>
                            <span class="relative">
                                <label for="cfg__web_db_type"><span class="big">Database Type:</span></label>
                                <select name="cfg__web_db_type" id="cfg__web_db_type" class="full-width">
                                    <option disabled selected>Select Database Type</option>
                                {% foreach dirName, metadata in db_drivers %}
                                    <option value="{{dirName}}"{% if dirName is database.web.driver %} selected{% endif %}>{{metadata.name}}</option>
                                {% endforeach %}
                                </select>
                                <span id="cfg__web_db_type-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__web_db_host"><span class="big">Database Host:</span></label>
                                <input type="text" name="cfg__web_db_host" id="cfg__web_db_host" class="full-width" value="{{ database.web.host }}">
                                <span id="cfg__web_db_host-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__web_db_port"><span class="big">Database Port:</span></label>
                                <input type="text" name="cfg__web_db_port" id="cfg__web_db_port" class="full-width" value="{{ database.web.port }}">
                                <span id="cfg__web_db_port-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__web_db_user"><span class="big">Database Username:</span></label>
                                <input type="text" name="cfg__web_db_user" id="cfg__web_db_user" class="full-width" value="{{ database.web.username }}">
                                <span id="cfg__web_db_user-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__web_db_pass"><span class="big">Database Password:</span></label>
                                <span class="input-type-text full-width">
                                    <input type="password" name="cfg__web_db_pass" id="cfg__web_db_pass" value="{{ database.web.password }}">
                                    <a id="toggle-pass-2" class="toggle-password" href="#">
                                        <img src="public/images/icons/fugue/magnifier.png" width="16" height="16">
                                    </a>
                                    <span id="cfg__web_db_pass-tick"></span>
                                </span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="cfg__web_db_name"><span class="big">Database Name:</span></label>
                                <input type="text" name="cfg__web_db_name" id="cfg__web_db_name" class="full-width" value="{{ database.web.database }}">
                                <span id="cfg__web_db_name-tick"></span>
                            </span>
                        </p>
                    </fieldset>
                </div>

                <!-- Page 3 -->
                <div id="tab-page-3" class="wizard-step">
                    <span class="wizard-label">Connect</span>
                    <span class="number bigger">3</span>
                    <small>Setup wizard</small>
                    <h2 class="bigger">Test Connection</h2>
                    <p>
                        <div id="connecting-message">
                            Connecting to Database Servers... Do NOT Refresh This Page!
                        </div>
                        <div class="progress-container">
                            <i class="icol32-computer"></i>
                            <i>
                                <span id="db-progress-bar" class="progress-bar">
                                    <span class="with-stripes"></span>
                                </span>
                            </i>
                            <i class="icol32-database"></i>
                        </div>
                    </p>
                </div>

                <div id="tab-page-4" class="wizard-step">
                    <span class="wizard-label">Install Tables</span>
                    <span class="number bigger">4</span>
                    <small>Setup wizard</small>
                    <h2 class="bigger">Install Data Tables</h2>
                    <p>
                        <div id="installing-tables-message">
                            Installing Website Tables... Do NOT Refresh This Page!
                        </div>
                        <div class="progress-container">
                            <i class="icol32-computer"></i>
                            <i>
                                <span id="tables-progress-bar" class="progress-bar">
                                    <span class="with-stripes"></span>
                                </span>
                            </i>
                            <i class="icol32-database"></i>
                        </div>
                    </p>
                </div>

                <div id="tab-page-5" class="wizard-step">
                    <span class="wizard-label">Finish</span>
                    <span class="number bigger">5</span>
                    <small>Setup wizard</small>
                    <h2 class="bigger">Define Owner Account</h2>
                    <fieldset id="admin" class="required">
                        <div class="box">
                            <p class="infos">
                                In order to access the admin panel, you must define an account to be the owner of the website.
                                This account will be used to manage the website and its content, and will be able to define
                                account permissions for other users. This account can either be an existing Battlefield 2 account,
                                or a new account that will be created.
                                <strong>
                                    This account cannot be changed once defined, and will always be the owner of the website!
                                </strong>
                            </p>
                        </div>
                        <br>
                        <p>
                            <span class="relative">
                                <label for="super_admin_user"><span class="big">Username:</span></label>
                                <input type="text" name="super_admin_user" id="super_admin_user" class="full-width" value="">
                                <span id="super_admin_user-tick"></span>
                            </span>
                        </p>
                        <p>
                            <span class="relative">
                                <label for="super_admin_pass"><span class="big">Password:</span></label>
                                <span class="input-type-text full-width">
                                    <input type="password" name="super_admin_pass" id="super_admin_pass" value="">
                                    <a id="toggle-pass-3" class="toggle-password" href="#">
                                        <img src="public/images/icons/fugue/magnifier.png" width="16" height="16">
                                    </a>
                                    <span id="super_admin_pass-tick"></span>
                                </span>
                            </span>
                        </p>
                        <p>
                            <input type="checkbox" name="newAccount" id="newAccount" value="0" class="switch"> &nbsp;
                            <label for="newAccount" class="inline grey">Is this a new Battlefield 2 account?</label>
                        </p>
                        <div id="newAccount-container">
                            <p>
                                <span class="relative">
                                    <label for="super_admin_email"><span class="big">Email:</span></label>
                                    <input type="text" name="super_admin_email" id="super_admin_email" class="full-width" value="">
                                    <span id="super_admin_email-tick"></span>
                                </span>
                            </p>
                            <p>
                                <span class="relative">
                                    <label for="super_admin_iso"><span class="big">Country:</span></label>
                                    <select name="super_admin_iso" id="super_admin_iso" class="full-width select2">
                                        <option disabled selected>Select Country</option>
                                    {% foreach iso, name in countries %}
                                        <option value="{{ iso }}">{{ name }}</option>
                                    {% endforeach %}
                                    </select>
                                    <span id="super_admin_iso-tick"></span>
                                </span>
                            </p>
                        </div>
                    </fieldset>
                </div>

            </form>

            <!-- Wizard Navigation Buttons -->
            <p id="wizard-button-row"></p>

            <!-- Footer block -->
            <div class="block-footer">
                <div class="float-right">
                    Copyright &copy; 2026 <a href="https://github.com/BF2Statistics" target="_blank">BF2Statistics</a>
                </div>
                Created by Steven Wilson (Wilson212) for the Battlefield 2 community. All rights reserved.
            </div>
        </div>
        <div id="install-modal" >
            <span id="install-result">
                <img src="{{ app.theme_url }}/images/Check.png">
            </span>
            <p>
                Congratulations! The essential configurations required to launch the website have been successfully completed.
                Please proceed to the admin panel to finalize the setup and complete the remaining configurations.
                By clicking the "Finish" button, you will be redirected to the admin panel.
            </p>
        </div>
    </div>
</section>
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