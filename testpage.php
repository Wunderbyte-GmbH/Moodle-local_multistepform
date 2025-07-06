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
 * Page for testing the multi-step form.
 *
 * @package   local_multistepform
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_multistepform\manager;

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/multistepform/testpage.php'));
$PAGE->set_title(get_string('pluginname', 'local_multistepform'));
$PAGE->set_heading(get_string('pluginname', 'local_multistepform'));

echo $OUTPUT->header();

$data = [
    1 => [
        'label' => get_string('step', 'local_multistepform', 1),
        'formclass' => 'local_multistepform\\\\form\\\\step1',
        'stepidentifier' => 'firststep',
        'formdata' => [
            'id' => 1,
        ],
    ],
    2 => [
        'label' => get_string('step', 'local_multistepform', 2),
        'formclass' => 'local_multistepform\\\\form\\\\step2',
        'stepidentifier' => 'secondstep',
        'formdata' => [
            'id' => 1,
        ],
    ],
    3 => [
            'label' => get_string('step', 'local_multistepform', 3),
            'formclass' => 'local_multistepform\\\\form\\\\step3',
            'stepidentifier' => 'thirdstep',
            'formdata' => [
                'step' => 1,
            ],
    ],
];

$uniqueid = 'multistepform_testpage';
$formmanager = new manager($uniqueid, $data, 0, true, true, '/local/multistepform/testpage.php');
$formmanager->render();

echo $OUTPUT->footer();
