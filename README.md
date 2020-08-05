# AMP App Shell for WordPress

Add support for app shell navigation via AMP Shadow DOM.

This is a WordPress plugin which you can activate to see the app shell navigation
in action. It extends the functionality of the [AMP plugin](https://github.com/ampproject/amp-wp).

Tha app shell navigation based on AMP Shadow DOM is an experimental feature initially
proposed in [ampproject/amp-wp#1519](https://github.com/ampproject/amp-wp/pull/1519).

In order for the plugin to work, you will have to install the
[AMP](https://github.com/ampproject/amp-wp) and [PWA](https://wordpress.org/plugins/pwa/) plugins.

Note that you will also have to run `composer install && npm install && npm run build`
prior to activating the plugin.
