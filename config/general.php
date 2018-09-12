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
        'siteUrl' => 'http://aei-craft.localhost',
        'backupCommand' => '/usr/local/bin/mysqldump',
        'devMode' => true,
    ],

    // Staging environment settings
    'staging' => [
        'siteUrl' => 'http://aeieng.webfactional.com',
    ],

    // Production environment settings
    'production' => [
        'siteUrl' => 'https://www.aeieng.com',
    ],
];
