<?php
// Config file for Vagrant VM
$lmutil_binary="/opt/flexnetserver/lmutil";
//$lmstat_command="{$lmutil_binary} lmstat";
$cache_dir="/var/cache/phplw/";
$cache_lifetime=7200;  // In seconds. e.g. 7200 = 2 hours.
$notify_address="";
$do_not_reply_address="";
$lead_time=30;
$disable_autorefresh=0;
//$disable_license_removal=1;
$collection_interval=10; // In minutes.  Make sure this matches the cron schedule for license_util.php

//$db_type="mysqli";
$db_hostname="localhost";
$db_username="vagrant";
$db_password="vagrant";
$db_database="vagrant";
//$dsn="{$db_type}://{$db_username}:{$db_password}@{$db_hostname}/{$db_database}";

//$colors="#ffffdd,#ff9966,#ffffaa,#ccccff,#cccccc,#ffcc66,#99ff99,#eeeeee,#66ffff,#ccffff,#ffff66,#ffccff,#ff66ff,yellow,lightgreen,lightblue";
//$smallgraph="100,100";
//$largegraph="300,200";
//$legendpoints="";

// IMPORTANT: Change this to 0 when used in production!
$debug = 1;

// License servers are now in the database.
// e.g. INSERT INTO `servers` (`name`, `label`, `is_active`) VALUES ('port@host.domain.tld', 'label/description', 1);
?>
