<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\ViewModel;

use Magebit\Documentation\Api\DocumentationProviderInterface;
use Magebit\Documentation\Api\MarkdownRendererInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for documentation content
 */
class Content implements ArgumentInterface
{
    /**
     * @param DocumentationProviderInterface $documentationProvider
     * @param MarkdownRendererInterface $markdownRenderer
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly DocumentationProviderInterface $documentationProvider,
        private readonly MarkdownRendererInterface $markdownRenderer,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Get rendered markdown content as HTML
     *
     * @return string
     */
    public function getRenderedContent(): string
    {
        $module = $this->request->getParam('module');
        $feature = $this->request->getParam('feature');
        $file = $this->request->getParam('file');

        if (!$module || !$feature || !$file) {
            $first = $this->documentationProvider->getFirstFile();
            if ($first) {
                $module = $first['module'];
                $feature = $first['feature'];
                $featurePath = $first['featurePath'];
                $file = $first['file'];
            } else {
                return '<p>No documentation available.</p>';
            }
        } else {
            $tree = $this->documentationProvider->getDocumentationTree();
            $featurePath = $tree[$module][$feature]['path'] ?? null;
            if (!$featurePath) {
                return '<p>Documentation not found.</p>';
            }
        }

        $content = $this->documentationProvider->getFileContent($module, $featurePath, $file);
        if ($content === null) {
            return '<p>Documentation file not found.</p>';
        }

        return $this->markdownRenderer->render($content, [
            'module' => $module,
            'feature' => $feature,
            'file' => $file,
        ]);
    }
}
