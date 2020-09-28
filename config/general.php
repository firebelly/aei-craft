<?php
/**
 * General Configuration
 *
 * All of your system's general configuration settings go in here. You can see a
 * list of the available settings in vendor/craftcms/cms/src/config/GeneralConfig.php.
 *
 * @see craft\config\GeneralConfig
 */

return [
    // Global settings
    '*' => [
        // Default Week Start Day (0 = Sunday, 1 = Monday...)
        'defaultWeekStartDay' => 0,

        // Enable CSRF Protection (recommended)
        'enableCsrfProtection' => true,

        // Whether "index.php" should be visible in URLs
        'omitScriptNameInUrls' => true,

        // Control Panel trigger word
        'cpTrigger' => 'admin',

        // The secure key Craft will use for hashing and encrypting data
        'securityKey' => getenv('SECURITY_KEY'),

        // 100M upload max
        'maxUploadFileSize' => '104857600',

    ],

    // Dev environment settings
    'dev' => [
        // Base site URL
        'siteUrl' => 'http://aei-craft.localhost',
        // Fix backing up mysql on dev w/ homebrew mysql
        'backupCommand' =>  "/usr/local/bin/mysqldump -h localhost -u root -proot --add-drop-table --comments --create-options --dump-date --no-autocommit --routines --set-charset --triggers --single-transaction --no-data --result-file=\"{file}\" {database} && /usr/local/bin/mysqldump -h localhost -u root -proot --add-drop-table --comments --create-options --dump-date --no-autocommit --routines --set-charset --triggers --no-create-info --ignore-table={database}.assetindexdata --ignore-table={database}.assettransformindex --ignore-table={database}.cache --ignore-table={database}.sessions --ignore-table={database}.templatecaches --ignore-table={database}.templatecachecriteria --ignore-table={database}.templatecacheelements {database} >> \"{file}\"",
        'restoreCommand' => "/usr/local/bin/mysql -h localhost -u root -proot {database} < \"{file}\"",
        // Dev Mode (see https://craftcms.com/support/dev-mode)
        'devMode' => true,
    ],

    // Staging environment settings
    'staging' => [
        'siteUrl' => 'https://wf.aeieng.com',
    ],

    // Production environment settings
    'production' => [
        'softDeleteDuration' => 86400,
        'siteUrl' => 'https://aeieng.com',
    ],
];
