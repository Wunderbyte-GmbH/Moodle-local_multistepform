# Moodle Plugin: Local Multi-step Form

**Plugin Name:** `local_multistepform`
**Version:** 1.0
**Author:** Wunderbyte GmbH
**License:** [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html)

## Overview

`local_multistepform` is a Moodle plugin designed to simplify the creation and management of **multi-step forms**. It provides a flexible system for defining sequential form steps with data persistence, user navigation (back and forth), and optional review before final submission.

This plugin can be used for various workflows such as surveys, user profile updates, or any process requiring staged data entry.

---

## Features

- Define dynamic, multi-step forms with custom step labels and classes.
- Navigate between steps with data retention.
- Review collected data before final submission.
- Caches form data for consistent user experience.
- Easily extensible with custom form classes per step.
- Works with Moodle’s `dynamic_form` API and `MoodleQuickForm`.

## How It Works

Example: local/multistepform/testpage.php
A test page demonstrates how to define a 3-step form with associated form classes.

## Integrate in your own project
- Extend the manager class with your own manager class within your own plugin.
- The only function you need to overwrite ist persist(). This is to save the data of all included forms in your own table. The testpage does not save the data anywhere persistently.
- To actually make use of the plugin, you need to use the constructure of your manager class. Pass on the steps in a form like this:
````
$data = [
    1 => [
        'label' => get_string('step', 'local_multistepform', 1),
        'formclass' => 'local_multistepform\\form\\step1',
        'stepidentifier' => 'firststep',
        'formdata' => [
            'id' => 1,
        ],
    ],
    2 => [
        'label' => get_string('step', 'local_multistepform', 2),
        'formclass' => 'local_multistepform\\form\\step2',
        'stepidentifier' => 'secondstep',
        'formdata' => [
            'id' => 1,
        ],
    ],
]
````
Create your own form classes (extending dynamic_form) for each step. You can add any number of steps. Just make sure that you add the following lines to the standard functions:

```
protected function definition(): void {
        $mform = $this->_form;
        $formdata = $this->_ajaxformdata ?? $this->_customdata ?? [];
        manager::definition($mform, $formdata);

        // You can add any form elements here below.
}

public function process_dynamic_submission(): void {
        $data = $this->get_data();
        $mform = $this->_form;
        manager::process_dynamic_submission($data, $mform);

        // You should not add anything here.
        // Do the saving of your data in the persist function of the manager class.
}

public function set_data_for_dynamic_submission(): void {

        // This is needed so data is set correctly.
        $data = $this->_ajaxformdata ?? $this->_customdata ?? [];

        // You can add more data to be set here.


        if ($data) {
            $this->set_data($data);
        }
}

```
