/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */
define([], function () {
    'use strict';

    var STORAGE_KEY = 'magebit_documentation_expanded';

    return function (config, element) {
        var labels = config && config.labels ? config.labels : {},
            copyLabel = labels.copy || 'Copy',
            copiedLabel = labels.copied || 'Copied',
            copyFailedLabel = labels.copyFailed || 'Press Ctrl+C',
            toggles = Array.prototype.slice.call(element.querySelectorAll('[data-doc-toggle]')),
            stored = readState();

        if (stored !== null) {
            applyState(stored);
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                setExpanded(toggle, !isExpanded(toggle));
                writeState();
            });
        });

        bindSidebarToggle();
        moveTableOfContents();
        addCopyButtons();

        /**
         * Whether one tree node is open.
         *
         * @param {HTMLElement} toggle
         * @return {Boolean}
         */
        function isExpanded(toggle) {
            return toggle.getAttribute('aria-expanded') === 'true';
        }

        /**
         * Open or close one tree node and the panel that follows it.
         *
         * @param {HTMLElement} toggle
         * @param {Boolean} expanded
         */
        function setExpanded(toggle, expanded) {
            var panel = toggle.nextElementSibling;

            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

            if (panel) {
                panel.hidden = !expanded;
            }
        }

        /**
         * The node ids the admin last left open, or null when nothing was ever stored.
         *
         * @return {Array|null}
         */
        function readState() {
            var raw;

            try {
                raw = window.localStorage.getItem(STORAGE_KEY);
            } catch (error) {
                return null;
            }

            if (!raw) {
                return null;
            }

            try {
                raw = JSON.parse(raw);
            } catch (error) {
                return null;
            }

            return Array.isArray(raw) ? raw : null;
        }

        /**
         * Remember every node that is open right now.
         */
        function writeState() {
            var open = toggles.filter(isExpanded).map(function (toggle) {
                return toggle.getAttribute('data-doc-toggle');
            });

            try {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(open));
            } catch (error) {
                // Storage can be full or switched off; the tree still works for this page.
            }
        }

        /**
         * Open exactly the stored nodes, so a reload shows the tree the admin left behind.
         *
         * @param {Array} open
         */
        function applyState(open) {
            toggles.forEach(function (toggle) {
                setExpanded(toggle, open.indexOf(toggle.getAttribute('data-doc-toggle')) !== -1);
            });
        }

        /**
         * Show and hide the tree on narrow screens.
         */
        function bindSidebarToggle() {
            var button = element.querySelector('[data-doc-sidebar-toggle]'),
                sidebar = element.querySelector('[data-doc-sidebar]');

            if (!button || !sidebar) {
                return;
            }

            button.addEventListener('click', function () {
                var open = sidebar.classList.toggle('_open');

                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        /**
         * Put the generated table of contents in its own column.
         */
        function moveTableOfContents() {
            var container = element.querySelector('[data-doc-toc]'),
                target = element.querySelector('[data-doc-toc-target]'),
                list = element.querySelector('[data-doc-content] .table-of-contents');

            if (!container || !target || !list) {
                return;
            }

            target.appendChild(list);
            container.hidden = false;
        }

        /**
         * Add a copy button to every code block, out of the cached page HTML.
         */
        function addCopyButtons() {
            var blocks = element.querySelectorAll('[data-doc-content] pre');

            Array.prototype.forEach.call(blocks, function (block) {
                var wrapper = document.createElement('div'),
                    button = document.createElement('button');

                wrapper.className = 'doc-pre';
                block.parentNode.insertBefore(wrapper, block);
                wrapper.appendChild(block);

                button.type = 'button';
                button.className = 'doc-copy';
                button.textContent = copyLabel;
                button.addEventListener('click', function () {
                    copy(block.textContent, button);
                });
                wrapper.appendChild(button);
            });
        }

        /**
         * Copy one code block and say on the button how it went.
         *
         * @param {String} text
         * @param {HTMLElement} button
         */
        function copy(text, button) {
            if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
                window.navigator.clipboard.writeText(text).then(function () {
                    flash(button, copiedLabel);
                }, function () {
                    flash(button, copyFailedLabel);
                });

                return;
            }

            flash(button, copyWithSelection(text) ? copiedLabel : copyFailedLabel);
        }

        /**
         * Older browsers have no clipboard API, so copy the old way.
         *
         * @param {String} text
         * @return {Boolean}
         */
        function copyWithSelection(text) {
            var field = document.createElement('textarea'),
                copied = false;

            field.value = text;
            field.setAttribute('readonly', 'readonly');
            field.className = 'doc-copy-source';
            document.body.appendChild(field);
            field.select();

            try {
                copied = document.execCommand('copy');
            } catch (error) {
                copied = false;
            }

            document.body.removeChild(field);

            return copied;
        }

        /**
         * Show a short message on the copy button.
         *
         * @param {HTMLElement} button
         * @param {String} message
         */
        function flash(button, message) {
            button.textContent = message;
            window.setTimeout(function () {
                button.textContent = copyLabel;
            }, 2000);
        }
    };
});
