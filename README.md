# EDD Client for Wordpress Plugins
A helper library for Wordpress Plugin which provides a simple GUI to enter license, check updates from your Custom Server manged by Easy Digital Download.

## How to Use?

1. Download the latest EDD Client from here, extract the zip and put the EDD_Client folder into the root of your Plugin directory. 
2. Add below snippets to the entry point of your main plugin file.

```$xslt
require_once('EDD_Client/EDD_Client_Init.php');
$license = new EDD_Client_Init(__FILE__, 'https://wpstars.org');
```