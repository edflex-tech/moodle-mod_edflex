import {BaseComponent} from 'core/reactive';
import {exception} from 'core/notification';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Autocomplete from "core/form-autocomplete";

/**
 * Browser component
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default class BrowserComponent extends BaseComponent {
    showselectedcontents = false;
    coursedata = {};
    contentscache = {};
    selectedcontents = [];
    searchresult = [];

    constructor(data) {
        super(data);
        this.coursedata = data.coursedata || {};
    }

    create() {
        const self = this;

        self.selectors = {
            PAGE: '[data-page]',
            RESULTS: '.edflex-results',
            RESULTS_LOADING: '.edflex-loading-results',
            RESULTS_EMPTY: '.edflex-no-results',
            RESULTS_ERROR: '.edflex-errors',
            RESULTS_LIST: '.edflex-results-list',
            RESULTS_ACTIONS: '.edflex-results-actions',
            RESULT_CHECKBOX: '.edflex-id',
            IMPORT_BUTTON: '.edflex-import-activities',
            SEARCH_BUTTON: '#edflexsearchactivities',
            SHOW_SEARCH_RESULT_BUTTON: '.show-search-result',
            SHOW_SELECTED_CONTENTS_BUTTON: '.show-selected-contents',
            SELECTED_CONTENTS_LIST: '.selected-contents',

            // Filters
            QUERY: '#edflex_query',
            TYPE: '#edflex_content_type',
            LANGUAGE: '#edflex_content_language',
            CATEGORY: '#edflex_content_category',
        };


        self.loadBrowserContent()
            .then(() => {
                self.initElements();
                self.initEventListeners();

                self.elements.searchButton.disabled = true; // Disable search button by default
                self.elements.importButton.classList.add('d-none'); // Hide import button by default

                const modalElement = self.element.closest(".modal-dialog");

                if (modalElement) {
                    const modalFooter = modalElement.querySelector('.modal-footer');
                    modalFooter.appendChild(self.elements.actions);
                }

                self.element
                    .querySelectorAll('.select-multiple')
                    .forEach((sel) => {
                        Autocomplete.enhance(sel, false, false, '', false, true, undefined, true);

                        sel.addEventListener('change', () => {
                            const wrapper = sel.closest('.form-autocomplete, div.position-relative, div[id^="yui_"]');
                            const input = wrapper?.querySelector('input.form-control[data-fieldtype="autocomplete"]');
                            if (input) {
                                input.value = '';
                                input.dispatchEvent(new Event('input', {bubbles: true}));
                            }
                        });
                    })
                ;

                return true;
            })
            .catch(exception)
        ;
    }

    initElements() {
        const self = this;

        self.elements = {
            loading: self.element.querySelector(self.selectors.RESULTS_LOADING),
            empty: self.element.querySelector(self.selectors.RESULTS_EMPTY),
            errors: self.element.querySelector(self.selectors.RESULTS_ERROR),
            list: self.element.querySelector(self.selectors.RESULTS_LIST),
            selectedList: self.element.querySelector(self.selectors.SELECTED_CONTENTS_LIST),
            actions: self.element.querySelector(self.selectors.RESULTS_ACTIONS),
            query: self.element.querySelector(self.selectors.QUERY),
            type: self.element.querySelector(self.selectors.TYPE),
            language: self.element.querySelector(self.selectors.LANGUAGE),
            category: self.element.querySelector(self.selectors.CATEGORY),
            searchButton: self.element.querySelector(self.selectors.SEARCH_BUTTON),
            importButton: self.element.querySelector(self.selectors.IMPORT_BUTTON),
            showSearchResultButton: self.element.querySelector(self.selectors.SHOW_SEARCH_RESULT_BUTTON),
            showSelectedContentsButton: self.element.querySelector(self.selectors.SHOW_SELECTED_CONTENTS_BUTTON),
        };
    }

    initEventListeners() {
        const self = this;

        // Listen for changes in filter inputs
        const filterElements = [
            self.elements.query,
            self.elements.type,
            self.elements.language,
            self.elements.category,
        ];

        filterElements.forEach(element => {
            let events = ['change'];

            if (element.type === 'text') {
                events.push('input');
            }

            events.forEach((eventname) => {
                element.addEventListener(eventname, () => {
                    self.elements.searchButton.disabled = self.filtersAreEmpty();
                });
            });
        });

        self.elements.query.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                self.search();
            }
        });

        self.element.addEventListener('change', (e) => {
            if (e.target.matches(self.selectors.RESULT_CHECKBOX)) {
                const checkbox = e.target;
                if (checkbox.checked && checkbox.classList.contains('confirm')) {
                    const ok = confirm(M.util.get_string('confirmsameactivitymultipletimesinthecourse', 'mod_edflex'));

                    if (!ok) {
                        checkbox.checked = false;
                    }
                }

                const contentid = checkbox.value;

                if (checkbox.checked) {
                    self.selectContent(contentid);
                } else {
                    self.unselectContent(contentid);
                }
            }
        });

        self.element.addEventListener('click', (e) => {
            if (e.target.matches(self.selectors.PAGE)) {
                const page = e.target.getAttribute('data-page');
                self.search(page);
            }
        });

        self.elements.searchButton.addEventListener('click', e => {
            e.preventDefault();
            self.search();
        });

        self.elements.showSelectedContentsButton.addEventListener('click', e => {
            e.preventDefault();
            self.showselectedcontents = true;

            self.resetDisplay();
            self.showSelectedContents();
            self.refreshActionButtons();
        });

        self.elements.showSearchResultButton.addEventListener('click', e => {
            e.preventDefault();
            self.showselectedcontents = false;

            self.resetDisplay();
            self.showSearchResult();
            self.refreshActionButtons();
        });

        self.elements.importButton.addEventListener('click', (e) => {
            e.preventDefault();
            const ids = self.selectedcontents.map(item => item.edflexid);

            if (!ids.length) {
                return true;
            }

            const args = {
                edflexid: ids,
                course: self.coursedata.course,
                section: self.coursedata.section
            };

            const xhr = Ajax.call([{
                methodname: 'mod_edflex_import_activity',
                args: args
            }])[0];

            return xhr.then(({activities, course}) => {
                let redirecturl;

                if (activities && activities.length === 1) {
                    redirecturl = activities[0].url;
                }

                if (!redirecturl) {
                    redirecturl = course.url;
                }

                window.location.assign(redirecturl);

                return true;
            }).catch((error) => {
                self.showError(error.message || error);
            });
        });
    }

    refreshActionButtons() {
        const self = this;
        const {importButton, showSearchResultButton, showSelectedContentsButton} = self.elements;

        if (self.selectedcontents.length) {
            importButton.classList.remove('d-none');
        } else {
            importButton.classList.add('d-none');
        }

        showSelectedContentsButton.classList.add('d-none');
        showSearchResultButton.classList.add('d-none');

        if (self.showselectedcontents) {
            showSearchResultButton.classList.remove('d-none');
        } else if (self.selectedcontents.length) {
            showSelectedContentsButton.classList.remove('d-none');
        }
    }

    getFilters() {
        const self = this;

        return {
            query: (self.elements.query?.value || '').trim(),
            type: Array.from(self.elements.type.selectedOptions).map(opt => opt.value).join(','),
            language: Array.from(self.elements.language.selectedOptions).map(opt => opt.value).join(','),
            category: Array.from(self.elements.category.selectedOptions).map(opt => opt.value).join(','),
        };
    }

    filtersAreEmpty() {
        const self = this;
        const filters = self.getFilters();

        for (const filter in filters) {
            if (filters[filter]) {
                return false;
            }
        }

        return true;
    }

    search(page = 1) {
        const self = this;
        const filters = self.getFilters();
        const course = self.coursedata.course;
        const {empty} = self.elements;

        self.resetDisplay();

        const promise = Ajax.call([{
            methodname: 'mod_edflex_browser_search',
            args: {filters, course, page},
        }])[0].then((result) => {
            self.searchresult = result;
            self.showSearchResult();
            self.elements.searchButton.disabled = true;

            return result;
        }).catch((error) => {
            empty.classList.remove('d-none');
            self.showError(error.message || error);
        });

        self.showLoader(promise);

        return promise;
    }

    showLoader(promise) {
        const self = this;
        const {loading} = self.elements;

        loading.classList.remove('d-none');

        promise
            .then((data) => {
                loading.classList.add('d-none');

                return data;
            })
            .catch((err) => {
                loading.classList.add('d-none');

                return err;
            });
    }

    showSearchResult() {
        const self = this;
        let {contents, pages} = self.searchresult;
        const selectedcontents = Object.fromEntries(self.selectedcontents.map((item) => [item.edflexid, true]));
        const {empty, list} = self.elements;

        if (!contents || contents.length === 0) {
            empty.classList.remove('d-none');
        } else {
            contents = contents.map((item) => {
                item.is_selected = selectedcontents[item.edflexid] || false;

                return item;
            });

            Templates
                .render('mod_edflex/content_search_result', {contents, pages})
                .then((html) => {
                    list.innerHTML = html;
                    return html;
                })
                .catch((error) => {
                    self.showError(error.message || error);
                });

            list.classList.remove('d-none');
        }
    }

    showSelectedContents() {
        const self = this;

        self.showselectedcontents = true;

        const {empty, selectedList} = self.elements;

        if (!self.selectedcontents.length) {
            empty.classList.remove('d-none');
        } else {
            self.selectedcontents.map((item) => {
                item.is_selected = true;
                return item;
            });

            Templates
                .render('mod_edflex/content_search_result', {contents: self.selectedcontents})
                .then((html) => {
                    selectedList.innerHTML = html;

                    return html;
                })
                .catch((error) => {
                    self.showError(error.message || error);
                });

            selectedList.classList.remove('d-none');
        }
    }

    loadBrowserContent() {
        const self = this;

        return Ajax.call([{
            methodname: 'mod_edflex_browser_html',
            args: {}
        }])[0].then((response) => {
            // Parse the HTML string into a DOM element.
            self.element.innerHTML = response.html;

            return response;
        });
    }

    resetDisplay() {
        const self = this;
        const {empty, list, selectedList, errors} = self.elements;

        self.showselectedcontents = false;

        // Reset display
        empty.classList.add('d-none');

        list.classList.add('d-none');

        selectedList.classList.add('d-none');

        errors.classList.add('d-none');

        self.refreshActionButtons();
    }

    showError(error) {
        const self = this;
        if (self.elements.errors) {
            self.elements.errors.classList.remove('d-none');
            self.elements.errors.innerHTML = error;
        }
    }

    selectContent(contentid) {
        const self = this;
        const contents = self.searchresult?.contents || [];
        const isselected = self.selectedcontents.find((item) => item.edflexid === contentid);

        if (isselected) {
            return;
        }

        const content = self.contentscache[contentid]
            || contents.find((item) => item.edflexid === contentid);

        if (content) {
            self.contentscache[content.edflexid] = content;
            self.selectedcontents.push(content);
        }

        self.refreshActionButtons();
    }

    unselectContent(contentid) {
        const self = this;
        self.selectedcontents = self.selectedcontents.filter((item) => item.edflexid !== contentid);

        self.refreshActionButtons();
    }
}
