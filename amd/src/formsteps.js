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

export const init = (uniqueid, recordid, initialstep, formclass, data) => {

    // eslint-disable-next-line no-console
    console.log(data);

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
                loadStep(uniqueid, recordid, currentstep);
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
    const container = multiformcontainer.querySelector(SELECTORS.FORMCONTAINER);
    const topwrapper = multiformcontainer.closest('.multiform-container-topwrapper');
    container.classList.remove('fade-in');
    void container.offsetWidth;
    container.classList.add('fade-out');

    const onFadeOutEnd = () => {
        container.removeEventListener('transitionend', onFadeOutEnd);
        setTimeout(() => {
            if (step > 0) {
            dynamicForm = null;
            }
        }, 300);// Reset dynamicForm to ensure a fresh start
        // Step 3: Load next step content
        Ajax.call([{
            methodname: 'local_multistepform_load_step',
            args: { uniqueid, recordid, step },
            done: response => {
                if (response.returnurl?.length > 0) {
                    window.location.href = response.returnurl;
                    return;
                }

                Templates.renderForPromise(response.template, JSON.parse(response.data)).then(({ html, js }) => {
                    // Prepare new content
                    const tempWrapper = document.createElement('div');
                    tempWrapper.innerHTML = html;

                    Templates.runTemplateJS(js, tempWrapper);

                    const newFormContainer = tempWrapper.querySelector(SELECTORS.FORMCONTAINER);
                    const formclass = tempWrapper.querySelector('[data-formclass]')?.getAttribute('data-formclass');

                    if (formclass && newFormContainer) {
                        initializeForm(newFormContainer, formclass, []);
                    }

                    // Step 4: Replace the content
                    topwrapper.innerHTML = '';
                    topwrapper.appendChild(tempWrapper.firstElementChild);

                    // Step 5: Trigger fade-in
                    requestAnimationFrame(() => {
                        newFormContainer.classList.remove('fade-out');
                        newFormContainer.classList.add('fade-in');
                    });

                }).catch(notification.exception);
            },
            fail: notification.exception
        }]);
    };

    // Attach the fade-out end listener
    container.addEventListener('transitionend', onFadeOutEnd, { once: true });
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

    if (!dynamicForm && uniqueid.length > 0) {

        dynamicForm = new DynamicForm(
            container,
            formclass,
            data,
        );

        if (dynamicForm) {
            dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, () => {

                loadStep(uniqueid, recordid, currentstep);
            });

            dynamicForm.addEventListener(dynamicForm.events.SERVER_VALIDATION_ERROR, () => {

                // When we tried to go to the previous page, even when the validation fails, we load the step.
                if ((currentstep + 1) == previousstep) {
                    loadStep(uniqueid, recordid, currentstep);
                } else {
                    currentstep = previousstep;
                }
            });
        }

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
// Wait for DOM to update after Moodle repeats the form elements
    window.requestAnimationFrame(() => {
        // eslint-disable-next-line no-console
        console.log('make sure all autocomplete elements are here');
        setTimeout(() => {
            // eslint-disable-next-line no-console
            console.log('load them now');
            require(['core/form-autocomplete'], (AutoComplete) => {
                document.querySelectorAll('div[data-fieldtype="autocomplete"]').forEach((select) => {
                    const alreadyEnhanced = select.querySelector('.form-autocomplete-downarrow');

                    if (!alreadyEnhanced && AutoComplete.enhance) {
                        AutoComplete.enhance(select.querySelector('select'));
                    }
                });
            });
            const packageSelect = document.querySelector('#id_message_package');
            if (packageSelect) {
                packageSelect.addEventListener('change', (e) => {
                const selectedPackageId = e.target.value;
                e.stopImmediatePropagation();

                // Deselect all selected messageids.
                const messageSelect = document.querySelector('#id_messageids');
                if (messageSelect) {
                    Array.from(messageSelect.options).forEach(option => {
                        option.selected = false;
                    });
                }

                if (dynamicForm) {
                    dynamicForm.submitFormAjax({
                        packageid: selectedPackageId
                    }).then(() => {
                        // The form will be reloaded by SERVER_VALIDATION_ERROR below.
                        return;
                    }).catch(err => {
                        // eslint-disable-next-line no-console
                        console.error(err);
                    });
                }
                });
            }
        }, 400);
    });
}