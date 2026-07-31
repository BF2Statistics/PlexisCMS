<?php
/***************************************
*  Plexis Core Config File             *
****************************************/
$site_url = null;
$site_domain = null;
$site_webroot = null;
$force_https = false;
$csrf_enable = true;
$debug_lvl = 4;
$default_group_id = 3;
$security_seed = 'dF77c9E6rP16';
$session_length_default = 3600;
$session_length_extended = 2592000;
$session_concurrency_limit = 5;
$default_timezone = 'America/Los_Angeles';

// IDE Protocol Handler Configuration
// VS Code: 'vscode://file/%s:%d'
// PhpStorm: 'phpstorm://open?file=%s&line=%d'
// JetBrains toolkit: 'jetbrains://phpstorm/navigate/reference?project=<PROJECT_NAME>&path=%s:%d'
$editorProtocol = 'jetbrains://phpstorm/navigate/reference?project=bf2&path=%s:%d';

// Remove root from file paths in the IDE protocol handler? Jetbrains:// requires this.
$editorRemoveRoot = true;

