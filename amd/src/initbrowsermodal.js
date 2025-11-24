import ModalFactory from 'core/modal_factory';
import ModalEvents from "core/modal_events";
import {exception} from 'core/notification';
import BrowserComponent from './browsercomponent';

export const init = () => {
    document.body.addEventListener('click', (e) => {
        if (e.target.closest('[name="openedflexbrowser"]')) {
            e.preventDefault();
            const url = new URL(window.location);

            const coursedata = {
                course: url.searchParams.get('course'),
                section: url.searchParams.get('section')
            };

            return initActivityModal(coursedata);
        }

        if (e.target.closest('[data-modname^="mod_edflex"]')) {
            const el = e.target.closest('[data-modname^="mod_edflex"]');
            const href = el && el.querySelector('[data-action="add-chooser-option"]').getAttribute('href');

            if (!href) {
                return false;
            }

            e.preventDefault();

            const url = new URL(href);
            const coursedata = {
                course: url.searchParams.get('id'),
                section: url.searchParams.get('section')
            };

            return initActivityModal(coursedata);
        }
    });

    /**
     * Initializes and displays the activity modal with the provided course data.
     *
     * @param {Object} coursedata - Data related to the course to be loaded into the modal.
     * @return {Promise} A promise that resolves when the modal is successfully shown or rejects on error.
     */
    function initActivityModal(coursedata) {
        ModalFactory.create({
            type: ModalFactory.types.DEFAULT,
            title: M.util.get_string('edflexbrowsertitle', 'mod_edflex'),
            body: `<div class="edflex-browser-wrapper">
                        <div class="my-3 py-5 text-center"><div class="spinner-border" role="status"></div></div>
                   </div>`,
            footer: '<div></div>',
            large: true
        }).then((modal) => {
            modal.show();
            modal.getRoot()
                .on(ModalEvents.hidden, () => {
                    modal.destroy();
                })
            ;

            new BrowserComponent({
                element: modal.getRoot()[0].querySelector('.edflex-browser-wrapper'),
                coursedata
            });

            return true;
        }).catch(exception);
    }
};
