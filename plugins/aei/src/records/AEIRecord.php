<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin for custom AEI functionality
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei\records;

use firebelly\aei\AEI;

use Craft;
use craft\db\ActiveRecord;

/**
 * AEIRecord Record
 *
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 * http://www.yiiframework.com/doc-2.0/guide-db-active-record.html
 *
 * @author    Firebelly Design
 * @package   AEI
 * @since     1.0.0
 */
class AEIRecord extends ActiveRecord
{
     /**
     * @return string the table name
     */
    public static function tableName()
    {
        return '{{%aei_deltek_log}}';
    }
}
