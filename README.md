# SpinupWP MU Plugin

This must-use plugin adds page cache purging functionality to your WordPress site. In addition to automatically clearing the page cache for individual posts, pages, and other content when it is updated, this plugin allows you to purge the cache for the whole site as well. You will need to download this plugin and install it in the /wp-content/mu-plugins/ folder.

## Install

1. Clone or download his repository.
1. Rename the `src` directory to `spinupwp-mu-plugin`.
1. Copy the `spinupwp-mu-plugin` directory to `/wp-content/mu-plugins`.
1. Add the following constants to your `wp-config.php`:

```
define( 'WP_REDIS_SELECTIVE_FLUSH', true );
define( 'SPINUPWP_CACHE_PATH', '/sites/{DOMAIN}/cache' );
```
