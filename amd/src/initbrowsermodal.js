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
 * Initializes and displays the activity modal with the provided course data.
 *
 * @module    mod_edflex/initbrowsermodal
 * @copyright 2025 Edflex <support@edflex.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
