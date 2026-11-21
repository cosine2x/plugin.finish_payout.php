# PHP 8 Finish Payout

This plugin replaces the legacy `plugin.finishpayout.php` from Xymph's Automatic Server Control (XASECO for short).

## Features
- Paying coppers for finishing a map for the first time [Optional]
- Paying coppers for beating the gold time for the first time
- Optional payment logging
- Ignored login list; players listed here won't receive any payment

## Requirements & Dependencies
- **XASECO** with PHP 8 compatibility (available at [Codeberg - hanouta/xaseco](https://codeberg.org/hanouta/xaseco)).
- `plugin.localdatabase.php` and `plugin.rasp.php`
- Trackmania Nations Forever / Trackmania United Forever server running the above-stated XASECO version.

## Installation
1. Download the latest version of the plugin from the [Releases page](https://github.com/cosine2x/plugin.finish_payout.php/releases).
   
2. Copy `plugin.finish_payout.php` into your server's plugins directory (usually `/xaseco/plugins/`).
   
3. Open your `plugins.xml` configuration file (usually `/xaseco/plugins.xml`) and add the following line:
   ```xml
   <plugin>plugin.finish_payout.php</plugin>
   ```
   Make sure that the plugins `plugin.rasp.php` and `plugin.localdatabase.php` are BELOW plugin.finish_payout.php in the configuration file:
   ```xml
   <plugin>plugin.finish_payout.php</plugin>
   (...)
   <plugin>plugin.rasp.php</plugin>
   <plugin>plugin.localdatabase.php</plugin>
   ```
   
4. Copy `finish_config.xml` into your xaseco folder (`/xaseco/`) and edit it to fit your needs.
    
5. Restart XASECO on your server.

# Acknowledgments
Xymph's Automatic Server Control, XASECO, available @ [xaseco.org](https://www.xaseco.org/)

Hanouta's XASECO Update for PHP 8 compatibility, available @ [codeberg.org](https://codeberg.org/hanouta/xaseco/)

Legacy Finish Payout by Expressman, available @ [xaseco.org](https://plugins.xaseco.org/info.php?id=17)

## License
This project is licensed under the **GNU General Public License v3.0 (GPLv3)**. See the [LICENSE](LICENSE) file for details.
