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
    private $categories_cache = [];
    private $deltek_ids = [];
    private $superTableQuotesField = null;

    /**
     * Run Deltek Import
     *
     * AEI::$plugin->deltekImport->importRecords()
     *
     * @return string
     */
    public function importRecords($sections_to_import, $deltek_ids='')
    {
        if (empty($sections_to_import)) {
            return (object) [
                'log' => 'Nothing done.',
                'summary' => 'No sections selected to import.',
            ];
        }

        // Optional IDs to specify which entries to import
        if (!empty($deltek_ids)) {
            $this->deltek_ids = array_map('trim', explode(',', $deltek_ids));
        }

        // Connect to Deltek db
        try {
            $this->deltekDb = new \PDO('mysql:host='.getenv('DELTEK_DB_SERVER').';dbname='.getenv('DELTEK_DB_DATABASE').';charset=utf8', getenv('DELTEK_DB_USER'), getenv('DELTEK_DB_PASSWORD'));
            $this->deltekDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch(\PDOException $e) {
            $this->bomb('PDO Error: ' . $e->getMessage());
        }

        // Import all sections specified in $sections_to_import array
        try {
            if (in_array('offices', $sections_to_import)) {
                $this->importOffices();
            }
            if (in_array('people', $sections_to_import)) {
                $this->importPeople();
            }
            if (in_array('awards', $sections_to_import)) {
                $this->importAwards();
            }
            if (in_array('impact', $sections_to_import)) {
                $this->importImpact();
            }
            if (in_array('projects', $sections_to_import)) {
                $this->importProjects();
            }
        } catch (\Exception $e) {
            $this->bomb('Import Error: ' . $e->getMessage());
        }

        // Store import summary + log in aei_deltek_log table
        $deltekLog = new DeltekLog();
        $deltekLog->log = $this->log;
        $deltekLog->summary = $this->summary;
        $deltekLog->save();

        return (object) [
            'log'     => $this->log,
            'summary' => implode(', ', $this->summary),
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
            if (!empty($this->deltek_ids) && !in_array($row['office_name'], $this->deltek_ids)) continue;

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
            $field = Craft::$app->fields->getFieldByHandle('quotes');
            $blockTypes = SuperTable::$plugin->service->getBlockTypesByFieldId($field->id);
            $blockType = $blockTypes[0];

            // Find Office Quotes
            $office_quotes = [];
            $i = 0;

            $rel_result = $this->deltekDb->prepare('SELECT * FROM office_quotes WHERE office_name = ?');
            $rel_result->execute([ $row['office_name'] ]);
            $rel_rows = $rel_result->fetchAll();
            foreach($rel_rows as $rel_row) {
                $i++;
                if (!empty($row['employee_num'])) {
                    $person = Entry::find()->section('people')->where([
                        'content.field_personEmployeeNumber' => $row['employee_num']
                    ])->one();
                    $aeiPerson = ($person) ? [$person->id] : [];
                } else {
                    $aeiPerson = [];
                }
                $office_quotes['new'.$i] = [
                    'type' => $blockType->id,
                    'fields' => [
                        'quote'         => $rel_row['quote'],
                        'personName'    => '', // no override field in office_quotes table for this
                        'personCompany' => $rel_row['employee_title'],
                        'quoteKey'      => $rel_row['quote_key'],
                        'aeiPerson'     => $aeiPerson,
                    ]
                ];
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
                'officeMapUrl'     => $this->validUrl($row['map_url']),
                'quotes'           => $office_quotes,
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
            if (!empty($this->deltek_ids) && !in_array($row['employee_num'], $this->deltek_ids)) continue;

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
            $office_ids = $office ? [$office->id] : [];

            // Find Person Quote
            $person_quote = '';
            $rel_result = $this->deltekDb->prepare('SELECT * FROM employee_quotes WHERE employee_num = ?');
            $rel_result->execute([ $row['employee_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            foreach($rel_rows as $rel_row) {
                // Remove quotes around text
                $person_quote = trim(str_replace('&nbsp;', ' ', $rel_row['quote']), ' "”“');
            }

            // Find People Type IDs
            $person_type_ids = [];
            foreach (explode(',', $row['primary_category']) as $category_title) {
                if ($category = $this->getCategory('peopleTypes', trim($category_title))) {
                    $person_type_ids[] = $category->id;
                }
            }

            // Find Images
            $filename = basename($row['photo_url']);
            $image = Asset::find()->where([
                'filename' => $filename,
            ])->one();
            $image_ids = $image ? [$image->id] : [];

            // Find Secondary People Type IDs
            $secondary_person_type_ids = [];
            foreach (explode(',', $row['primary_category']) as $category_title) {
                if ($category = $this->getCategory('peopleTypes', trim($category_title))) {
                    $secondary_person_type_ids[] = $category->id;
                }
            }

            // Populate Social Links (matrix field), currently just Linkedin
            if (!empty($row['linkedin'])) {
                $social_links = [
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
                'office'               => $office_ids,
                'personType'           => $person_type_ids,
                'secondaryPersonType'  => $secondary_person_type_ids,
                'socialLinks'          => $social_links,
                'personQuote'          => $person_quote,
                'personImage'          => $image_ids,
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
            if (!empty($this->deltek_ids) && !in_array($row['award_key'], $this->deltek_ids)) continue;

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
    private function importImpact() {
        $impactImport = new SectionImport('Impact');

        $result = $this->deltekDb->query("SELECT * FROM impacts");
        foreach($result as $row) {
            // Filter by delted_ids passed in?
            if (!empty($this->deltek_ids) && !in_array($row['impact_key'], $this->deltek_ids)) continue;

            $actionVerb = 'updated';
            $entry = Entry::find()->section('impact')->where([
                'content.field_impactKey' => $row['impact_key']
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('impact');
                $entry->title = $row['title'];
                $actionVerb = 'added';
            }

            // Add matrix fields (stats, quotes, images)
            $media_blocks = [];
            $i = 0;

            // Find impact images
            list($hero_image, $related_images) = $this->getRelatedPhotos('impact_photos', 'impact_key', $row['impact_key'], $i);
            // Update matrix field index
            $i = $i + count($related_images);
            // Merge in any images found to matrix
            $media_blocks = array_merge($related_images, $media_blocks);

            $related_quotes = $this->getRelatedQuotes('impact_quotes', 'impact_key', $row['impact_key']);
            if (!empty($related_quotes)) {
                $i++;
                $media_blocks = array_merge(['new'.$i => [
                    'type' => 'quotes',
                    'fields' => [
                        'quotes' => $related_quotes,
                    ]
                ]], $media_blocks);
            }

            // Find Market IDs
            $market_ids = [];
            $markets = implode(',', array_filter([
                $row['primary_market'],
                $row['secondary_market'],
                $row['tertiary_market'],
            ]));
            foreach (explode(',', $markets) as $category_title) {
                $category = $this->getCategory('markets', trim($category_title));
                if ($category) {
                    $market_ids[] = $category->id;
                }
            }

            // todo: refactor code from importProjects to share quotes
            // todo: pull impact_projects

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
                'body'                 => $this->formatText($row['body']),
                // 'excerpt'           => $row['excerpt'], // This isn't currently being sent
                'sessionDate'          => $sessionDate,
                'conferenceUrl'        => $conferenceUrl,
                'conferenceHost'       => $conferenceHost,
                'conferenceLocation'   => $row['location'],
                'impactPublication'    => $impactPublication,
                'impactPublicationUrl' => $impactPublicationUrl,
                'markets'              => $market_ids,
                'impactType'           => $this->getImpactType($row['category']),
                'impactPeople'         => $this->getImpactPeopleMatrix($row['impact_key']),
                'impactKey'            => $row['impact_key'],
                'impactImage'          => $hero_image,
                'mediaBlocks'          => $media_blocks,
                'featured'             => (!empty($row['is_featured']) ? 1 : 0),
            ];
            $entry->setFieldValues($fields);
            $entry->postDate = new \DateTime($row['date']);
            $entry->enabled = (!isset($row['is_enabled']) || !empty($row['is_enabled']) ? 1 : 0);

            if(Craft::$app->getElements()->saveElement($entry)) {
                $impactImport->saved($entry, $actionVerb);
                // Set postDate after save if new post (can't set on first save)
                if ($actionVerb == 'added') {
                    $entry->postDate = new \DateTime($row['date']);
                    Craft::$app->getElements()->saveElement($entry);
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
    private function importProjects() {
        $projectsImport = new SectionImport('Projects');
        $result = $this->deltekDb->query('SELECT * FROM projects');
        foreach($result as $row) {
            // Filter by delted_ids passed in?
            if (!empty($this->deltek_ids) && !in_array($row['project_num'], $this->deltek_ids)) continue;

            $entry = Entry::find()->section('projects')->where([
                'content.field_projectNumber' => $row['project_num'],
            ])->one();

            $actionVerb = 'updated';
            if (!$entry) {
                $entry = $this->makeNewEntry('projects');
                $actionVerb = 'added';
            }

            /////////////////////////////////////
            // Add matrix fields (stats, quotes, images)
            $media_blocks = [];
            $i = 0;

            $project_quotes = $this->getRelatedQuotes('project_quotes', 'project_num', $row['project_num']);
            if (!empty($project_quotes)) {
                $i++;
                $media_blocks = array_merge(['new'.$i => [
                    'type' => 'quotes',
                    'fields' => [
                        'quotes' => $project_quotes,
                    ]
                ]], $media_blocks);
            }

            // Project Stats
            $project_stats = [];
            $rel_result = $this->deltekDb->prepare('SELECT * FROM project_stats WHERE project_num = ?');
            $rel_result->execute([ $row['project_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            foreach($rel_rows as $rel_row) {
                $i++;
                $project_stats['new'.$i] = [
                    'type' => 'stat',
                    'fields' => [
                        'statFigure' => $rel_row['text'],
                        'statLabel'  => $rel_row['subtext'],
                    ]
                ];
            }
            $media_blocks = array_merge($project_stats, $media_blocks);

            // Find project images
            list($hero_image, $related_images) = $this->getRelatedPhotos('project_photos', 'project_num', $row['project_num'], $i);
            // Update matrix field index
            $i = $i + count($related_images);
            // Merge in any images found to matrix
            $media_blocks = array_merge($related_images, $media_blocks);

            // Find Service IDs
            $service_ids = [];
            foreach (explode(',', $row['services']) as $category_title) {
                $category = $this->getCategory('services', trim($category_title));
                if ($category) {
                    $service_ids[] = $category->id;
                }
            }

            // Find Market IDs
            $market_ids = [];
            $markets = implode(',', array_filter([
                $row['primary_market'],
                $row['secondary_market'],
                $row['tertiary_market'],
            ]));
            foreach (explode(',', $markets) as $category_title) {
                $category = $this->getCategory('markets', trim($category_title));
                if ($category) {
                    $market_ids[] = $category->id;
                }
            }

            // Find Project Awards
            $award_ids = [];
            $rel_result = $this->deltekDb->prepare("SELECT * FROM project_awards WHERE project_num = ?");
            $rel_result->execute([ $row['project_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            foreach($rel_rows as $rel_row) {
                // See if this award is imported already
                $award = Entry::find()->section('awards')->where([
                    'content.field_awardKey' => $rel_row['award_key'],
                ])->one();
                if ($award) {
                    $award_ids[] = $award->id;
                }
            }

            // Find Project Leaders (matrix field)
            $project_leaders = [];
            $rel_result = $this->deltekDb->prepare('SELECT * FROM project_leaders WHERE project_num = ?');
            $rel_result->execute([ $row['project_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            $i = 0;
            foreach($rel_rows as $rel_row) {
                $i++;
                $person = Entry::find()->section('people')->where([
                    'content.field_personEmployeeNumber' => $rel_row['employee_num'],
                ])->one();
                if ($person) {
                    $project_leaders['new'.$i] = [
                        'type' => 'projectLeader',
                        'fields' => [
                            'aeiPerson'   => [$person->id],
                            'leaderTitle' => $rel_row['project_role'],
                        ]
                    ];
                }
            }

            // Find Project Partners (matrix field)
            $project_partners = [];
            $rel_result = $this->deltekDb->prepare('SELECT * FROM project_partners WHERE project_num = ?');
            $rel_result->execute([ $row['project_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            $i = 0;
            foreach($rel_rows as $rel_row) {
                $i++;
                $project_partners['new'.$i] = [
                    'type' => 'projectPartner',
                    'fields' => [
                        'partnerName' => $rel_row['partner'],
                        'partnerRole' => $rel_row['role'],
                    ]
                ];
            }

            $entry->setFieldValues([
                'projectNumber'     => $row['project_num'],
                'projectName'       => $row['name'],
                'projectClientName' => $row['client'],
                'projectTagline'    => $row['tagline'],
                'projectLocation'   => $row['location'],
                'projectLeedStatus' => $row['leed_status'],
                'body'              => $this->formatText($row['case_study']),
                'services'          => $service_ids,
                'markets'           => $market_ids,
                'projectAwards'     => $award_ids,
                'projectLeaders'    => $project_leaders,
                'projectPartners'   => $project_partners,
                'projectImage'      => $hero_image,
                'mediaBlocks'       => $media_blocks,
                'featured'          => (!empty($row['is_featured']) ? 1 : 0),
            ]);
            $entry->enabled = (!isset($row['is_enabled']) || !empty($row['is_enabled']) ? 1 : 0);

            if(Craft::$app->getElements()->saveElement($entry)) {
                $projectsImport->saved($entry, $actionVerb);
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
     * @param  string $entry_type Slug of entry type
     * @return object             New Entry object
     */
    private function makeNewEntry(string $entry_type)
    {
        $entryType = EntryType::find()->where(['handle' => $entry_type])->one();
        $entry = new Entry();
        $entry->sectionId = $entryType->getAttribute('sectionId');
        $entry->typeId = $entryType->getAttribute('id');
        $entry->authorId = 1;
        return $entry;
    }

    /**
     * Find People Matrix for Impact post
     * @param  string $impact_key Deltek ID of Impact post
     * @return array              People IDs
     */
    private function getImpactPeopleMatrix(string $impact_key)
    {
        $impact_people = [];
        $i = 0;
        $rel_result = $this->deltekDb->prepare('SELECT * FROM impact_authorship WHERE impact_key = ?');
        $rel_result->execute([ $impact_key ]);
        $rel_rows = $rel_result->fetchAll();
        foreach($rel_rows as $rel_row) {
            $i++;
            if (!empty($rel_row['employee_num'])) {
                $person = Entry::find()->section('people')->where([
                    'content.field_personEmployeeNumber' => $rel_row['employee_num']
                ])->one();
                $aeiPerson = ($person) ? [$person->id] : [];
            } else {
                $aeiPerson = [];
            }
            // Make sure we found an AEI person or have a name/company
            if (!empty($aeiPerson) || !empty($rel_row['author_name']) || !empty($rel_row['author_company'])) {
                $impact_people['new'.$i] = [
                    'type' => 'person',
                    'fields' => [
                        'aeiPerson'     => $aeiPerson,
                        'personName'    => $rel_row['author_name'],
                        'personCompany' => $rel_row['author_company'],
                        'personRole'    => $rel_row['role'],
                    ]
                ];
            }
        }
        return $impact_people;
    }

    /**
     * Find Impact Type
     * @param  string $impact_type title of impact type
     * @return array              Impact Type IDs
     */
    private function getImpactType(string $impact_type)
    {
        $category = $this->getCategory('impactTypes', $impact_type);
        return ($category) ? [$category->id] : [];
    }

    /**
     * Get Related Photos for deltek object
     * @param  string $photos_table     lookup table for images
     * @param  string $deltek_id_field  deltek_id field
     * @param  string $deltek_id        deltek id
     * @param  int $i                   current counter for matrix fields
     * @return array
     */
    private function getRelatedPhotos($photos_table, $deltek_id_field, $deltek_id, $i)
    {
        $hero_image = [];
        $related_images = [];
        $rel_result = $this->deltekDb->prepare('SELECT * FROM `'.$photos_table.'` WHERE `'.$deltek_id_field.'` = ?');
        $rel_result->execute([ $deltek_id ]);
        $rel_rows = $rel_result->fetchAll();
        foreach($rel_rows as $rel_row) {
            $filename = basename($rel_row['photo_url']);
            $filename = preg_replace('/(png|tif|jpg)$/i','jpg', $filename);
            $image = Asset::find()->where([
                'filename' => $filename,
            ])->one();
            if ($image) {
                $caption = trim(str_replace('&nbsp;', ' ', $rel_row['caption']), ' "”“');
                // Is this the hero image? If so, set for return
                if ($rel_row['is_hero']==1) {
                    $hero_image = [$image->id];
                } else {
                    // Otherwise add image to matrix fields to return
                    $i++;
                    $related_images['new'.$i] = [
                        'type' => 'image',
                        'fields' => [
                            'caption' => $caption,
                            'width'   => (!empty($rel_row['full_width']) ? 'full' : 'half'),
                            'image'   => [$image->id],
                        ]
                    ];
                }
            }
        }

        // Return arrays of images found
        return [$hero_image, $related_images];
    }

    /**
     * Get Related Quotes for deltek object
     * @param  string $quotes_table     lookup table for quotes
     * @param  string $deltek_id_field  deltek_id field
     * @param  string $deltek_id        deltek id
     * @return array
     */
    private function getRelatedQuotes($quotes_table, $deltek_id_field, $deltek_id)
    {
        // Get our "quotes" Super Table field (inside "mediaBlocks" matrix field)
        if (empty($this->superTableQuotesField)) {
            $mediaBlockField = Craft::$app->fields->getFieldByHandle('mediaBlocks');
            $blockTypes = Craft::$app->matrix->getBlockTypesByFieldId($mediaBlockField->id);
            foreach($blockTypes as $blockType) {
                if ($blockType->handle=='quotes') {
                    $matrixFields = Craft::$app->fields->getFieldsByLayoutId($blockType->fieldLayoutId);
                    // Cache this return for future use
                    $this->superTableQuotesField = SuperTable::$plugin->service->getBlockTypesByFieldId($matrixFields[0]->id);
                }
            }
        }
        // For some reaons we couldn't find the super table block
        if (empty($this->superTableQuotesField)) {
            Craft::warning('Could not find Super Table field for quotes in mediaBlocks!');
            return [];
        }

        $blockType = $this->superTableQuotesField[0]; // There will only ever be one SuperTable_BlockType

        // Find related quotes
        $related_quotes = [];
        $rel_result = $this->deltekDb->prepare('SELECT * FROM `'.$quotes_table.'` WHERE `'.$deltek_id_field.'` = ?');
        $rel_result->execute([ $deltek_id ]);
        $rel_rows = $rel_result->fetchAll();
        $q = 0;
        foreach($rel_rows as $rel_row) {
            $q++;
            if (!empty($rel_row['employee_num'])) {
                $person = Entry::find()->section('people')->where([
                    'content.field_personEmployeeNumber' => $rel_row['employee_num']
                ])->one();
                $aeiPerson = ($person) ? [$person->id] : [];
            } else {
                $aeiPerson = [];
            }

            $personName = '';
            // There are different field names for author in impact_quotes and related_quotes (gak)
            if (!empty($rel_row['author'])) {
                $personName = $rel_row['author'];
            } else if (!empty($rel_row['quote_author'])) {
                $personName = $rel_row['quote_author'];
            }
            // Reverse lastName, firstName formatting (commenting this out when I saw "Quinn Evans Architects, AIA Washington Award in Architecture" in the db)
            // $personName = preg_replace('/^([^,]*), (.*)/', '$2 $1', $personName);

            // Make comma-delimited string of company + title
            $companyTitle = implode(',', array_filter([$rel_row['author_company'], $rel_row['author_title']]));

            // Clean up quote
            $quote = trim(str_replace('&nbsp;', ' ', $rel_row['quote']), ' "”“');

            $related_quotes['new'.$q] = [
                'type' => $blockType->id,
                'fields' => [
                    'quote'         => $quote,
                    'personName'    => $personName,
                    'personCompany' => $companyTitle,
                    'quoteKey'      => $rel_row['quote_key'],
                    'aeiPerson'     => $aeiPerson,
                ]
            ];
        }

        // Return array of quotes found
        return $related_quotes;
    }

    /**
     * Get category of entry
     * @param string               $category_group_handle category group handle
     * @param string               $category_title        category title
     */
    private function getCategory(string $category_group_handle, string $category_title)
    {
        if (empty($category_title) || empty($category_group_handle)) return;
        // Populate category cache array for category handle if not set
        if (empty($this->categories_cache[$category_group_handle])) {
            $this->categories_cache[$category_group_handle] = [];
        }
        // Check if category is cached
        if (!empty($this->categories_cache[$category_group_handle][$category_title])) {
            return $this->categories_cache[$category_group_handle][$category_title];
        }
        $category_group = Craft::$app->categories->getGroupByHandle($category_group_handle);
        $category = Category::find()->where([
            'title' => $category_title,
            'groupId' => $category_group->id,
        ])->one();
        // Cache category for subsequent lookups
        $this->categories_cache[$category_group_handle][$category_title] = $category;
        return $category;
    }

    /**
     * Error was triggered, email dev and log warning
     * @param  string $message info about the error
     */
    function bomb(string $message) {
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
     * @param string $to_email
     * @return bool
     */
    private function sendMail(string $message, string $subject, string $to_email): bool
    {
        $settings = Craft::$app->systemSettings->getSettings('email');
        $message = new Message();
        $message->setFrom([$settings['fromEmail'] => $settings['fromName']]);
        $message->setTo($to_email);
        $message->setSubject($subject);
        $message->setHtmlBody($message);
        return Craft::$app->mailer->send($message);
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
