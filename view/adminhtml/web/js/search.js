/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */
define([], function () {
    'use strict';

    var DEFAULT_MIN_LENGTH = 2,
        DEFAULT_DELAY = 300,
        SELECTED_CLASS = '_selected';

    return function (config, element) {
        var settings = config || {},
            labels = settings.labels || {},
            noResultsText = labels.noResults || 'No results found.',
            failedText = labels.failed || 'The search did not run.',
            input = element.querySelector('[data-doc-search-input]'),
            clear = element.querySelector('[data-doc-search-clear]'),
            results = element.querySelector('[data-doc-search-results]'),
            tree = element.querySelector('[data-doc-tree]'),
            minLength = settings.minQueryLength || DEFAULT_MIN_LENGTH,
            delay = settings.debounceDelay || DEFAULT_DELAY,
            timer = null,
            request = 0,
            links = [],
            selected = -1;

        if (!input || !results || !tree || !settings.searchUrl) {
            return;
        }

        input.addEventListener('input', function () {
            window.clearTimeout(timer);

            if (clear) {
                clear.hidden = input.value === '';
            }

            if (input.value.trim().length < minLength) {
                restore();

                return;
            }

            timer = window.setTimeout(function () {
                search(input.value.trim());
            }, delay);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                select(selected + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                select(selected - 1);
            } else if (event.key === 'Enter') {
                open(event);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                input.value = '';
                restore();
            }
        });

        if (clear) {
            clear.addEventListener('click', function () {
                input.value = '';
                restore();
                input.focus();
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || isTyping(event.target)) {
                return;
            }

            event.preventDefault();
            input.focus();
            input.select();
        });

        /**
         * Whether the admin is already writing somewhere else on the page.
         *
         * @param {HTMLElement} target
         * @return {Boolean}
         */
        function isTyping(target) {
            var name;

            if (!target) {
                return false;
            }

            if (target.isContentEditable) {
                return true;
            }

            name = (target.tagName || '').toLowerCase();

            return name === 'input' || name === 'textarea' || name === 'select';
        }

        /**
         * Ask the search endpoint and show whatever comes back.
         *
         * @param {String} query
         */
        function search(query) {
            var token = ++request,
                url = settings.searchUrl + (settings.searchUrl.indexOf('?') === -1 ? '?' : '&') +
                    'q=' + encodeURIComponent(query);

            window.fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                // A slower earlier request must not overwrite what the admin is looking at now.
                if (token !== request) {
                    return;
                }

                if (!data || !data.success) {
                    show(message(data && data.message ? String(data.message) : failedText));

                    return;
                }

                render(Array.isArray(data.results) ? data.results : [], query);
            }).catch(function () {
                if (token === request) {
                    show(message(failedText));
                }
            });
        }

        /**
         * Build the result list. Every value from the endpoint is plain text, so it is set as text.
         *
         * @param {Array} hits
         * @param {String} query
         */
        function render(hits, query) {
            var list;

            if (!hits.length) {
                show(message(noResultsText));

                return;
            }

            list = document.createElement('ul');
            list.className = 'doc-search-list';

            hits.forEach(function (hit) {
                list.appendChild(resultItem(hit, query));
            });

            show(list);
            links = Array.prototype.slice.call(results.querySelectorAll('.doc-search-link'));
            select(0);
        }

        /**
         * One result row.
         *
         * @param {Object} hit
         * @param {String} query
         * @return {HTMLElement}
         */
        function resultItem(hit, query) {
            var item = document.createElement('li'),
                link = document.createElement('a'),
                title = document.createElement('span'),
                breadcrumb = document.createElement('span'),
                snippet = document.createElement('span');

            item.className = 'doc-search-item';
            link.className = 'doc-search-link';
            link.href = hit.url || '#';

            title.className = 'doc-search-title';
            highlight(title, String(hit.title || ''), query);

            breadcrumb.className = 'doc-search-breadcrumb';
            breadcrumb.textContent = String(hit.breadcrumb || '');

            snippet.className = 'doc-search-snippet';
            highlight(snippet, String(hit.snippet || ''), query);

            link.appendChild(title);
            link.appendChild(breadcrumb);
            link.appendChild(snippet);
            item.appendChild(link);

            return item;
        }

        /**
         * Write text into an element, marking every place the query appears.
         *
         * @param {HTMLElement} target
         * @param {String} text
         * @param {String} query
         */
        function highlight(target, text, query) {
            var needle = query.toLowerCase(),
                haystack = text.toLowerCase(),
                from = 0,
                mark,
                at;

            if (!needle) {
                target.textContent = text;

                return;
            }

            at = haystack.indexOf(needle, from);

            while (at !== -1) {
                if (at > from) {
                    target.appendChild(document.createTextNode(text.slice(from, at)));
                }

                mark = document.createElement('mark');
                mark.textContent = text.slice(at, at + needle.length);
                target.appendChild(mark);
                from = at + needle.length;
                at = haystack.indexOf(needle, from);
            }

            target.appendChild(document.createTextNode(text.slice(from)));
        }

        /**
         * A single line instead of a result list.
         *
         * @param {String} text
         * @return {HTMLElement}
         */
        function message(text) {
            var paragraph = document.createElement('p');

            paragraph.className = 'doc-search-message';
            paragraph.textContent = text;

            return paragraph;
        }

        /**
         * Put one element in the result box and hide the tree behind it.
         *
         * @param {HTMLElement} content
         */
        function show(content) {
            results.textContent = '';
            results.appendChild(content);
            results.hidden = false;
            tree.hidden = true;
            links = [];
            selected = -1;
        }

        /**
         * Drop the results and bring the tree back.
         */
        function restore() {
            window.clearTimeout(timer);
            request++;
            results.textContent = '';
            results.hidden = true;
            tree.hidden = false;
            links = [];
            selected = -1;

            if (clear) {
                clear.hidden = input.value === '';
            }
        }

        /**
         * Move the highlight through the results.
         *
         * @param {Number} index
         */
        function select(index) {
            if (!links.length) {
                return;
            }

            if (selected >= 0 && links[selected]) {
                links[selected].classList.remove(SELECTED_CLASS);
            }

            selected = Math.max(0, Math.min(index, links.length - 1));
            links[selected].classList.add(SELECTED_CLASS);
            links[selected].scrollIntoView({
                block: 'nearest'
            });
        }

        /**
         * Follow the highlighted result.
         *
         * @param {Event} event
         */
        function open(event) {
            if (selected < 0 || !links[selected]) {
                return;
            }

            event.preventDefault();
            window.location.assign(links[selected].href);
        }
    };
});
