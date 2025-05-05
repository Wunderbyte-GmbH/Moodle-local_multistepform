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

namespace local_multistepform\external;

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_multistepform\manager;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

/**
 * Submit data to the server.
 * @package local_multistepform
 * @category external
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2025 Wunderbyte GmbH
 */
class load_step extends external_api {
    /**
     * Define the parameters for the function.
     *
     * @return [type]
     *
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'uniqueid' => new external_value(PARAM_RAW, 'uniqueid'),
                'recordid' => new external_value(PARAM_INT, 'recordid'),
                'step' => new external_value(PARAM_INT, 'step'),
            ]
        );
    }

    /**
     * Execute the function.
     *
     * @param mixed $uniqueid
     * @param mixed $step
     *
     * @return [type]
     *
     */
    public static function execute($uniqueid, $recordid, $step) {
        global $DB, $PAGE;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'uniqueid' => $uniqueid,
            'recordid' => $recordid,
            'step' => $step,
        ]);

        $context = context_system::instance();
        $PAGE->set_context($context);

        $manager = manager::return_class_by_uniqueid($uniqueid, $recordid);

        $data = $manager->get_step($step);

        return [
            'step' => $data['step'],
            'formclass' => $data['formclass'],
            'data' => json_encode($data),
            'template' => $data['template'] ?? $manager->get_template(),
            'returnurl' => $data['returnurl'] ?? '',
        ];
    }

    /**
     * Define the return structure for the function.
     *
     * @return [type]
     *
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'step' => new external_value(PARAM_INT, 'Step ID'),
                'formclass' => new external_value(PARAM_TEXT, 'Formclass status'),
                'data' => new external_value(PARAM_RAW, 'Json encoded data'),
                'template' => new external_value(PARAM_RAW, 'template'),
                'returnurl' => new external_value(PARAM_URL, 'returnurl', VALUE_OPTIONAL, ''),
            ]
        );
    }
}
