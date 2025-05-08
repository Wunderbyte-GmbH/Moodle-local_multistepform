<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class for managing multi-step forms.
 *
 * @package   local_multistepform
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_multistepform;

use cache;
use local_multistepform\local\cachestore;
use moodleform;
use MoodleQuickForm;
use ReflectionClass;
use stdClass;
use Throwable;

/**
 * Submit data to the server.
 * @package local_multistepform
 * @category external
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2025 Wunderbyte GmbH
 */
class manager {
    /**
     * Uniqueid of the manager class
     *
     * @var string $uniqueid
     */
    protected $uniqueid;
    /**
     * The steps of the form
     *
     * @var array
     */
    protected $steps = [];
    /**
     * The data for the current step
     *
     * @var array $data
     */
    protected $data = [];
    /**
     * Which recordid is currently being used
     *
     * @var int $recordid
     */
    protected $recordid;
    /**
     * Has review of data.
     *
     * @var bool $hasreview
     */
    protected $hasreview;
    /**
     * Go back and forward between steps
     *
     * @var bool $canmovesteps
     */
    protected $canmovesteps;
    /**
     * The template to render.
     *
     * @var string $template
     */
    protected $template = 'local_multistepform/multistepform';

    /**
     * Returnurl
     *
     * @var string $template
     */
    protected $returnurl = '/';

    /**
     * Constructor for the manager class.
     *
     * @param array $steps
     * @param int $recordid
     * @param bool $canmovesteps
     * @param bool $hasreview
     * @param string $returnurl
     *
     */
    public function __construct(
        string $uniqueid,
        array $steps,
        int $recordid = 0,
        bool $canmovesteps = true,
        bool $hasreview = true,
        string $returnurl = '/'
    ) {
        $this->uniqueid = $uniqueid;
        $this->steps = $steps;
        $this->recordid = $recordid;
        $this->canmovesteps = $canmovesteps;
        $this->hasreview = $hasreview;
        $this->returnurl = $returnurl;

        $cachestore = new cachestore();
        $cachedata = $cachestore->get_multiform($this->uniqueid, $this->recordid);

        if (empty($cachedata)) {
            $cachedata = [
                'uniqueid' => $this->uniqueid,
                'steps' => $this->steps,
                'recordid' => $this->recordid,
                'canmovesteps' => $this->canmovesteps,
                'hasreview' => $this->hasreview,
                'returnurl' => $this->returnurl,
                'managerclassname' => get_class($this),
            ];
            $cachestore->set_multiform($this->uniqueid, $this->recordid, $cachedata);
        } else {
            // We might have already stored values for the steps.
            $this->steps = $cachedata['steps'];
        }
    }

    /**
     * Definition.
     *
     * @return void
     *
     */
    public function definition(MoodleQuickForm $mform, array $data): void {

        $uniqueid = $data['uniqueid'];
        $step = $data['step'];
        $stepidentifier = $data['stepidentifier'] ?? '';
        $recordid = $data['recordid'] ?? '';
        $formclass = $data['formclass'] ?? '';
        $label = $data['label'] ?? '';

        $mform->addElement('hidden', 'uniqueid', $uniqueid);
        $mform->setType('uniqueid', PARAM_TEXT);
        $mform->addElement('hidden', 'recordid', $recordid);
        $mform->setType('recordid', PARAM_INT);
        $mform->addElement('hidden', 'step', $step);
        $mform->setType('step', PARAM_INT);
        $mform->addElement('hidden', 'stepidentifier', $stepidentifier);
        $mform->setType('stepidentifier', PARAM_TEXT);
        $mform->addElement('hidden', 'label', $label);
        $mform->setType('label', PARAM_TEXT);
        $mform->addElement('hidden', 'formclass', $formclass);
        $mform->setType('formclass', PARAM_RAW);
    }

    /**
     * Process the form submission.
     *
     * @param stdClass $data
     * @param MoodleQuickForm|null $mform
     * @return void
     *
     */
    public function process_dynamic_submission($data, ?MoodleQuickForm $mform = null): void {
        global $DB;

        $uniqueid = $data->uniqueid;
        $recordid = $data->recordid;
        $step = $data->step;

        // Load the manager instance.
        $manager = self::return_class_by_uniqueid($uniqueid, $recordid);
        if ($manager) {
            // Save the step data.
            $manager->save_step_data($step, $data, $mform);
        } else {
            throw new \moodle_exception('Invalid uniqueid');
        }
    }

    /**
     * Set data for the form.
     *
     * @return void
     *
     */
    public function set_data_for_dynamic_submission(): void {
    }
    /**
     * Returns the class instance by uniqueid.
     *
     * @param string $uniqueid
     * @param int $recordid
     *
     * @return null|self
     *
     */
    public static function return_class_by_uniqueid(string $uniqueid, int $recordid = 0): ?self {
        global $DB;

        $cachestore = new cachestore();
        $msdata = $cachestore->get_multiform($uniqueid, $recordid);

        // We need to instantiate the child class, if there is one.
        $classname = $msdata['managerclassname'] ?? get_class($this);
        $manager = new $classname(
            $uniqueid,
            $msdata['steps'] ?? [],
            $msdata['recordid'] ?? 0,
            $msdata['canmovesteps'] ?? true,
            $msdata['hasreview'] ?? true,
            $msdata['returnurl'] ?? '/'
        );
        return $manager;
    }

    /**
     * Get the form instance.
     *
     * @param string $step
     * @param \moodle_url $url
     *
     * @return \core_form\dynamic_form
     *
     */
    public function get_form_instance(string $step, \moodle_url $url): \core_form\dynamic_form {
        if (!isset($this->steps[$step])) {
            throw new \moodle_exception("Invalid step: {$step}");
        }

        $formclass = $this->steps[$step];
        return new $formclass($url, ['step' => $step, 'manager' => $this]);
    }

    /**
     * Save the step data.
     * This is only set in the non permanent cache.
     * Persistent saving is done in the persist method.
     *
     * @param int $step
     * @param stdClass $stepdata
     * @param MoodleQuickForm|null $mform
     *
     * @return void
     *
     */
    public function save_step_data(int $step, stdClass $stepdata, ?MoodleQuickForm $mform): void {

        // First, we save each step in the cache.

        $labels = [];
        foreach ($stepdata as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $element = $mform->getElement($key);

            $type = $element->getType();
            if ($type == 'hidden') {
                continue;
            }
            $label = $element->getLabel();

            $options = $this->get_element_options($element);

            if (!empty($options)) {
                $type = $element->getType();
                if (
                    ($type == 'autocomplete' || $type == 'select')
                    && is_array($value)
                ) {
                    // For autocomplete and select, we need to get the value from the options.
                    // We might have comma separated values.
                    $multilabels = [];
                    foreach ($value as $val) {
                        if (isset($options[$val])) {
                            $multilabels[] = $options[$val];
                        }
                    }
                    $labels[$label] = implode(', ', $multilabels);
                } else {
                    if (isset($options[$value])) {
                        // For other types, we can use the label directly.
                        $labels[$label] = $options[$value];
                    } else {
                        foreach ($options as $key => $option) {
                            if (($option['attr']['value'] ?? '') == $value) {
                                $labels[$label] = $option['text'];
                                break;
                            }
                        }
                    }
                }
            } else {
                // For other types, we can use the label directly.
                switch ($type) {
                    case 'duration':
                        $labels[$label] = $this->format_duration($value);
                        break;
                    case 'date_time_selector':
                        $labels[$label] = userdate($value);
                        break;
                    default:
                        $labels[$label] = $value;
                }
            }
        }

        $stepdata->labels = $labels;
        $cachestore = new cachestore();
        $cachestore->set_step($this->uniqueid, $this->recordid, $step, (array)$stepdata);

        // Persistent saving is done in the persist method.
    }

    /**
     * Load the data from the database.
     *
     * @return void
     *
     */
    protected function load_data(): void {
        global $DB;
    }

    /**
     * Set the return URL.
     *
     * @param string $url
     *
     * @return void
     *
     */
    public function set_returnurl(string $url): void {
        $cachestore = new cachestore();
        $cachedata = $cachestore->get_multiform($this->uniqueid, $this->recordid);
        $cachedata['returnurl'] = $url;
        $cachestore->set_multiform($this->uniqueid, $this->recordid, $cachedata);
        $this->returnurl = $url;
    }

    /**
     * Set the template.
     *
     * @param string $template
     *
     * @return void
     *
     */
    public function set_template(string $template): void {
        $cachestore = new cachestore();
        $cachedata = $cachestore->get_multiform($this->uniqueid, $this->recordid);
        $cachedata['template'] = $template;
        $cachestore->set_multiform($this->uniqueid, $this->recordid, $cachedata);
        $this->returnurl = $template;
    }

    /**
     * Set the hasreview.
     *
     * @param string $template
     *
     * @return void
     *
     */
    public function set_hasreview(bool $hasreview): void {
        $cachestore = new cachestore();
        $cachedata = $cachestore->get_multiform($this->uniqueid, $this->recordid);
        $cachedata['hasreview'] = $hasreview;
        $cachestore->set_multiform($this->uniqueid, $this->recordid, $cachedata);
        $this->returnurl = $hasreview;
    }

    /**
     * Persist the data to the database.
     * This method needs to be overriden in the child class to save the data the way it's needed.
     *
     * @return void
     *
     */
    public function persist(): void {
    }

    /**
     * returns the data.
     *
     * @return array
     *
     */
    public function get_data(): array {
        return $this->steps;
    }

    /**
     * Returns the record ID of the current step.
     *
     * @return int|null
     *
     */
    public function get_recordid(): ?int {
        return $this->recordid;
    }

    /**
     * Returns the template.
     *
     * @return string
     *
     */
    public function get_template(): string {
        return $this->template;
    }

    /**
     * Returns the information if the user can move between steps.
     *
     * @return bool
     *
     */
    public function can_move_between_steps(): bool {
        return $this->canmovesteps;
    }

    /**
     * Returns the array for the webservice.
     *
     * @param int $step
     *
     * @return array
     *
     */
    public function get_step(int $step) {

        if (
            $step > 0
            && !isset($this->steps[$step])
        ) {
            throw new \moodle_exception("Invalid step: {$step}");
        }
        if ($step < 0) {
            $formdata = [
                'step' => $step,
                'recordid' => $this->get_recordid(),
                'template' => $this->template,
                'uniqueid' => $this->uniqueid,
                'confirmation' => false,
            ];
            if (!empty($this->hasreview) && $step == -1) {
                $formdata['confirmation'] = true;
                foreach ($this->steps as $stepkey => $step) {
                    foreach ($step['labels'] as $key => $value) {
                        $formdata['fields'][] = ['label' => $key, 'value' => $value];
                    }
                }
            } else {
                // This is the moment when we have acutally submitted the Data.
                // First we persist the saved data.
                $this->persist();

                // Then we purge the cache.
                $cachestore = new cachestore();
                $cachestore->purge_cache($this->uniqueid, $this->recordid);
                // Finally, we return the returnurl.
                $formdata['returnurl'] = $this->returnurl ?? '';
            }
        } else {
            // If we are on the first step and the data is not yet loaded, we call the load_data method.
            // We want the instantiated class here.
            if (
                $step == 1
                && !empty($this->steps[$step]['recordid'])
            ) {
                // At this point, we have the instantiated class, we can now load the data.
                $this->load_data();
            }

            $formdata = $this->steps[$step] ?? [];
            $formclass = $this->steps[$step]['formclass'];
            $formdata['step'] = $step;

            $currentstep = (int) $formdata['step']; // Assuming current step is stored here.
            $formdata['steps'] = array_values(array_map(
                function ($step, $index) use ($currentstep) {
                    $stepnumber = (int) ($index); // Fallback to 1-based index.
                    return [
                        'number' => $stepnumber,
                        'label' => $this->steps[$index]['label'] ?? 'formlabel',
                        'iscurrent' => $stepnumber === $currentstep,
                        'iscompleted' => $stepnumber < $currentstep,
                    ];
                },
                $this->steps,
                array_keys($this->steps)
            ));

            $formdata['disableprevious'] = ($step == 1 || !$this->can_move_between_steps()) ? true : false;
            $formdata['disablenext'] = $step == count($this->steps) ? true : false;
            $formdata['formclass'] = str_replace('\\', '\\\\', $formclass);
            $formdata['totalsteps'] = count($this->steps);

            $formdata['uniqueid'] = $this->uniqueid;
            $formdata['recordid'] = $this->recordid;

            $formdata['formdata'] = json_encode($formdata);

            $form = new $formclass(null, null, 'post', '', [], true, $formdata);
            // Set the form data with the same method that is called when loaded from JS.
            // It should correctly set the data for the supplied arguments.
            $form->set_data_for_dynamic_submission();

            $formdata['formhtml'] = $form->render();
        }

        return $formdata;
    }

    /**
     * Renders the first or current step of the form.
     *
     * @param int $step
     *
     * @return void
     *
     */
    public function render($step = 1) {
        global $OUTPUT;

        $data = $this->get_step($step);

        echo $OUTPUT->render_from_template(
            $this->template,
            $data
        );
    }

    /**
     * Get value => label options for a MoodleQuickForm element.
     *
     * @param mixed $element
     * @return array|null Array of options (value => label), or null if not applicable.
     */
    private function get_element_options($element) {

        $type = $element->getType();

        switch ($type) {
            case 'select':
                return $element->_options;
            case 'autocomplete':
                // Use reflection to access protected _options property.
                $ref = new ReflectionClass($element);
                if ($ref->hasProperty('_options')) {
                    $prop = $ref->getProperty('_options');
                    $prop->setAccessible(true);
                    $values = $prop->getValue($element);
                    $options = [];
                    foreach ($values as $key => $value) {
                        if (is_array($value)) {
                            // If the value is an array, use the first element as the label.
                            $key = reset($value['attr']);
                            $options[$key] = $value['text'];
                        }
                    }
                    return $options;
                }
                return null;

            case 'advcheckbox':
                // Special case: usually a single checkbox with specific values.
                $onval  = $element->getAttribute('value');
                $label  = $element->getLabel();
                return [$onval => $label];

            case 'checkbox':
                // Similar to advcheckbox, but more basic.
                $label = $element->getLabel();
                return ['1' => $label];

            default:
                return null;
        }
    }

    /**
     * Format the duration in a human-readable way.
     *
     * @param mixed $seconds
     *
     * @return [type]
     *
     */
    private function format_duration($seconds) {
        if ($seconds <= 0) {
            return '0 seconds';
        }

        $units = [
            'week'   => WEEKSECS, // 604800
            'day'    => DAYSECS, // 86400
            'hour'   => HOURSECS, // 3600
            'minute' => MINSECS, // 60
            'seconds' => 1,
        ];

        $parts = [];

        foreach ($units as $name => $unit) {
            $count = floor($seconds / $unit);
            if ($count > 0) {
                $parts[] = $count . ' ' . get_string($name);
                $seconds -= $count * $unit;
            }
        }

        return implode(', ', $parts);
    }
}
