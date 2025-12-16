<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Controller\Adminhtml\Search;

use Magebit\Documentation\Api\SearchServiceInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Documentation search controller
 */
class Index extends Action
{
    /**
     * ACL resource for documentation access
     */
    public const ADMIN_RESOURCE = 'Magebit_Documentation::documentation';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param SearchServiceInterface $searchService
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly SearchServiceInterface $searchService
    ) {
        parent::__construct($context);
    }

    /**
     * Execute search and return JSON results
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $query = (string) $this->getRequest()->getParam('q', '');

        // Preserve additional parameters like expand state
        $additionalParams = [];
        $expand = $this->getRequest()->getParam('expand');
        if ($expand) {
            $additionalParams['expand'] = $expand;
        }
        $expandedModules = $this->getRequest()->getParam('expanded_modules');
        if ($expandedModules) {
            $additionalParams['expanded_modules'] = $expandedModules;
        }

        try {
            $searchResults = $this->searchService->search($query, $additionalParams);
            $data = array_map(
                fn($item) => $item->toArray(),
                $searchResults
            );

            $result->setData([
                'success' => true,
                'results' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
                'results' => [],
                'count' => 0,
            ]);
        }

        return $result;
    }
}
