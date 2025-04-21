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

var dynamicForm = null;
var currentstep = 0;
var previousstep = 0;

export const init = (uniqueid, initialstep, formclass, data) => {

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

            previousstep = currentstep > 0 ? currentstep : previousstep;
            switch (direction) {
                case 'next':
                    currentstep++;
                    break;
                case 'previous':
                    if (currentstep < 0) {
                        // If we are on the review page, we fake steps in a way that..
                        // .. we land on the last page and previous page is set to last but one.
                        currentstep = previousstep;
                    } else {
                        currentstep--;
                    }
                    break;
                case 'submit':
                    currentstep = -1;
                    break;
                default:
                    currentstep = -2;
                    break;
            }

            if (!dynamicForm) {
                loadStep(uniqueid, currentstep);
            } else {
                dynamicForm.submitFormAjax().then(() => {
                    return true;
                }).catch(e => {
                    // eslint-disable-next-line no-console
                    console.log(e);
                });
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
 * @param {mixed} step
 *
 * @return void *
 */
function loadStep(uniqueid, step) {
    const multiformcontainer = document.querySelector(
        SELECTORS.MULTISTEPFORMCONTAINER + '[data-uniqueid="' + uniqueid + '"]');
    if (!multiformcontainer) {
        return;
    }

    Ajax.call([{
        methodname: 'local_multistepform_load_step',
        args: {
            uniqueid: uniqueid,
            step: step,
        },
        done: (response) => {

            if (response.returnurl.length > 0) {
                window.location.href = response.returnurl;
                return;
            }

            Templates.renderForPromise(response.template, JSON.parse(response.data)).then(({html, js}) => {

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

    const uniqueid = container?.closest(SELECTORS.MULTISTEPFORMCONTAINER)?.getAttribute('data-uniqueid') ?? '';

    if (!dynamicForm && uniqueid.length > 0) {
        dynamicForm = new DynamicForm(
            container,
            formclass,
            data,
        );
        dynamicForm.load(data);
    }

    if (dynamicForm) {
        dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, () => {
            dynamicForm.container.innerHTML = '';
            dynamicForm = null;

            loadStep(uniqueid, currentstep);
        });

        dynamicForm.addEventListener(dynamicForm.events.SERVER_VALIDATION_ERROR, () => {

            // When we tried to go to the previous page, even when the validation fails, we load the step.
            if ((currentstep + 1) == previousstep) {
                dynamicForm.container.innerHTML = '';
                dynamicForm = null;
                loadStep(uniqueid, currentstep);
            } else {
                currentstep = previousstep;
            }
        });
    }
}