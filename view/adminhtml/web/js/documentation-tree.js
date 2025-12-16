/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

define([
    'jquery',
    'hljs'
], function ($, hljs) {
    'use strict';

    /**
     * Documentation tree widget
     *
     * @param {Object} config
     * @param {HTMLElement} element
     */
    return function (config, element) {
        var $element = $(element);
        var isExpanded = false;

        /**
         * Get URL parameter value
         *
         * @param {string} name
         * @return {string|null}
         */
        function getUrlParameter(name) {
            var params = new URLSearchParams(window.location.search);
            return params.get(name);
        }

        /**
         * Update URL parameter
         *
         * @param {string} name
         * @param {string} value
         */
        function updateUrlParameter(name, value) {
            var url = new URL(window.location.href);
            url.searchParams.set(name, value);
            window.history.replaceState({}, '', url.toString());
        }

        /**
         * Initialize syntax highlighting
         */
        function initSyntaxHighlighting() {
            $element.find('.documentation-content pre code').each(function () {
                hljs.highlightElement(this);
            });
        }

        /**
         * Initialize collapsible sections
         */
        function initCollapsible() {
            $element.on('click', '[data-collapse-toggle]', function (e) {
                if ($(e.target).is('a')) {
                    return;
                }

                // Ignore clicks on module expand button
                if ($(e.target).closest('[data-module-expand]').length) {
                    return;
                }

                $(this).toggleClass('collapsed');
            });
        }

        /**
         * Expand parent elements of the given element
         *
         * @param {jQuery} $activeElement
         */
        function expandParents($activeElement) {
            $activeElement.parents('.collapsible-content').each(function () {
                var $toggle = $(this).prev('[data-collapse-toggle]');

                if ($toggle.length) {
                    $toggle.removeClass('collapsed');
                }
            });
        }

        /**
         * Initialize active item expansion
         * Expands parents of active item, or first module/feature if no active
         */
        function initActiveExpansion() {
            var expandParam = getUrlParameter('expand');

            if (expandParam === 'all') {
                // Expand all sections
                expandAll();
                isExpanded = true;
                updateExpandButton();
            } else {
                var $activeLink = $element.find('.doc-files a.active');

                if ($activeLink.length) {
                    expandParents($activeLink);
                } else {
                    // No active link - expand first module and first feature
                    var $firstModule = $element.find('.doc-module').first();
                    $firstModule.find('> .doc-module-header').removeClass('collapsed');
                    $firstModule.find('.doc-feature-header').first().removeClass('collapsed');
                }
            }
        }

        /**
         * Expand all collapsible sections
         */
        function expandAll() {
            $element.find('[data-collapse-toggle]').removeClass('collapsed');
        }

        /**
         * Collapse all collapsible sections
         */
        function collapseAll() {
            $element.find('[data-collapse-toggle]').addClass('collapsed');
        }

        /**
         * Update expand button state
         */
        function updateExpandButton() {
            var $button = $element.find('[data-expand-all]');
            var $icon = $button.find('.expand-all-icon');
            var $text = $button.find('.expand-all-text');

            if (isExpanded) {
                $button.attr('title', 'Collapse All');
                $icon.addClass('expanded');
                $text.text('Collapse All');
            } else {
                $button.attr('title', 'Expand All');
                $icon.removeClass('expanded');
                $text.text('Expand All');
            }
        }

        /**
         * Expand all features within a module
         *
         * @param {jQuery} $module
         */
        function expandModule($module) {
            $module.find('.doc-feature-header, .doc-category-header').removeClass('collapsed');
            $module.addClass('expanded');
        }

        /**
         * Collapse all features within a module
         *
         * @param {jQuery} $module
         */
        function collapseModule($module) {
            $module.find('.doc-feature-header, .doc-category-header').addClass('collapsed');
            $module.removeClass('expanded');
        }

        /**
         * Get expanded module IDs from URL
         *
         * @return {Array}
         */
        function getExpandedModules() {
            var param = getUrlParameter('expanded_modules');
            return param ? param.split(',') : [];
        }

        /**
         * Update expanded modules in URL
         *
         * @param {Array} moduleIds
         */
        function updateExpandedModules(moduleIds) {
            var url = new URL(window.location.href);
            if (moduleIds.length > 0) {
                url.searchParams.set('expanded_modules', moduleIds.join(','));
            } else {
                url.searchParams.delete('expanded_modules');
            }
            window.history.replaceState({}, '', url.toString());
        }

        /**
         * Initialize module expand buttons
         */
        function initModuleExpand() {
            // Load expanded modules from URL
            var expandedModules = getExpandedModules();
            expandedModules.forEach(function(moduleId) {
                var $module = $element.find('[data-module-id="' + moduleId + '"]');
                if ($module.length) {
                    expandModule($module);
                }
            });

            // Handle module expand button clicks
            $element.on('click', '[data-module-expand]', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var $module = $(this).closest('.doc-module');
                var moduleId = $module.attr('data-module-id');
                var expandedModules = getExpandedModules();

                if ($module.hasClass('expanded')) {
                    collapseModule($module);
                    expandedModules = expandedModules.filter(function(id) {
                        return id !== moduleId;
                    });
                } else {
                    expandModule($module);
                    if (expandedModules.indexOf(moduleId) === -1) {
                        expandedModules.push(moduleId);
                    }
                }

                updateExpandedModules(expandedModules);
            });
        }

        /**
         * Initialize expand/collapse all button
         */
        function initExpandAll() {
            $element.on('click', '[data-expand-all]', function (e) {
                e.preventDefault();

                if (isExpanded) {
                    collapseAll();
                    isExpanded = false;
                    updateUrlParameter('expand', 'none');
                    // Clear module expansions
                    $element.find('.doc-module').removeClass('expanded');
                    updateExpandedModules([]);
                } else {
                    expandAll();
                    isExpanded = true;
                    updateUrlParameter('expand', 'all');
                    // Mark all modules as expanded
                    var allModuleIds = [];
                    $element.find('.doc-module').each(function() {
                        var moduleId = $(this).attr('data-module-id');
                        if (moduleId) {
                            allModuleIds.push(moduleId);
                            $(this).addClass('expanded');
                        }
                    });
                    updateExpandedModules(allModuleIds);
                }

                updateExpandButton();
            });
        }

        // Initialize
        initSyntaxHighlighting();
        initCollapsible();
        initModuleExpand();
        initExpandAll();
        initActiveExpansion();
    };
});
