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
 * Step 1 form definition.
 *
 * @package   local_multistepform
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_multistepform\form;

use core_form\dynamic_form;
use local_multistepform\manager;

/**
 * Demo step 1 form.
 */
class step extends dynamic_form {
    /**
     * Definition.
     *
     * @return void
     *
     */
    protected function definition(): void {
        $mform = $this->_form;
        $formdata = $this->_ajaxformdata ?? $this->_customdata ?? [];

        $manager = new manager();
        $manager->definition($mform, $formdata);
    }

    /**
     * Process the form submission.
     *
     * @return void
     *
     */
    public function process_dynamic_submission(): void {
        $data = $this->get_data();
        $manager = new manager();
        $manager->process_dynamic_submission($data);
    }

    /**
     * Set data for the form.
     *
     * @return void
     *
     */
    public function set_data_for_dynamic_submission(): void {
        $data = $this->_ajaxformdata ?? $this->_customdata ?? [];
        if ($data) {
            $this->set_data($data);
        }
    }

    /**
     * Get the URL for the page.
     *
     * @return \moodle_url
     *
     */
    protected function get_page_url(): \moodle_url {
        return new \moodle_url('/local/multistepform/testpage.php');
    }

    /**
     * Get the URL for the page.
     *
     * @return \moodle_url
     *
     */
    public function get_page_url_for_dynamic_submission(): \moodle_url {
        return $this->get_page_url();
    }

    /**
     * Get the context for the page.
     *
     * @return \context
     *
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Check access for the page.
     *
     * @return void
     *
     */
    protected function check_access_for_dynamic_submission(): void {
        require_login();
    }

    /**
     * Each step can provide a specific way how to extract and return the data.
     * @param array $step
     * @return array
     */
    public function get_data_to_persist(array $step): array {
        return $step;
    }
}
