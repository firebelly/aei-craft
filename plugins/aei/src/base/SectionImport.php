<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin for custom AEI functionality
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei\base;

/**
 * SectionImport base class
 */
class SectionImport
{
    public $localLog = '';
    public $added = 0;
    public $updated = 0;
    public $timeStart = 0;
    private $sectionName = '';
    private $summary = [];

    public function __construct($sectionName) {
        $this->sectionName = $sectionName;
        $this->timeStart = microtime(true);
    }

    public function log($log_html) {
        $this->localLog .= $log_html;
    }

    public function saved($entry, $actionVerb) {
        $this->log('<li>'.$entry->title.' '.$actionVerb.' OK!</li>');
        if ($actionVerb == 'added') {
            $this->added++;
        } else {
            $this->updated++;
        }
    }

    public function finish() {
        $exec_time = sprintf("%.2f", (microtime(true) - $this->timeStart));

        if ($this->added>0) {
            $this->summary[] = $this->added . ' ' . $this->sectionName . ' added';
        }
        if ($this->updated>0) {
            $this->summary[] = $this->updated . ' ' . $this->sectionName . ' updated';
        }
        if ($this->added + $this->updated == 0) {
            $this->summary[] = 'No ' . $this->sectionName . ' added or updated';
        }

        return [
            '<h3>'.$this->sectionName.' ('.$exec_time.' seconds)</h3><ul>'.$this->localLog.'</ul>',
            $this->summary,
        ];
    }
}
