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
use moodleform;
use MoodleQuickForm;
use stdClass;

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
     * Constructor for the manager class.
     *
     * @param array $steps
     * @param int|null $recordid
     * @param bool $canmovesteps
     * @param bool $hasreview
     *
     */
    public function __construct(string $uniqueid, array $steps, ?int $recordid = null, bool $canmovesteps = false, bool $hasreview = false) {
        $this->uniqueid = $uniqueid;
        $this->steps = $steps;
        $this->recordid = $recordid;
        $this->canmovesteps = $canmovesteps;
        $this->hasreview = $hasreview;

        $cache = cache::make('local_multistepform', 'multistepform');
        $cachedata = $cache->get('multistepform_' . $this->uniqueid);

        // If the cache is empty, we need to create it.
        // If the cache is not empty, we need to load the data from the cache.

        // Now add the data from the cache to the steps array.
        if ($cachedata) {
            foreach ($cachedata as $stepkey => $step) {
                foreach ($step as $key => $value) {
                    $this->steps[$stepkey]['formdata'][$key] = $value;
                }
            }
        }



        $data = [
            'uniqueid' => $this->uniqueid,
            'steps' => $this->steps,
            'recordid' => $this->recordid,
            'canmovesteps' => $this->canmovesteps,
        ];

        set_user_preferences([
            'multistepform_' . $this->uniqueid => json_encode($data),
        ]);
    }

    /**
     * Definition.
     *
     * @return void
     *
     */
    public static function definition(MoodleQuickForm $mform, array $data): void {

        $uniqueid = $data['uniqueid'];
        $step = $data['step'];
        $stepidentifier = $data['stepidentifier'] ?? '';

        $mform->addElement('hidden', 'uniqueid', $uniqueid);
        $mform->setType('uniqueid', PARAM_TEXT);
        $mform->addElement('hidden', 'step', $step);
        $mform->setType('step', PARAM_INT);
        $mform->addElement('hidden', 'stepidentifier', $stepidentifier);
        $mform->setType('stepidentifier', PARAM_TEXT);
    }

    /**
     * Process the form submission.
     *
     * @param \stdClass $data
     * @return void
     *
     */
    public static function process_dynamic_submission($data): void {
        global $DB;

        $uniqueid = $data->uniqueid;
        $step = $data->step;

        // Load the manager instance.
        $manager = self::return_class_by_uniqueid($uniqueid);
        if ($manager) {
            // Save the step data.
            $manager->save_step_data($step, $data);
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
     *
     * @return null|self
     *
     */
    public static function return_class_by_uniqueid(string $uniqueid) {
        global $DB;

        $msdata = get_user_preferences('multistepform_' . $uniqueid);
        if ($msdata) {
            $msdata = json_decode($msdata, true);
            $manager = new self($uniqueid, $msdata['steps'], $msdata['recordid'], $msdata['canmovesteps']);
            return $manager;
        } else {
            return null;
        };
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
     *
     * @param string $step
     * @param stdClass $stepdata
     *
     * @return void
     *
     */
    public function save_step_data(string $step, stdClass $stepdata): void {

        // First, we save each step in the cache.
        $cache = cache::make('local_multistepform', 'multistepform');
        $data = $cache->get('multistepform_' . $this->uniqueid);

        if (
            !$data
            || empty($data)
        ) {
            $data = [];
        }
        $data[$step] = $stepdata;

        $cache->set('multistepform_' . $this->uniqueid, $data);

        // Now we need to check if this is the last step.
        if ($step == count($this->steps)) {
            // If so, we peramanently save the data.
            $this->persist();
        }
    }

    /**
     * Load the data from the database.
     *
     * @return void
     *
     */
    protected function load_data(): void {
        global $DB;
        $record = $DB->get_record('local_multistepform_data', ['id' => $this->recordid], '*', MUST_EXIST);
        $this->data = json_decode($record->datajson, true);
    }

    /**
     * Persist the data to the database.
     * This method needs to be overriden in the child class to save the data the way it's needed.
     *
     * @return void
     *
     */
    protected function persist(): void {
    }

    /**
     * returns the data.
     *
     * @return array
     *
     */
    public function get_data(): array {
        return $this->data;
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
            $step != -1
            && !isset($this->steps[$step])
        ) {
            throw new \moodle_exception("Invalid step: {$step}");
        }

        if ($step == -1 && !empty($this->hasreview)) {
            $formdata = [
                'step' => -1,
                'template' => $this->template,
                'uniqueid' => $this->uniqueid,
                'confirmation' => true,
            ];
        } else {
            $formdata = $this->steps[$step]['formdata'] ?? [];
            $formclass = $this->steps[$step]['formclass'];
            $formdata['step'] = $step;
            $formdata['disableprevious'] = $step == 1 ? true : false;
            $formdata['disablenext'] = $step == count($this->steps) ? true : false;
            $formdata['formclass'] = str_replace('\\', '\\\\', $formclass);
            $formdata['totalsteps'] = count($this->steps);

            $formdata['uniqueid'] = $this->uniqueid;

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
}
