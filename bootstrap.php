<?php

/*********************************************************************************
 * Tidbit is a data generation tool for the SugarCRM application developed by
 * SugarCRM, Inc. Copyright (C) 2004-2016 SugarCRM Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo. If the display of the logo is not reasonably feasible for
 * technical reasons, the Appropriate Legal Notices must display the words
 * "Powered by SugarCRM".
 ********************************************************************************/

ini_set('memory_limit', '8096M');
set_time_limit(0);

if (!defined('sugarEntry')) define('sugarEntry', true);
define('SUGAR_DIR', __DIR__ . '/..');
define('DATA_DIR', __DIR__ . '/Data');
define('RELATIONSHIPS_DIR', __DIR__ . '/Relationships');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/install_config.php';
require_once __DIR__ . '/install_functions.php';

require_once DATA_DIR . '/DefaultData.php';
require_once DATA_DIR . '/contactSeedData.php';

chdir('..');

require_once SUGAR_DIR . '/include/entryPoint.php';
require_once SUGAR_DIR . '/config.php';
require_once SUGAR_DIR . '/include/modules.php';
require_once SUGAR_DIR . '/include/utils.php';
require_once SUGAR_DIR . '/include/database/DBManagerFactory.php';
require_once SUGAR_DIR . '/include/SugarTheme/SugarTheme.php';
require_once SUGAR_DIR . '/include/utils/db_utils.php';
require_once SUGAR_DIR . '/modules/Teams/TeamSet.php';

// TODO: This loads additional definitions into beanList and beanFiles for
// custom modules
if (file_exists(SUGAR_DIR . '/custom/application/Ext/Include/modules.ext.php')) {
    require_once(SUGAR_DIR . '/custom/application/Ext/Include/modules.ext.php');
}
if (file_exists(SUGAR_DIR . '/include/modules_override.php')) {
    require_once(SUGAR_DIR . '/include/modules_override.php');
}

if (file_exists(dirname(__FILE__) . '/../ini_setup.php')) {
    require_once dirname(__FILE__) . '/../ini_setup.php';
    set_include_path(INSTANCE_PATH . PATH_SEPARATOR . TEMPLATE_PATH . PATH_SEPARATOR . get_include_path());
}

class FakeLogger
{
    public function __call($m, $a)
    {
    }
}

$GLOBALS['log'] = new FakeLogger();
$GLOBALS['app_list_strings'] = return_app_list_strings_language('en_us');
$GLOBALS['db'] = DBManagerFactory::getInstance(); // get default sugar db

$GLOBALS['processedRecords'] = 0;
$GLOBALS['allProcessedRecords'] = 0;

$GLOBALS['modules'] = $modules;
$GLOBALS['startTime'] = microtime();
$GLOBALS['baseTime'] = time();
$GLOBALS['totalRecords'] = 0;
$GLOBALS['time_spend'] = array();


$recordsPerPage = 1000;     // Are we going to use this?
$insertBatchSize = 0;       // zero means use default value provided by storage adapter
$moduleUsingGenerators = array('KBContents', 'Categories');



$usageStr = "Usage: " . $_SERVER['PHP_SELF'] . " [-l loadFactor] [-u userCount] [-x txBatchSize] [-e] [-c] [-t] [-h] [-v]\n";
$versionStr = "Tidbit v2.0 -- Compatible with SugarCRM 5.5 through 6.0.\n";
$helpStr = <<<EOS
$versionStr
This script populates your instance of SugarCRM with realistic demo data.

$usageStr
Options
    -l loadFactor   	The number of Accounts to create.  The ratio between
                    	Accounts and the other modules is fixed in
                    	install_config.php, so the loadFactor determines the number
                    	of each type of module to create.
                    	If not specified, defaults to 1000.

    -u userCount    	The number of Users to create.  If not specified,
                    	but loadFactor is, number of users is 1/10th of
                    	loadFactor.  Otherwise, default is 100.

    -o              	Turn Obliterate Mode on.  All existing records and
                    	relationships in the tables populated by this script will
                    	be emptied.  This includes any custom data in those tables.
                    	The administrator account will not be deleted.

    -c              	Turn Clean Mode on.  All existing demo data will be
                    	removed.  No Data created within the app will be affected,
                    	and the administrator account will not be deleted.  Has no
                    	effect if Obliterate Mode is enabled.

    -t              	Turn Turbo Mode on.  Records are produced in groups of 1000
                    	duplicates.  Users and teams are not affected.
                    	Useful for testing duplicate checking or quickly producing
                    	a large volume of test data.

    -e              	Turn Existing Users Mode on.  Regardless of other settings,
                    	no Users or Teams will be created or modified.  Any new
                    	data created will be assigned and associated with existing
                    	Users and Teams.  The number of users that would normally
                    	be created is assumed to be the number of existing users.
                    	Useful for appending data onto an existing data set.

    --as_populate       Populate ActivityStream records for each user and module

    --as_last_rec <N>   Works with "--as_populate" key only. Populate last N
                        records of each module (default: all available)

    --as_number <N>     Works with "--as_populate" key only. Number of
                        ActivityStream records for each module record (default 10)

    --as_buffer <N>     Works with "--as_populate" key only. Size of ActivityStream
                        insertion buffer (default 1000)

    --storage name       Storage name, you have next options:
                        - mysql
                        - oracle
                        - csv

    -d              	Turn Debug Mode on.  With Debug Mode, all queries will be
                    	logged in a file called 'executedQueries.txt' in your
                    	Tidbit folder.

    -v              	Display version information.

    -h              	Display this help text.

    -x count            How often to commit module records - important on DBs like DB2. Default is no batches.

    -s             	Specify the number of teams per team set and per record.

    --tba               Turn Team-based ACL Mode on.

    --tba_level         Specify restriction level for Team-based ACL. Could be (minimum/medium/maximum/full).
                        Default level is medium.
    --fullteamset       Build fully intersected teamset list.

    --iterator count    This will only insert in the DB the last (count) records specified, meanwhile the
                        iterator will continue running in the loop. Used to check for orphaned records.

    --insert_batch_size Number of VALUES to be added to one INSERT statement for bean data.
                        Does Not include relations for now

    --with-tags         Turn on Tags and Tags Relations generation. If you do not specify this option,
                        default will be false.

    "Powered by SugarCRM"


EOS;

if (!function_exists('getopt')) {
    die('"getopt" function not found. Please make sure you are running PHP 5+ version');
}

$opts = getopt(
    'l:u:s:x:ecothvd',
    array(
        'fullteamset',
        'tba_level:',
        'tba',
        'with-tags',
        'as_populate',
        'as_number:',
        'as_buffer:',
        'storage:',
        'as_last_rec:',
        'iterator:',
        'insert_batch_size:',
    )
);

if ($opts === false) {
    die($usageStr);
}

if (isset($opts['v'])) {
    die($versionStr);
}
if (isset($opts['h'])) {
    die($helpStr);
}

if (isset($opts['l'])) {
    if (!is_numeric($opts['l'])) {
        die($usageStr);
    }
    $factor = $opts['l'] / $modules['Accounts'];
    foreach ($modules as $m => $n) {
        $modules[$m] *= $factor;
    }
}
if (isset($opts['u'])) {
    if (!is_numeric($opts['u'])) {
        die($usageStr);
    }
    $modules['Teams'] = $opts['u'] * ($modules['Teams'] / $modules['Users']);
    $modules['Users'] = $opts['u'];
    if (isset($opts['tba'])) {
        $modules['ACLRoles'] = ceil($modules['Users'] / $modules['ACLRoles']);
    }
}

if (isset($opts['x'])) {
    if (!is_numeric($opts['x']) || $opts['x'] < 1) {
        die($usageStr);
    }
    $_GLOBALS['txBatchSize'] = $opts['x'];
} else {
    $_GLOBALS['txBatchSize'] = 0;
}

if (isset($opts['e'])) {
    $GLOBALS['UseExistUsers'] = true;
}
if (isset($opts['c'])) {
    $GLOBALS['clean'] = true;
}
if (isset($opts['o'])) {
    $GLOBALS['obliterate'] = true;
}
if (isset($opts['t'])) {
    $GLOBALS['turbo'] = true;
}
if (isset($opts['d'])) {
    $GLOBALS['debug'] = true;
}

if (isset($opts['tba'])) {
    if (version_compare($GLOBALS['sugar_config']['sugar_version'], '7.8.0', '>=')) {
        $GLOBALS['tba'] = true;
    } else {
        echo "!!! WARNING !!!\n";
        echo "Team Based ACL Settings could not be enabled for SugarCRM version less than 7.8 \n";
        echo "!!! WARNING !!!\n";
        echo "\n";
    }
}

if (isset($GLOBALS['tba']) && $GLOBALS['tba'] == true) {
    $GLOBALS['tba_level'] = in_array($opts['tba_level'], array_keys($tbaRestrictionLevel)) ? strtolower($opts['tba_level']) : $tbaRestrictionLevelDefault;
}

if(isset($opts['fullteamset']))
{
    $GLOBALS['fullteamset'] = true;
}

if (isset($opts['as_populate'])) {
    $GLOBALS['as_populate'] = true;
    if (isset($opts['as_number'])) {
        $GLOBALS['as_number'] = $opts['as_number'];
    }
    if (isset($opts['as_buffer'])) {
        $GLOBALS['as_buffer'] = $opts['as_buffer'];
    }
    if (isset($opts['as_last_rec'])) {
        $GLOBALS['as_last_rec'] = $opts['as_last_rec'];
    }
}
if (isset($opts['iterator'])) {
    $GLOBALS['iterator'] = $opts['iterator'];
}

if (isset($GLOBALS['debug'])) {
    $GLOBALS['queryFP'] = fopen('Tidbit/executedQueries.txt', 'w');
}

if (!empty($opts['insert_batch_size']) && $opts['insert_batch_size'] > 0) {
    $insertBatchSize = ((int)$opts['insert_batch_size']);
}

// Remove Tags module, if it's not turned in CLI options
if (!isset($opts['with-tags'])) {
    unset($modules['Tags']);
}

// Do not populate KBContent and KBCategories for versions less that 7.7.0.0
if (isset($modules['Categories']) && version_compare($GLOBALS['sugar_config']['sugar_version'], '7.7.0', '<')) {
    echo "Knowledge Base Tidbit Data population is available only for 7.7.0.0 and newer versions of SugarCRM\n";
    echo "\n";

    unset($modules['Categories']);
    unset($modules['KBContents']);
}

foreach ($modules as $records) {
    $GLOBALS['totalRecords'] += $records;
}
