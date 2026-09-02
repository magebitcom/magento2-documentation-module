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
            closeLabel = labels.close || 'Close',
            enlargeLabel = labels.enlarge || 'Enlarge',
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
        followHeadings();
        wrapWideTables();
        addCopyButtons();
        bindZoom();

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
         * Open the stored nodes plus whatever the server already opened for the current page,
         * so a reload never hides the page the admin is on.
         *
         * @param {Array} open
         */
        function applyState(open) {
            var merged = open.slice();

            toggles.forEach(function (toggle) {
                var id = toggle.getAttribute('data-doc-toggle');

                if (isExpanded(toggle) && merged.indexOf(id) === -1) {
                    merged.push(id);
                }
            });

            toggles.forEach(function (toggle) {
                setExpanded(toggle, merged.indexOf(toggle.getAttribute('data-doc-toggle')) !== -1);
            });

            writeState();
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
         * Mark the table of contents entry of the heading the admin is reading.
         */
        function followHeadings() {
            var links = Array.prototype.slice.call(
                    element.querySelectorAll('[data-doc-toc-target] a[href^="#"]')
                ),
                headings = [],
                visible = {},
                observer;

            if (!links.length || !window.IntersectionObserver) {
                return;
            }

            links.forEach(function (link) {
                var id = decodeURIComponent(link.getAttribute('href').slice(1)),
                    heading = id ? document.getElementById(id) : null;

                if (heading) {
                    headings.push(heading);
                }
            });

            if (!headings.length) {
                return;
            }

            observer = new window.IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    visible[entry.target.id] = entry.isIntersecting;
                });
                markCurrent();
            }, {
                rootMargin: '0px 0px -60% 0px'
            });

            headings.forEach(function (heading) {
                observer.observe(heading);
            });

            /**
             * Light up the first heading still on screen, or the last one scrolled past.
             */
            function markCurrent() {
                var current = null,
                    index;

                for (index = 0; index < headings.length; index++) {
                    if (visible[headings[index].id]) {
                        current = headings[index];
                        break;
                    }
                }

                if (!current) {
                    for (index = headings.length - 1; index >= 0; index--) {
                        if (headings[index].getBoundingClientRect().top < 0) {
                            current = headings[index];
                            break;
                        }
                    }
                }

                links.forEach(function (link) {
                    var id = decodeURIComponent(link.getAttribute('href').slice(1));

                    link.classList.toggle('_active', current !== null && id === current.id);
                });
            }
        }

        /**
         * Let a wide table scroll on its own instead of pushing the page sideways.
         */
        function wrapWideTables() {
            var tables = element.querySelectorAll('[data-doc-content] table');

            Array.prototype.forEach.call(tables, function (table) {
                var wrapper = document.createElement('div');

                wrapper.className = 'doc-table-wrap';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            });
        }

        /**
         * Open images and diagrams full screen on click.
         */
        function bindZoom() {
            var content = element.querySelector('[data-doc-content]');

            if (!content) {
                return;
            }

            content.addEventListener('click', function (event) {
                var target = event.target.closest ? event.target.closest('img, .doc-diagram-canvas') : null;

                if (!target || target.closest('a')) {
                    return;
                }

                event.preventDefault();
                openZoom(target);
            });
        }

        /**
         * Show one image or diagram in an overlay that closes on click or Escape.
         *
         * @param {HTMLElement} source
         */
        function openZoom(source) {
            var overlay = document.createElement('div'),
                figure = document.createElement('div'),
                close = document.createElement('button'),
                copy = source.cloneNode(true),
                opener = document.activeElement;

            overlay.className = 'doc-zoom';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-label', source.getAttribute('alt') || enlargeLabel);

            close.type = 'button';
            close.className = 'doc-zoom-close';
            close.setAttribute('aria-label', closeLabel);
            close.textContent = '\u00d7';

            figure.className = 'doc-zoom-figure';
            copy.removeAttribute('id');
            figure.appendChild(copy);

            overlay.appendChild(close);
            overlay.appendChild(figure);
            document.body.appendChild(overlay);
            document.body.classList.add('_doc-zoomed');
            fitDiagram(copy.tagName === 'svg' ? copy : copy.querySelector('svg'), figure);
            close.focus();

            overlay.addEventListener('click', closeZoom);
            document.addEventListener('keydown', onKey);

            /**
             * @param {KeyboardEvent} event
             */
            function onKey(event) {
                if (event.key === 'Escape') {
                    closeZoom();
                }
            }

            /**
             * Take the overlay down and put focus back where it came from.
             */
            function closeZoom() {
                document.removeEventListener('keydown', onKey);
                document.body.classList.remove('_doc-zoomed');

                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }

                if (opener && opener.focus) {
                    opener.focus();
                }
            }
        }

        /**
         * Grow a drawn diagram to the largest size that still fits, keeping its shape.
         *
         * @param {SVGElement|null} svg
         * @param {HTMLElement} figure
         */
        function fitDiagram(svg, figure) {
            var box = svg && svg.viewBox ? svg.viewBox.baseVal : null,
                width,
                height;

            if (!box || !box.width || !box.height) {
                return;
            }

            width = Math.min(figure.clientWidth, figure.clientHeight * box.width / box.height);
            height = width * box.height / box.width;

            svg.style.maxWidth = 'none';
            svg.style.width = Math.floor(width) + 'px';
            svg.style.height = Math.floor(height) + 'px';
        }

        /**
         * Add a copy button to every code block, out of the cached page HTML.
         */
        function addCopyButtons() {
            var blocks = element.querySelectorAll('[data-doc-content] pre:not(.doc-diagram-source)');

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
