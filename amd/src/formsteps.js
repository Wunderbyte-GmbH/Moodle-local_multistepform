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

    // eslint-disable-next-line no-console
    console.log('start with step', uniqueid, initialstep, formclass, data);

    // eslint-disable-next-line no-console
    console.log('selector', SELECTORS.MULTISTEPFORMCONTAINER + '[data-uniqueid="' + uniqueid + '"]');

    const multiformcontainer = document.querySelector(
        SELECTORS.MULTISTEPFORMCONTAINER + '[data-uniqueid="' + uniqueid + '"]');
    if (!multiformcontainer) {
        // eslint-disable-next-line no-console
        console.log('multiformcontainer not found');
        return;
    }

    const formdata = JSON.parse(data);

    currentstep = initialstep;

    multiformcontainer.querySelectorAll('[data-step]').forEach(button => {
        button.addEventListener('click', e => {

            const direction = e.currentTarget.getAttribute('data-step');

            previousstep = currentstep;
            switch (direction) {
                case 'next':
                    currentstep++;
                    break;
                case 'previous':
                    currentstep--;
                    break;
                default:
                    currentstep = -1;
                    break;
            }
            // eslint-disable-next-line no-console
            console.log('next step', currentstep);

            dynamicForm.submitFormAjax().then(() => {
                // eslint-disable-next-line no-console
                console.log('form submitted');
                return true;
            }).catch(e => {
                // eslint-disable-next-line no-console
                console.log(e);
            });
        });
    });
    const container = multiformcontainer.querySelector(SELECTORS.FORMCONTAINER);

    // eslint-disable-next-line no-console
    console.log(formclass);
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
        // eslint-disable-next-line no-console
        console.log('multiformcontainer not found');
        return;
    }

    Ajax.call([{
        methodname: 'local_multistepform_load_step',
        args: {
            uniqueid: uniqueid,
            step: step,
        },
        done: (response) => {

            // eslint-disable-next-line no-console
            console.log('response', response);

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

    // eslint-disable-next-line no-console
    console.log('dynamicForm', container, formclass, data);

    const uniqueid = container.closest(SELECTORS.MULTISTEPFORMCONTAINER).getAttribute('data-uniqueid');

    if (!dynamicForm) {
        dynamicForm = new DynamicForm(
            container,
            formclass,
            data,
        );
        dynamicForm.load(data);
    }

    // eslint-disable-next-line no-console
    console.log('dynamicForm', dynamicForm);

    dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, e => {
        const response = e.detail;
        // eslint-disable-next-line no-console
        console.log(response);
        dynamicForm.container.innerHTML = '';
        dynamicForm = null;

        loadStep(uniqueid, currentstep);
    });

    dynamicForm.addEventListener(dynamicForm.events.SERVER_VALIDATION_ERROR, e => {
        const response = e.detail;
        // eslint-disable-next-line no-console
        console.log(response);
        currentstep = previousstep;
        // loadStep(uniqueid, currentstep);
    });
}