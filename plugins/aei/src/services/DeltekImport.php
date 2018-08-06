<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin for custom AEI functionality
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei\services;

use firebelly\aei\AEI;
use firebelly\aei\base\SectionImport;
use firebelly\aei\records\DeltekLog;
use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\Asset;
use craft\elements\Category;
use craft\records\EntryType;
use craft\mail\Message;
use verbb\supertable\SuperTable;
use yii\db\Expression;

/**
 * DeltekImport Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Firebelly Design
 * @package   AEI
 * @since     1.0.0
 */
class DeltekImport extends Component
{
    private $deltekDb = null;
    private $log = '';
    private $summary = [];
    private $deltekIds = [];
    private $categoriesCache = [];
    private $superTableQuotesField = null;

    /**
     * Returns deltek log records for /admin/aei/logs template
     * @return [array] active records
     */
    public function getDeltekLogs()
    {
        $logs = DeltekLog::find()->orderBy('dateCreated DESC')->all();
        return $logs;
    }

    /**
     * Run Deltek Import
     *
     * AEI::$plugin->deltekImport->importRecords()
     *
     * @return string
     */
    public function importRecords($sectionsToImport, $deltekIds='', $importMode='basic')
    {
        $timeStart = microtime(true);
        if (empty($sectionsToImport)) {
            return (object) [
                'log' => 'Nothing done.',
                'summary' => 'No sections selected to import.',
                'exec_time' => '0',
            ];
        }

        // Optional IDs to specify which entries to import
        if (!empty($deltekIds)) {
            $this->deltekIds = array_map('trim', explode(',', $deltekIds));
        }

        // Connect to Deltek db
        try {
            $this->deltekDb = new \PDO('mysql:host='.getenv('DELTEK_DB_SERVER').';dbname='.getenv('DELTEK_DB_DATABASE').';charset=utf8', getenv('DELTEK_DB_USER'), getenv('DELTEK_DB_PASSWORD'));
            $this->deltekDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch(\PDOException $e) {
            $this->bomb('PDO Error: ' . $e->getMessage());
        }

        // Import all sections specified in $sectionsToImport array
        try {
            if (in_array('offices', $sectionsToImport)) {
                $this->importOffices();
            }
            if (in_array('people', $sectionsToImport)) {
                $this->importPeople();
            }
            if (in_array('awards', $sectionsToImport)) {
                $this->importAwards();
            }
            if (in_array('projects', $sectionsToImport)) {
                $this->importProjects($importMode);
            }
            if (in_array('impact', $sectionsToImport)) {
                $this->importImpact($importMode);
            }
        } catch (\Exception $e) {
            $this->bomb('Import Error: ' . $e->getMessage());
        }

        // Store import summary + log in aei_deltek_log table
        $deltekLog = new DeltekLog();
        $deltekLog->log = $this->log;
        $deltekLog->summary = implode(', ', $this->summary);
        $deltekLog->save();

        // Clear out older logs
        $this->cleanUpLogs();

        $execTime = sprintf("%.2f", (microtime(true) - $timeStart));

        return (object) [
            'log'     => $this->log,
            'summary' => implode(', ', $this->summary),
            'exec_time' => $execTime,
        ];
    }

    /**
     * Import Offices
     */
    private function importOffices() {
        $officesImport = new SectionImport('Offices');

        $result = $this->deltekDb->query("SELECT * FROM offices");
        foreach($result as $row) {
            // Filter by delted_ids passed in?
            if (!empty($this->deltekIds) && !in_array($row['office_name'], $this->deltekIds)) continue;

            $actionVerb = 'updated';
            $entry = Entry::find()->section('offices')->where([
                'title' => $row['office_name']
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('offices');
                $entry->title = $row['office_name'];
                $actionVerb = 'added';
            }

            // Get our Super Table field
            $field = Craft::$app->getFields()->getFieldByHandle('quotes');
            $blockTypes = SuperTable::$plugin->service->getBlockTypesByFieldId($field->id);
            $blockType = $blockTypes[0];

            // Find Office Quotes
            $officeQuotes = [];
            $i = 0;

            $relResult = $this->deltekDb->prepare('SELECT * FROM office_quotes WHERE office_name = ?');
            $relResult->execute([ $row['office_name'] ]);
            $relRows = $relResult->fetchAll();
            foreach($relRows as $relRow) {
                $i++;
                if (!empty($relRow['employee_num'])) {
                    $person = Entry::find()->section('people')->where([
                        'content.field_personEmployeeNumber' => $relRow['employee_num']
                    ])->one();
                    $aeiPerson = ($person) ? [$person->id] : [];
                } else {
                    $aeiPerson = [];
                }
                $officeQuotes['new'.$i] = [
                    'type' => $blockType->id,
                    'fields' => [
                        'quote'         => $relRow['quote'],
                        'personName'    => '', // no override field in office_quotes table for this
                        'personCompany' => $relRow['employee_title'],
                        'quoteKey'      => $relRow['quote_key'],
                        'aeiPerson'     => $aeiPerson,
                    ]
                ];
            }

            // Find Office Leaders
            $officeLeaders = [];
            $i = 0;

            $relResult = $this->deltekDb->prepare('SELECT * FROM office_leadership WHERE office_name = ?');
            $relResult->execute([ $row['office_name'] ]);
            $relRows = $relResult->fetchAll();
            foreach($relRows as $relRow) {
                $i++;
                $person = Entry::find()->section('people')->where([
                    'content.field_personEmployeeNumber' => $relRow['employee_num']
                ])->one();
                if ($person) {
                    $officeLeaders['new'.$i] = [
                        'type' => 'person',
                        'fields' => [
                            'aeiPerson'   => [$person->id],
                            'personTitle' => $relRow['employee_title'],
                        ]
                    ];
                }
            }


            $entry->setFieldValues([
                'officeAddress1'   => $row['address1'],
                'officeAddress2'   => $row['address2'],
                'officeCity'       => $row['city'],
                'officeState'      => $row['state'],
                'officePostalCode' => $row['postal_code'],
                'officeCountry'    => $row['country'],
                'phoneNumber'      => $row['phone'],
                'body'             => $row['overview'],
                'careersUrl'       => (!empty($row['careers_url']) ? $this->validUrl($row['careers_url']) : ''),
                'officeMapUrl'     => $this->validUrl($row['map_url']),
                'officeLeaders'    => $officeLeaders,
                'quotes'           => $officeQuotes,
                'officeImage'      => $this->getPhoto($row['photo_url']),
            ]);

            if(Craft::$app->getElements()->saveElement($entry)) {
                $officesImport->saved($entry, $actionVerb);
            } else {
                $this->bomb('<li>Save error: '.print_r($entry->getErrors(), true).'</li>');
            }
        }
        list($log, $summary) = $officesImport->finish();
        $this->log .= $log;
        $this->summary = array_merge($summary, $this->summary);
    }

    /**
     * Import People
     */
    private function importPeople() {
        $peopleImport = new SectionImport('People');

        $result = $this->deltekDb->query("SELECT * FROM employees");
        foreach($result as $row) {
            // Filter by delted_ids passed in?
            if (!empty($this->deltekIds) && !in_array($row['employee_num'], $this->deltekIds)) continue;

            $actionVerb = 'updated';
            $entry = Entry::find()->section('people')->where([
                'content.field_personEmployeeNumber' => $row['employee_num']
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('person');
                $actionVerb = 'added';
            }

            // Find Office
            $office = Entry::find()->section('offices')->where([
                'title' => $row['officename']
            ])->one();
            $officeIds = $office ? [$office->id] : [];

            // Find Person Quote
            $personQuote = '';
            $relResult = $this->deltekDb->prepare('SELECT * FROM employee_quotes WHERE employee_num = ?');
            $relResult->execute([ $row['employee_num'] ]);
            $relRows = $relResult->fetchAll();
            foreach($relRows as $relRow) {
                // Remove quotes around text
                $personQuote = trim(str_replace('&nbsp;', ' ', $relRow['quote']), ' "”“');
            }

            // Find People Type IDs
            $personTypeIds = [];
            foreach (explode(',', $row['primary_category']) as $categoryTitle) {
                if ($category = $this->getCategory('peopleTypes', trim($categoryTitle))) {
                    $personTypeIds[] = $category->id;
                }
            }

            // Find Secondary People Type IDs
            $secondaryPersonTypeIds = [];
            foreach (explode(',', $row['secondary_category']) as $categoryTitle) {
                if ($category = $this->getCategory('peopleTypes', trim($categoryTitle))) {
                    $secondaryPersonTypeIds[] = $category->id;
                }
            }

            // Populate Social Links (matrix field), currently just Linkedin
            $socialLinks = [];
            if (!empty($row['linkedin'])) {
                $socialLinks = [
                    'new1' => [
                        'type' => 'socialLink',
                        'fields' => [
                            'socialNetwork' => 'LinkedIn',
                            'socialUrl'     => $row['linkedin'],
                        ]
                    ],
                ];
            }

            $entry->setFieldValues([
                'email'                => $row['email'],
                'personFirstName'      => $row['firstname'],
                'personLastName'       => $row['lastname'],
                'personCertifications' => $row['certifications'],
                'phoneNumber'          => $row['phone'],
                'personTitle'          => $row['title'],
                'body'                 => $row['bio'],
                'personEmployeeNumber' => $row['employee_num'],
                'featured'             => $row['is_featured'],
                'office'               => $officeIds,
                'personType'           => $personTypeIds,
                'secondaryPersonType'  => $secondaryPersonTypeIds,
                'socialLinks'          => $socialLinks,
                'personQuote'          => $personQuote,
                'personImage'          => $this->getPhoto($row['photo_url']),
            ]);

            if(Craft::$app->getElements()->saveElement($entry)) {
                $peopleImport->saved($entry, $actionVerb);
            } else {
                $this->bomb('<li>Save error: '.print_r($entry->getErrors(), true).'</li>');
            }
        }
        list($log, $summary) = $peopleImport->finish();
        $this->log .= $log;
        $this->summary = array_merge($summary, $this->summary);
    }

    /**
     * Import Awards
     */
    private function importAwards() {
        $awardsImport = new SectionImport('Awards');

        $result = $this->deltekDb->query("SELECT * FROM project_awards");
        foreach($result as $row) {
            // Filter by delted_ids passed in?
            if (!empty($this->deltekIds) && !in_array($row['award_key'], $this->deltekIds)) continue;

            $entry = Entry::find()->section('awards')->where([
                'content.field_awardKey' => $row['award_key']
            ])->one();

            $actionVerb = 'updated';
            if (!$entry) {
                $entry = $this->makeNewEntry('awards');
                $entry->title = $row['name'];
                $actionVerb = 'added';
            }

            $entry->setFieldValues([
                'awardDate'   => $row['date'],
                'awardIssuer' => $row['issuer'],
                'awardKey'    => $row['award_key'],
            ]);

            if(Craft::$app->getElements()->saveElement($entry)) {
                $awardsImport->saved($entry, $actionVerb);
            } else {
                $this->bomb('<li>Save error: '.print_r($entry->getErrors(), true).'</li>');
            }
        }
        list($log, $summary) = $awardsImport->finish();
        $this->log .= $log;
        $this->summary = array_merge($summary, $this->summary);
    }

    /**
     * Import Impact
     */
    private function importImpact($importMode) {
        $impactImport = new SectionImport('Impact');
        $impactImport->setImportMode($importMode);

        $result = $this->deltekDb->query("SELECT * FROM impacts");
        foreach($result as $row) {
            // Filter by delted_ids passed in?
            if (!empty($this->deltekIds) && !in_array($row['impact_key'], $this->deltekIds)) continue;

            $actionVerb = 'updated';
            $entry = Entry::find()
                        ->section('impact')
                        ->with([ 'mediaBlocks' ])
                        ->where([
                            'content.field_impactKey' => $row['impact_key']
                        ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('impact');
                $actionVerb = 'added';
                $deltekIdsImported = [];
                $mediaBlocks = [];
                $body = $this->formatText($row['body']);
            } else {
                $deltekIdsImported = explode(',', $entry->deltekIdsImported);
                // Pull existing mediaBlocks Value
                $mediaBlocksField = Craft::$app->getFields()->getFieldByHandle('mediaBlocks');
                $existingMatrixQuery = $entry->getFieldValue('mediaBlocks');
                $mediaBlocks = $mediaBlocksField->serializeValue($existingMatrixQuery, $entry);
                // Don't populate body from deltek unless new entry
                $body = $entry->body;
            }
            // Always update title from Deltek
            $entry->title = $row['title'];

            /////////////////////////////////////
            // Only pull mediaBlocks if adding new entry, or we're doing a refresh from Deltek
            if ($actionVerb == 'added' || $importMode == 'refresh') {
                // Add matrix fields (quotes, images)
                $i = 0;

                // Find impact images
                list($heroImage, $relatedImages, $deltekIdsImported) = $this->getRelatedPhotos('impact_photos', 'impact_key', $row['impact_key'], $i, 'photo_key', $deltekIdsImported);
                // Update matrix field index
                $i = $i + count($relatedImages);
                // Merge in any images found to matrix
                $mediaBlocks = array_merge($mediaBlocks, $relatedImages);

                list($relatedQuotes, $deltekIdsImported) = $this->getRelatedQuotes('impact_quotes', 'impact_key', $row['impact_key'], 'quote_key', $deltekIdsImported);
                foreach ($relatedQuotes as $relatedQuote) {
                    $i++;
                    $mediaBlocks = array_merge($mediaBlocks, ['new'.$i => [
                        'type' => 'quotes',
                        'fields' => [
                            'quotes' => $relatedQuote,
                        ]
                    ]]);
                }

            } else {

                // Set old heroImage if not refreshing
                $heroImage = $entry->impactImage;

            }

            // Find Market IDs
            $marketIds = [];
            $markets = implode(',', array_filter([
                $row['primary_market'],
                $row['secondary_market'],
                $row['tertiary_market'],
            ]));
            foreach (explode(',', $markets) as $categoryTitle) {
                $category = $this->getCategory('markets', trim($categoryTitle));
                if ($category) {
                    $marketIds[] = $category->id;
                }
            }

            // Associate Projects with Impact
            $projectIds = [];
            $relResult = $this->deltekDb->prepare("SELECT * FROM impact_projects WHERE impact_key = ?");
            $relResult->execute([ $row['impact_key'] ]);
            $relRows = $relResult->fetchAll();
            foreach($relRows as $relRow) {
                // See if this project is imported already
                $project = Entry::find()->section('projects')->where([
                    'content.field_projectNumber' => $relRow['project_num'],
                ])->one();
                if ($project) {
                    $projectIds[] = $project->id;
                }
            }

            // Some fields have duplicate contexts based on category
            if ($row['category']=='Presentations') {
                $sessionDate = new \DateTime($row['session_date']);
                $conferenceUrl = $this->validUrl($row['url']);
                $conferenceHost = $row['host_or_publication'];
                $impactPublication = '';
                $impactPublicationUrl = '';
            } else {
                $sessionDate = '';
                $conferenceUrl = '';
                $conferenceHost = '';
                $impactPublication = $row['host_or_publication'];
                $impactPublicationUrl = $this->validUrl($row['url']);
                $impactPublicationDate = $this->validUrl($row['session_date']);
            }

            $fields = [
                'body'                 => $body,
                // 'excerpt'           => $row['excerpt'], // This isn't currently being sent
                'sessionDate'          => $sessionDate,
                'conferenceUrl'        => $conferenceUrl,
                'conferenceHost'       => $conferenceHost,
                'conferenceLocation'   => $row['location'],
                'impactPublication'    => $impactPublication,
                'impactPublicationUrl' => $impactPublicationUrl,
                'markets'              => $marketIds,
                'impactType'           => $this->getImpactType($row['category']),
                'impactPeople'         => $this->getImpactPeopleMatrix($row['impact_key']),
                'impactKey'            => $row['impact_key'],
                'relatedProjects'      => $projectIds,
                'featured'             => (!empty($row['is_featured']) ? 1 : 0),
                'deltekIdsImported'    => implode(',', $deltekIdsImported),
                'impactImage'          => $heroImage,
            ];
            // Only add/update media blocks if adding new entry, or we're doing a refresh from Deltek
            if ($actionVerb == 'added' || $importMode == 'refresh') {
                $fields = array_merge($fields, [
                    'mediaBlocks'  => $mediaBlocks
                ]);
            }
            $entry->setFieldValues($fields);
            $entry->postDate = new \DateTime($row['date']);
            // $entry->enabled = (!isset($row['is_enabled']) || !empty($row['is_enabled']) ? 1 : 0);

            if(Craft::$app->getElements()->saveElement($entry)) {
                $impactImport->saved($entry, $actionVerb);
                // Set postDate after save if new post (can't set on first save)
                if ($actionVerb == 'added') {
                    $entry->postDate = new \DateTime($row['date']);
                    Craft::$app->getElements()->saveElement($entry);
                } else {
                    // Also update any drafts for post
                    $drafts = Craft::$app->getEntryRevisions()->getDraftsByEntryId($entry->id);
                    foreach ($drafts as $draft) {
                        $draft->setFieldValues($fields);
                        $draft->title = $row['title'];
                        Craft::$app->getEntryRevisions()->saveDraft($draft);
                    }
                }
            } else {
                $this->bomb('<li>Save error: '.print_r($entry->getErrors(), true).'</li>');
            }
        }
        list($log, $summary) = $impactImport->finish();
        $this->log .= $log;
        $this->summary = array_merge($summary, $this->summary);
    }

    /**
     * Import Projects
     */
    private function importProjects($importMode) {
        $projectsImport = new SectionImport('Projects');
        $projectsImport->setImportMode($importMode);
        $result = $this->deltekDb->query('SELECT * FROM projects');
        foreach($result as $row) {
            // Filter by delted_ids passed in?
            if (!empty($this->deltekIds) && !in_array($row['project_num'], $this->deltekIds)) continue;

            $entry = Entry::find()
                        ->section('projects')
                        ->with([ 'mediaBlocks' ])
                        ->where([
                            'content.field_projectNumber' => $row['project_num']
                        ])->one();

            $actionVerb = 'updated';
            if (!$entry) {
                $entry = $this->makeNewEntry('projects');
                $actionVerb = 'added';
                $colorSwatch = AEI::$plugin->findProjectColor->randomSwatch();
                $deltekIdsImported = [];
                $mediaBlocks = [];
                $body = $this->formatText($row['case_study']);
            } else {
                $deltekIdsImported = explode(',', $entry->deltekIdsImported);
                // Pull existing mediaBlocks Value
                $mediaBlocksField = Craft::$app->getFields()->getFieldByHandle('mediaBlocks');
                $existingMatrixQuery = $entry->getFieldValue('mediaBlocks');
                $mediaBlocks = $mediaBlocksField->serializeValue($existingMatrixQuery, $entry);
                // Maintain colorswatch from saved entry
                $colorSwatch = $entry->colorSwatch;
                // Don't populate body from deltek unless new entry
                $body = $entry->body;
            }

            /////////////////////////////////////
            // Only pull mediaBlocks if adding new entry, or we're doing a refresh from Deltek
            if ($actionVerb == 'added' || $importMode == 'refresh') {

                // Add matrix fields (stats, quotes, images)
                $i = 0;

                list($relatedQuotes, $deltekIdsImported) = $this->getRelatedQuotes('project_quotes', 'project_num', $row['project_num'], 'quote_key', $deltekIdsImported);
                foreach ($relatedQuotes as $relatedQuote) {
                    $i++;
                    $mediaBlocks = array_merge($mediaBlocks, ['new'.$i => [
                        'type' => 'quotes',
                        'fields' => [
                            'quotes' => $relatedQuote,
                        ]
                    ]]);
                }

                // Project Stats
                $projectStats = [];
                $inSql = '';
                $params = [ $row['project_num'] ];
                if (count($deltekIdsImported) > 0) {
                    $in  = str_repeat('?,', count($deltekIdsImported) - 1) . '?';
                    $inSql = ' AND `stat_key` NOT IN ('.$in.')';
                    $params = array_merge($params, $deltekIdsImported);
                }
                $relResult = $this->deltekDb->prepare('SELECT * FROM project_stats WHERE project_num = ?'.$inSql);
                $relResult->execute($params);
                $relRows = $relResult->fetchAll();
                foreach($relRows as $relRow) {
                    $i++;
                    $projectStats['new'.$i] = [
                        'type' => 'stat',
                        'fields' => [
                            'statFigure' => $this->fixStatFigure($relRow['text']),
                            'statLabel'  => $relRow['subtext'],
                            'statKey'    => $relRow['stat_key'],
                        ]
                    ];
                    $deltekIdsImported[] = $relRow['stat_key'];
                }
                $mediaBlocks = array_merge($mediaBlocks, $projectStats);

                // Find project images
                list($heroImage, $relatedImages, $deltekIdsImported) = $this->getRelatedPhotos('project_photos', 'project_num', $row['project_num'], $i, 'photo_key', $deltekIdsImported);
                // Update matrix field index
                $i = $i + count($relatedImages);
                // Merge in any images found to matrix
                $mediaBlocks = array_merge($mediaBlocks, $relatedImages);

            } else {

                // Set old heroImage if not refreshing
                $heroImage = $entry->projectImage;

            }

            // Find Service IDs
            $serviceIds = [];
            foreach (explode(',', $row['services']) as $categoryTitle) {
                $category = $this->getCategory('services', trim($categoryTitle));
                if ($category) {
                    $serviceIds[] = $category->id;
                }
            }

            // Find Market IDs
            $marketIds = [];
            $markets = implode(',', array_filter([
                $row['primary_market'],
                $row['secondary_market'],
                $row['tertiary_market'],
            ]));
            foreach (explode(',', $markets) as $categoryTitle) {
                $category = $this->getCategory('markets', trim($categoryTitle));
                if ($category) {
                    $marketIds[] = $category->id;
                }
            }

            // Find Project Awards
            $awardIds = [];
            $relResult = $this->deltekDb->prepare("SELECT * FROM project_awards WHERE project_num = ?");
            $relResult->execute([ $row['project_num'] ]);
            $relRows = $relResult->fetchAll();
            foreach($relRows as $relRow) {
                // See if this award is imported already
                $award = Entry::find()->section('awards')->where([
                    'content.field_awardKey' => $relRow['award_key'],
                ])->one();
                if ($award) {
                    $awardIds[] = $award->id;
                }
            }

            // Find Project Leaders (matrix field)
            $projectLeaders = [];
            $relResult = $this->deltekDb->prepare('SELECT * FROM project_leaders WHERE project_num = ?');
            $relResult->execute([ $row['project_num'] ]);
            $relRows = $relResult->fetchAll();
            $i = 0;
            foreach($relRows as $relRow) {
                $i++;
                $person = Entry::find()->section('people')->where([
                    'content.field_personEmployeeNumber' => $relRow['employee_num'],
                ])->one();
                if ($person) {
                    $projectLeaders['new'.$i] = [
                        'type' => 'projectLeader',
                        'fields' => [
                            'aeiPerson'   => [$person->id],
                            'leaderTitle' => $relRow['project_role'],
                        ]
                    ];
                }
            }

            // Find Project Partners (matrix field)
            $projectPartners = [];
            $relResult = $this->deltekDb->prepare('SELECT * FROM project_partners WHERE project_num = ?');
            $relResult->execute([ $row['project_num'] ]);
            $relRows = $relResult->fetchAll();
            $i = 0;
            foreach($relRows as $relRow) {
                $i++;
                $projectPartners['new'.$i] = [
                    'type' => 'projectPartner',
                    'fields' => [
                        'partnerName' => $relRow['partner'],
                        'partnerRole' => $relRow['role'],
                    ]
                ];
            }
            $fields = [
                'projectNumber'     => $row['project_num'],
                'projectName'       => $row['name'],
                'projectClientName' => $row['client'],
                'projectTagline'    => $row['tagline'],
                'projectLocation'   => $row['location'],
                'projectLeedStatus' => $row['leed_status'],
                'body'              => $body,
                'services'          => $serviceIds,
                'markets'           => $marketIds,
                'projectAwards'     => $awardIds,
                'projectLeaders'    => $projectLeaders,
                'projectPartners'   => $projectPartners,
                'colorSwatch'       => $colorSwatch,
                'featured'          => (!empty($row['is_featured']) ? 1 : 0),
                'deltekIdsImported' => implode(',', $deltekIdsImported),
                'projectImage'      => $heroImage,
            ];
            // $entry->enabled = (!isset($row['is_enabled']) || !empty($row['is_enabled']) ? 1 : 0);

            // Only add/update media blocks if adding new entry, or we're doing a refresh from Deltek
            if ($actionVerb == 'added' || $importMode == 'refresh') {
                $fields = array_merge($fields, [
                    'mediaBlocks'  => $mediaBlocks
                ]);
            }

            $entry->setFieldValues($fields);
            if(Craft::$app->getElements()->saveElement($entry)) {
                if ($actionVerb != 'added') {
                    // Also update any drafts for post
                    $drafts = Craft::$app->getEntryRevisions()->getDraftsByEntryId($entry->id);
                    foreach ($drafts as $draft) {
                        $draft->setFieldValues($fields);
                        Craft::$app->getEntryRevisions()->saveDraft($draft);
                    }
                }
                $projectsImport->saved($entry, $actionVerb, (!empty($drafts) ? count($drafts) : 0));

            } else {
                $this->bomb('<li>Save error: '.print_r($entry->getErrors(), true).'</li>');
            }
        }
        list($log, $summary) = $projectsImport->finish();
        $this->log .= $log;
        $this->summary = array_merge($summary, $this->summary);
    }

    /**
     * Init a new Entry with type attributes
     * @param  string $entryType Slug of entry type
     * @return object             New Entry object
     */
    private function makeNewEntry(string $entryType)
    {
        $entryType = EntryType::find()->where(['handle' => $entryType])->one();
        $entry = new Entry();
        $entry->sectionId = $entryType->getAttribute('sectionId');
        $entry->typeId = $entryType->getAttribute('id');
        $entry->authorId = 1;
        return $entry;
    }

    /**
     * Find People Matrix for Impact post
     * @param  string $impactKey Deltek ID of Impact post
     * @return array              People IDs
     */
    private function getImpactPeopleMatrix(string $impactKey)
    {
        $impactPeople = [];
        $i = 0;
        $relResult = $this->deltekDb->prepare('SELECT * FROM impact_authorship WHERE impact_key = ?');
        $relResult->execute([ $impactKey ]);
        $relRows = $relResult->fetchAll();
        foreach($relRows as $relRow) {
            $i++;
            if (!empty($relRow['employee_num'])) {
                $person = Entry::find()->section('people')->where([
                    'content.field_personEmployeeNumber' => $relRow['employee_num']
                ])->one();
                $aeiPerson = ($person) ? [$person->id] : [];
            } else {
                $aeiPerson = [];
            }
            // Make sure we found an AEI person or have a name/company
            if (!empty($aeiPerson) || !empty($relRow['author_name']) || !empty($relRow['author_company'])) {
                $impactPeople['new'.$i] = [
                    'type' => 'person',
                    'fields' => [
                        'aeiPerson'     => $aeiPerson,
                        'personName'    => $relRow['author_name'],
                        'personCompany' => $relRow['author_company'],
                        'personRole'    => $relRow['role'],
                    ]
                ];
            }
        }
        return $impactPeople;
    }

    /**
     * Find Impact Type
     * @param  string $impactType title of impact type
     * @return array              Impact Type IDs
     */
    private function getImpactType(string $impactType)
    {
        $category = $this->getCategory('impactTypes', $impactType);
        return ($category) ? [$category->id] : [];
    }

    private function getPhoto($filename)
    {
        if (empty($filename)) {
            return [];
        }
        $filename = basename(trim($filename));
        $filename = preg_replace('/(png|tif|jpg|psd)$/i','jpg', $filename);
        $image = Asset::find()->where([
            'filename' => $filename,
        ])->one();
        return $image ? [$image->id] : [];
    }

    /**
     * Get Related Photos for deltek object
     * @param  string $photosTable     lookup table for images
     * @param  string $deltekIdField   deltek_id field
     * @param  string $deltekId        deltek id
     * @param  int $i                  current counter for matrix fields
     * @return array
     */
    private function getRelatedPhotos($photosTable, $deltekLookupField, $deltekId, $i, $deltekIdField, $deltekIdsImported)
    {
        $heroImage = [];
        $relatedImages = [];
        $inSql = '';
        $params = [ $deltekId ];
        if (count($deltekIdsImported) > 0) {
            $in  = str_repeat('?,', count($deltekIdsImported) - 1) . '?';
            $inSql = ' AND `'.$deltekIdField.'` NOT IN ('.$in.')';
            $params = array_merge($params, $deltekIdsImported);
        }
        $relResult = $this->deltekDb->prepare('SELECT * FROM `'.$photosTable.'` WHERE `'.$deltekLookupField.'` = ?'.$inSql);
        $relResult->execute($params);
        $relRows = $relResult->fetchAll();
        foreach($relRows as $relRow) {
            $filename = basename(trim($relRow['photo_url']));
            $filename = preg_replace('/(png|tif|jpg|psd)$/i','jpg', $filename);
            $image = Asset::find()->where([
                'filename' => $filename,
            ])->one();
            if ($image) {
                $caption = trim(str_replace('&nbsp;', ' ', $relRow['caption']), ' "”“');
                // Is this the hero image? If so, set for return
                if ($relRow['is_hero']==1) {
                    $heroImage = [$image->id];
                } else {
                    // Otherwise add image to matrix fields to return
                    $i++;
                    $relatedImages['new'.$i] = [
                        'type' => 'image',
                        'fields' => [
                            'caption'  => $caption,
                            'width'    => (!empty($relRow['full_width']) ? 'full' : 'half'),
                            'image'    => [$image->id],
                            'photoKey' => $relRow['photo_key'],
                        ]
                    ];
                    // Append to array of deltek ids
                    $deltekIdsImported[] = $relRow[$deltekIdField];
                }
            }
        }

        // Return array of images found + updated array of $deltekIdsImported
        return [$heroImage, $relatedImages, $deltekIdsImported];
    }

    /**
     * Get Related Quotes for deltek object
     * @param  string $quotesTable     lookup table for quotes
     * @param  string $deltekIdField  deltek_id field
     * @param  string $deltekId        deltek id
     * @return array
     */
    private function getRelatedQuotes($quotesTable, $deltekLookupField, $deltekId, $deltekIdField, $deltekIdsImported)
    {
        // Get our "quotes" Super Table field (inside "mediaBlocks" matrix field)
        if (empty($this->superTableQuotesField)) {
            $mediaBlockField = Craft::$app->getFields()->getFieldByHandle('mediaBlocks');
            $blockTypes = Craft::$app->getMatrix()->getBlockTypesByFieldId($mediaBlockField->id);
            foreach($blockTypes as $blockType) {
                if ($blockType->handle=='quotes') {
                    $matrixFields = Craft::$app->fields->getFieldsByLayoutId($blockType->fieldLayoutId);
                    // Cache this return for future use
                    $this->superTableQuotesField = SuperTable::$plugin->service->getBlockTypesByFieldId($matrixFields[0]->id);
                }
            }
        }
        // For some reason we couldn't find the Super Table field
        if (empty($this->superTableQuotesField)) {
            Craft::warning('Could not find Super Table field for quotes in mediaBlocks!');
            return [];
        }

        $blockType = $this->superTableQuotesField[0]; // There will only ever be one SuperTable_BlockType

        // Find related quotes
        $relatedQuotes = [];
        $inSql = '';
        $params = [ $deltekId ];
        if (count($deltekIdsImported) > 0) {
            $in  = str_repeat('?,', count($deltekIdsImported) - 1) . '?';
            $inSql = ' AND `'.$deltekIdField.'` NOT IN ('.$in.')';
            $params = array_merge($params, $deltekIdsImported);
        }
        $relResult = $this->deltekDb->prepare('SELECT * FROM `'.$quotesTable.'` WHERE `'.$deltekLookupField.'` = ?'.$inSql);
        $relResult->execute($params);
        $relRows = $relResult->fetchAll();
        foreach($relRows as $relRow) {
            if (!empty($relRow['employee_num'])) {
                $person = Entry::find()->section('people')->where([
                    'content.field_personEmployeeNumber' => $relRow['employee_num']
                ])->one();
                $aeiPerson = ($person) ? [$person->id] : [];
            } else {
                $aeiPerson = [];
            }

            $personName = '';
            // There are different field names for author in impact_quotes and related_quotes (gak)
            if (!empty($relRow['author'])) {
                $personName = $relRow['author'];
            } else if (!empty($relRow['quote_author'])) {
                $personName = $relRow['quote_author'];
            }

            // Make comma-delimited string of company + title
            $companyTitle = implode(',', array_filter([$relRow['author_company'], $relRow['author_title']]));

            // Clean up quote
            $quote = trim(str_replace('&nbsp;', ' ', $relRow['quote']), ' "”“');

            $relatedQuotes[] = ['new1' => [
                'type' => $blockType->id,
                'fields' => [
                    'quote'         => $quote,
                    'personName'    => $personName,
                    'personCompany' => $companyTitle,
                    'quoteKey'      => $relRow['quote_key'],
                    'aeiPerson'     => $aeiPerson,
                ]
            ]];
            // Append to array of deltek ids
            $deltekIdsImported[] = $relRow[$deltekIdField];
        }

        // Return array of quotes found + updated array of $deltekIdsImported
        return [$relatedQuotes, $deltekIdsImported];
    }

    /**
     * Get category of entry
     * @param string               $categoryGroupHandle category group handle
     * @param string               $categoryTitle        category title
     */
    private function getCategory(string $categoryGroupHandle, string $categoryTitle)
    {
        if (empty($categoryTitle) || empty($categoryGroupHandle)) return;
        // Populate category cache array for category handle if not set
        if (empty($this->categoriesCache[$categoryGroupHandle])) {
            $this->categoriesCache[$categoryGroupHandle] = [];
        }
        // Check if category is cached
        if (!empty($this->categoriesCache[$categoryGroupHandle][$categoryTitle])) {
            return $this->categoriesCache[$categoryGroupHandle][$categoryTitle];
        }
        $categoryGroup = Craft::$app->getCategories()->getGroupByHandle($categoryGroupHandle);
        $category = Category::find()->where([
            'title' => $categoryTitle,
            'groupId' => $categoryGroup->id,
        ])->one();
        // Cache category for subsequent lookups
        $this->categoriesCache[$categoryGroupHandle][$categoryTitle] = $category;
        return $category;
    }

    /**
     * Error was triggered, email dev and log warning
     * @param  string $message info about the error
     */
    private function bomb(string $message) {
        Craft::warning($message);
        if (!Craft::$app->getConfig()->general->devMode) {
            $this->sendMail($message, 'AEI bomb', 'nate@firebellydesign.com');
        }
        throw new \Exception($message);
    }

    /**
     * Send an email
     * @param string $message
     * @param string $subject
     * @param string $toEmail
     * @return bool
     */
    private function sendMail(string $message, string $subject, string $toEmail): bool
    {
        $settings = Craft::$app->getSystemSettings()->getSettings('email');
        $message = new Message();
        $message->setFrom([$settings['fromEmail'] => $settings['fromName']]);
        $message->setTo($toEmail);
        $message->setSubject($subject);
        $message->setHtmlBody($message);
        return Craft::$app->getMailer()->send($message);
    }

    /**
     * Ensure valid URL by adding http:// to avoid validation errors on URL fields
     * @param $url
     * @return string
     */
    private function validUrl($url)
    {
        if (empty($url)) return $url;
        else return parse_url($url, PHP_URL_SCHEME) === null ? 'http://' . $url : $url;
    }

    /**
     * Add some p tags if text is not formatted
     * @param $text
     * @return string
     */
    private function formatText($text)
    {
        if (strip_tags($text) == $text) {
            $text = preg_replace('#(\r\n?|\n){2,}#', "\n", $text);
            $text = '<p>' . implode('</p><p>', array_filter(explode("\n", $text))) . '</p>';
        }
        return $text;
    }

    /**
     * Fix stat figures for db save
     */
    private function fixStatFigure($figure) {
        // Stats with just 0 don't save in db, get nulled out
        $figure = preg_replace('/^0$/', 'zero', $figure);
        return $figure;
    }

    /**
     * Clean up older Deltek logs, removing anything 30 days or older
     */
    private function cleanUpLogs() {
        DeltekLog::deleteAll(['<', 'dateCreated', new Expression('DATE_SUB(NOW(), INTERVAL 30 DAY)')]);
    }

    /**
     * @param UploadedFile $uploadedFile
     * @param int $folderId
     * @return Asset
     * @throws BadRequestHttpException
     * @throws UploadFailedException
     */
    protected static function uploadNewAsset(UploadedFile $uploadedFile, $folderId) {
        if (empty($folderId)) {
            throw new BadRequestHttpException('No target destination provided for uploading');
        }

        if ($uploadedFile === null) {
            throw new BadRequestHttpException('No file was uploaded');
        }

        $assets = Craft::$app->getAssets();

        if ($uploadedFile->getHasError()) {
            throw new UploadFailedException($uploadedFile->error);
        }

        // Move the uploaded file to the temp folder
        if (($tempPath = $uploadedFile->saveAsTempFile()) === false) {
            throw new UploadFailedException(UPLOAD_ERR_CANT_WRITE);
        }

        if (empty($folderId)) {
            throw new BadRequestHttpException('The target destination provided for uploading is not valid');
        }

        $folder = $assets->findFolder(['id' => $folderId]);

        if (!$folder) {
            throw new BadRequestHttpException('The target folder provided for uploading is not valid');
        }

        // Check the permissions to upload in the resolved folder.
        $filename = Assets::prepareAssetName($uploadedFile->name);

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = $filename;
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $folder->volumeId;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        $result = Craft::$app->getElements()->saveElement($asset);

        return $asset;
    }
}
