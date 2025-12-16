/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */
define([
    'jquery',
    'underscore'
], function ($, _) {
    'use strict';

    /**
     * Documentation search widget
     *
     * @param {Object} config
     * @param {HTMLElement} element
     */
    return function (config, element) {
        var $element = $(element),
            $searchInput = $element.find('#doc-search-input'),
            $clearButton = $element.find('.doc-search-clear'),
            $searchResults = $element.find('.doc-search-results'),
            $docTree = $element.find('.doc-tree'),
            $spinner = $element.find('.doc-search-spinner'),
            searchUrl = config.searchUrl,
            currentRequest = null,
            minQueryLength = config.minQueryLength || 2,
            debounceDelay = config.debounceDelay || 300;

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
         * Perform search request
         *
         * @param {String} query
         */
        function performSearch(query) {
            if (currentRequest) {
                currentRequest.abort();
            }

            $spinner.addClass('loading');

            var data = { q: query };
            var expand = getUrlParameter('expand');
            if (expand) {
                data.expand = expand;
            }
            var expandedModules = getUrlParameter('expanded_modules');
            if (expandedModules) {
                data.expanded_modules = expandedModules;
            }

            currentRequest = $.ajax({
                url: searchUrl,
                type: 'GET',
                dataType: 'json',
                data: data,
                success: function (response) {
                    if (response.success) {
                        showSearchResults(response.results, query);
                    }
                },
                error: function (xhr, status) {
                    if (status !== 'abort') {
                        console.error('Search request failed');
                    }
                },
                complete: function () {
                    $spinner.removeClass('loading');
                }
            });
        }

        /**
         * Display search results
         *
         * @param {Array} results
         * @param {String} query
         */
        function showSearchResults(results, query) {
            $docTree.hide();
            $searchResults.show();

            if (results.length === 0) {
                $searchResults.html(
                    '<p class="no-search-results">No results found for "' + escapeHtml(query) + '"</p>'
                );
                return;
            }

            var html = '<ul class="search-results-list">';

            $.each(results, function (index, item) {
                html += '<li class="search-result-item">';
                html += '<a href="' + escapeHtml(item.url) + '">';
                html += '<span class="search-result-name">' + highlightMatch(item.displayName, query) + '</span>';
                html += '<span class="search-result-breadcrumb">' + escapeHtml(item.breadcrumb) + '</span>';
                html += '</a>';
                html += '</li>';
            });

            html += '</ul>';
            $searchResults.html(html);
        }

        /**
         * Hide search results and show tree
         */
        function hideSearchResults() {
            $searchResults.hide().empty();
            $docTree.show();
        }

        /**
         * Escape HTML entities
         *
         * @param {String} text
         * @returns {String}
         */
        function escapeHtml(text) {
            return $('<div>').text(text).html();
        }

        /**
         * Highlight matching text
         *
         * @param {String} text
         * @param {String} query
         * @returns {String}
         */
        function highlightMatch(text, query) {
            var escaped = escapeHtml(text),
                regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');

            return escaped.replace(regex, '<mark>$1</mark>');
        }

        /**
         * Escape regex special characters
         *
         * @param {String} string
         * @returns {String}
         */
        function escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        // Debounced search handler
        var debouncedSearch = _.debounce(function (query) {
            performSearch(query);
        }, debounceDelay);

        // Bind input event
        $searchInput.on('input', function () {
            var query = $.trim($(this).val());

            if (query.length === 0) {
                hideSearchResults();
                return;
            }

            if (query.length < minQueryLength) {
                return;
            }

            debouncedSearch(query);
        });

        // Bind keydown for escape
        $searchInput.on('keydown', function (e) {
            if (e.key === 'Escape') {
                $(this).val('').blur();
                hideSearchResults();
            }
        });

        // Bind clear button
        $clearButton.on('click', function () {
            $searchInput.val('').focus();
            hideSearchResults();
        });
    };
});
