/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */
define(['Magebit_Documentation/js/vendor/mermaid/mermaid.min'], function (mermaid) {
    'use strict';

    var counter = 0;

    return function (config, element) {
        var labels = config && config.labels ? config.labels : {},
            failedLabel = labels.failed || 'This diagram could not be drawn.',
            blocks = Array.prototype.slice.call(element.querySelectorAll('[data-doc-mermaid]'));

        if (!blocks.length) {
            return;
        }

        mermaid.initialize({
            startOnLoad: false,
            securityLevel: 'strict',
            theme: 'neutral',
            fontFamily: 'inherit'
        });

        blocks.forEach(draw);

        /**
         * Replace the source of one block with its drawing, or say why that did not work.
         *
         * @param {HTMLElement} block
         */
        function draw(block) {
            var source = block.querySelector('.doc-diagram-source'),
                canvas = document.createElement('div'),
                id;

            if (!source) {
                return;
            }

            canvas.className = 'doc-diagram-canvas';
            counter++;
            id = 'doc-diagram-' + counter;

            mermaid.render(id, source.textContent).then(function (result) {
                canvas.innerHTML = result.svg;
                block.appendChild(canvas);
                source.hidden = true;
                block.classList.add('_drawn');

                if (result.bindFunctions) {
                    result.bindFunctions(canvas);
                }
            }, function (error) {
                var message = document.createElement('p'),
                    leftover = document.getElementById('d' + id);

                // Mermaid leaves its own error drawing at the end of the page; the message below replaces it.
                if (leftover && leftover.parentNode) {
                    leftover.parentNode.removeChild(leftover);
                }

                message.className = 'doc-diagram-error';
                message.textContent = failedLabel;
                block.insertBefore(message, source);
                block.classList.add('_failed');
                console.warn('Magebit_Documentation could not draw a mermaid diagram.', error);
            });
        }
    };
});
