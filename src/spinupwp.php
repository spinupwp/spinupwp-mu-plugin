<?php

use DeliciousBrains\Spinupwp\NginxCachePurge;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Bootstrap our autoloader
require_once dirname(__FILE__) . '/spinupwp/Autoloader.php';
new SpinupwpAutoloader(__DIR__);

new NginxCachePurge();
