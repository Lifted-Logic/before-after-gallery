<?php
/**
 * Plugin Name:  LL Before & After
 * Plugin URI:   https://liftedlogic.com
 * Description:  Before & After post type with Vite HMR.
 * Version:      3.1.0
 * Author:       Lifted Logic
 * Author URI:   https://liftedlogic.com
 * Text Domain:  ll-bag
 * Domain Path:  /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

defined('ABSPATH') || exit;

define('LL_BAG_VERSION', '3.1.0');
define('LL_BAG_FILE',    __FILE__);
define('LL_BAG_PATH',    plugin_dir_path(__FILE__));
define('LL_BAG_URL',     plugin_dir_url(__FILE__));

require_once LL_BAG_PATH . 'vendor/autoload.php';
require_once LL_BAG_PATH . 'src/Hooks/functions.php';

use LiftedLogic\LLBag\Plugin;

(new Plugin())->boot();
