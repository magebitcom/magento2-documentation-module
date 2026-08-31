/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */
define(['Magebit_Documentation/js/vendor/highlight/highlight.min'], function (hljs) {
    'use strict';

    var LANGUAGE_PATH = 'Magebit_Documentation/js/vendor/highlight/languages';

    return function (config, element) {
        var settings = config || {},
            extras = Array.isArray(settings.languages) ? settings.languages : [],
            blocks = Array.prototype.slice.call(
                element.querySelectorAll('pre code[data-doc-highlight]')
            );

        if (!blocks.length) {
            return;
        }

        loadMissingLanguages(blocks, extras, function () {
            highlightInChunks(blocks);
        });

        /**
         * Load only the language files this page actually needs.
         *
         * @param {Array} nodes
         * @param {Array} configured
         * @param {Function} done
         */
        function loadMissingLanguages(nodes, configured, done) {
            var wanted = {},
                names,
                pending;

            nodes.forEach(function (node) {
                want(node.getAttribute('data-doc-highlight'));
            });

            configured.forEach(want);

            names = Object.keys(wanted);
            pending = names.length;

            if (!pending) {
                done();

                return;
            }

            // One request per language, so a file that fails costs only its own blocks.
            names.forEach(function (name) {
                require([LANGUAGE_PATH + '/' + name + '.min'], finish, function (error) {
                    console.warn(
                        'Magebit_Documentation could not load highlighting for "' + name + '".',
                        error
                    );
                    finish();
                });
            });

            /**
             * Remember a language highlight.js does not know yet.
             *
             * @param {String} name
             */
            function want(name) {
                if (name && !hljs.getLanguage(name)) {
                    wanted[name] = true;
                }
            }

            /**
             * Start highlighting once every language has been tried.
             */
            function finish() {
                pending--;

                if (!pending) {
                    done();
                }
            }
        }

        /**
         * Highlight blocks a few at a time so long pages stay responsive.
         *
         * @param {Array} nodes
         */
        function highlightInChunks(nodes) {
            var index = 0;

            (function step() {
                var end = Math.min(index + 5, nodes.length);

                for (; index < end; index++) {
                    hljs.highlightElement(nodes[index]);
                }

                if (index < nodes.length) {
                    schedule(step);
                }
            })();
        }

        /**
         * Run the next chunk when the browser is free.
         *
         * @param {Function} callback
         */
        function schedule(callback) {
            if (window.requestIdleCallback) {
                window.requestIdleCallback(callback);
            } else {
                window.setTimeout(callback, 0);
            }
        }
    };
});
