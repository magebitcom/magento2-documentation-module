/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */
define(['Magebit_Documentation/js/vendor/highlight/highlight.min'], function (hljs) {
    'use strict';

    var DEFAULT_LANGUAGE_PATH = 'Magebit_Documentation/js/vendor/highlight/languages';

    return function (config, element) {
        var settings = config || {},
            basePath = settings.languagePath || DEFAULT_LANGUAGE_PATH,
            extras = settings.languages || [],
            blocks = Array.prototype.slice.call(
                element.querySelectorAll('pre code[data-doc-highlight]')
            );

        if (!blocks.length) {
            return;
        }

        loadMissingLanguages(blocks, extras, basePath, function () {
            highlightInChunks(blocks);
        });

        /**
         * Load only the language files this page actually needs.
         *
         * @param {Array} nodes
         * @param {Array} configured
         * @param {String} languagePath
         * @param {Function} done
         */
        function loadMissingLanguages(nodes, configured, languagePath, done) {
            var wanted = {},
                names;

            nodes.forEach(function (node) {
                want(node.getAttribute('data-doc-highlight'));
            });

            configured.forEach(want);

            names = Object.keys(wanted);

            if (!names.length) {
                done();

                return;
            }

            require(
                names.map(function (name) {
                    return languagePath + '/' + name + '.min';
                }),
                done,
                done
            );

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
