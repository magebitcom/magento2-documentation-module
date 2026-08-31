<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Controller\Adminhtml\Search;

use Magebit\Documentation\Api\Data\SearchHitInterface;
use Magebit\Documentation\Api\SearchIndexInterface;
use Magebit\Documentation\Model\Markdown\UrlBuilder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Answers the documentation search box with JSON.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magebit_Documentation::documentation';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param SearchIndexInterface $searchIndex
     * @param UrlBuilder $urlBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly SearchIndexInterface $searchIndex,
        private readonly UrlBuilder $urlBuilder,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Search the documentation and return the hits the current admin may see.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $query = $this->getRequest()->getParam('q', '');

        try {
            $hits = $this->searchIndex->search(is_string($query) ? $query : '');
            $results = array_map(fn (SearchHitInterface $hit): array => $this->toArray($hit), $hits);

            return $result->setData(['success' => true, 'results' => $results, 'count' => count($results)]);
        } catch (Throwable $e) {
            $this->logger->error(
                'Magebit_Documentation could not search the documentation.',
                ['exception' => $e->getMessage()]
            );

            // The reason stays in the log: the client only learns that the search did not run.
            return $result->setData(
                ['success' => false, 'message' => __('Search is temporarily unavailable.')]
            );
        }
    }

    /**
     * One hit, as the search box needs it.
     *
     * @param SearchHitInterface $hit
     * @return array{title: string, snippet: string, breadcrumb: string, url: string}
     */
    private function toArray(SearchHitInterface $hit): array
    {
        return [
            'title' => $hit->getTitle(),
            'snippet' => $hit->getSnippet(),
            'breadcrumb' => $hit->getBreadcrumb(),
            'url' => $this->urlBuilder->page($hit->getModuleName(), $hit->getSectionName(), $hit->getRelativePath()),
        ];
    }
}
