/**
 * JS for handling AJAX-based form step loading.
 *
 * @module local_multistepform/formsteps
 * @copyright 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// import Ajax from 'core/ajax';
import * as notification from 'core/notification';
import DynamicForm from 'core_form/dynamicform';
import Ajax from 'core/ajax';
import Templates from 'core/templates';

const SELECTORS = {
    FORMCONTAINER: '[data-region=multistepformcontainer]',
    MULTISTEPFORMCONTAINER: 'div.multistep-form-wrapper',
};

var dynamicForms = {};
var currentstep = 1;
var previousstep = 0;

export const init = (uniqueid, recordid, initialstep, formclass, data) => {

    const multiformcontainer = document.querySelector(
        SELECTORS.MULTISTEPFORMCONTAINER + '[data-uniqueid="' + uniqueid + '"]');
    if (!multiformcontainer) {
        return;
    }

    const formdata = data ? JSON.parse(data) : [];

    currentstep = initialstep;

    multiformcontainer.querySelectorAll('[data-step]').forEach(button => {
        button.addEventListener('click', e => {
            const direction = e.currentTarget.getAttribute('data-step');

            const oldstep = currentstep; // Save current before changing

            switch (direction) {
                case 'next':
                    currentstep++;
                    break;
                case 'previous':
                    // If we're on a review step or invalid step, go back to last known valid
                    currentstep = currentstep < 0 ? previousstep : currentstep - 1;
                    break;
                case 'submit':
                    currentstep = -1;
                    break;
                default:
                    currentstep = -2;
                    break;
            }

            previousstep = oldstep;
            const form = dynamicForms[oldstep];
            if (form) {
                form.submitFormAjax().then(() => {
                    return true;
                }).catch(e => {
                    notification.exception(e);
                });
            } else {
                loadStep(uniqueid, recordid, currentstep);
            }
        });
    });
    const container = multiformcontainer.querySelector(SELECTORS.FORMCONTAINER);

    initializeForm(container, formclass, formdata);
};

/**
 * Load a step of the form via AJAX.
 *
 * @param {mixed} uniqueid
 * @param {mixed} recordid
 * @param {mixed} step
 *
 * @return void *
 */
function loadStep(uniqueid, recordid, step) {
    const multiformcontainer = document.querySelector(
        SELECTORS.MULTISTEPFORMCONTAINER + '[data-uniqueid="' + uniqueid + '"]');
    if (!multiformcontainer) {
        return;
    }
    const container = multiformcontainer.querySelector(SELECTORS.FORMCONTAINER) || multiformcontainer;
    container.classList.remove('fade-in');
    void container.offsetWidth;
    container.classList.add('fade-out');
    Ajax.call([{
        methodname: 'local_multistepform_load_step',
        args: {
            uniqueid: uniqueid,
            recordid: recordid,
            step: step,
        },
        done: (response) => {

            if (response.returnurl.length > 0) {
                window.location.href = response.returnurl;
                return;
            }

            Templates.renderForPromise(response.template, JSON.parse(response.data)).then(({html, js}) => {
                // We add the footer js to the html.
                html = html + response.js;
                Templates.replaceNode(SELECTORS.MULTISTEPFORMCONTAINER + '[data-uniqueid="' + uniqueid + '"]', html, js);
                return true;
            }).catch(e => {
                // eslint-disable-next-line no-console
                console.log(e);
            });
        },
        fail: (error) => {
            notification.exception(error);
        },
    }]);
}

/**
 * Initialize the form with a template and data.
 *
 * @param {mixed} container
 * @param {mixed} formclass
 * @param {array} data
 *
 * @return [type]
 *
 */
function initializeForm(container, formclass, data = []) {

    if (container) {
            container.parentElement.addEventListener('click', e => {

            const target = e.target;
            if (
                target.tagName === 'INPUT' &&
                target.type === 'submit' &&
                (
                    target.name.startsWith('target_add') ||
                    target.name.startsWith('deleteelement')
                )
            ) {
               loadAutocompleteElements();
            }
        });
    }

    const uniqueid = container?.closest(SELECTORS.MULTISTEPFORMCONTAINER)?.getAttribute('data-uniqueid') ?? '';
    const recordid = container?.closest(SELECTORS.MULTISTEPFORMCONTAINER)?.getAttribute('data-recordid') ?? '';

    const alreadyInitialized = dynamicForms[currentstep]
        && container.dataset.initializedStep == currentstep;

    if (!alreadyInitialized && uniqueid.length > 0) {
        dynamicForms[currentstep] = new DynamicForm(container, formclass, data);
        container.dataset.initializedStep = currentstep;

        const dynamicForm = dynamicForms[currentstep];

        dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, () => {
            loadStep(uniqueid, recordid, currentstep);
        });

        dynamicForm.addEventListener(dynamicForm.events.SERVER_VALIDATION_ERROR, () => {
            if ((currentstep + 1) === previousstep) {
                loadStep(uniqueid, recordid, currentstep);
            } else {
                currentstep = previousstep;
            }
        });

        loadAutocompleteElements();
    }
}

/**
 * Make sure all autocomplete elements are loaded.
 *
 * @return void
 *
 */
function loadAutocompleteElements() {
    require(['core/form-autocomplete'], (AutoComplete) => {
        // Enhance all uninitialized autocomplete fields
        document.querySelectorAll('div[data-fieldtype="autocomplete"]').forEach((select) => {
            const alreadyEnhanced = select.querySelector('.form-autocomplete-downarrow');

            if (!alreadyEnhanced && AutoComplete.enhance) {
                const dropdown = select.querySelector('select');
                if (dropdown) {
                    AutoComplete.enhance(dropdown);
                }
            }
        });
    });

    // Set up listener only once for package change
    const packageSelect = document.querySelector('#id_message_package');
    if (packageSelect && !packageSelect.dataset.listenerAttached) {
        packageSelect.dataset.listenerAttached = 'true'; // Prevent duplicate listeners
        packageSelect.addEventListener('change', (e) => {
            const selectedPackageId = e.target.value;
            e.stopImmediatePropagation();

            // Deselect all messageids
            const messageSelect = document.querySelector('#id_messageids');
            if (messageSelect) {
                Array.from(messageSelect.options).forEach(option => {
                    option.selected = false;
                });
            }

            if (dynamicForms[currentstep]) {
                dynamicForms[currentstep].submitFormAjax({
                    packageid: selectedPackageId
                }).then(() => {
                    // Form will reload through validation handler
                }).catch(err => {
                    notification.exception(err);
                });
            }
        });
    }
}
