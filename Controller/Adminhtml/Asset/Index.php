<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Controller\Adminhtml\Asset;

use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Api\PathResolverInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Psr\Log\LoggerInterface;

/**
 * Serves an image that sits next to a documentation page, refusing everything else with a 404.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magebit_Documentation::documentation';

    /**
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];

    /**
     * The type we send is decided here, never taken from the file or from the request.
     *
     * @var array<string, string>
     */
    private const MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
    ];

    private const SVG_MIME_TYPE = 'image/svg+xml';

    private const SVG_POLICY = "default-src 'none'; style-src 'unsafe-inline'; sandbox";

    /**
     * A documentation image larger than this is refused instead of being read into memory.
     */
    private const MAX_BYTES = 8388608;

    /**
     * @param Context $context
     * @param DocumentationTreeInterface $tree
     * @param PathResolverInterface $pathResolver
     * @param RawFactory $rawFactory
     * @param FileDriver $fileDriver
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly DocumentationTreeInterface $tree,
        private readonly PathResolverInterface $pathResolver,
        private readonly RawFactory $rawFactory,
        private readonly FileDriver $fileDriver,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Stream one documentation image, or a bare 404 when anything about the request is wrong.
     *
     * @return Raw
     */
    public function execute(): Raw
    {
        $moduleName = $this->stringParam('module');
        $sectionName = $this->stringParam('section');
        $path = $this->stringParam('path');

        if ($moduleName === '' || $sectionName === '' || $path === '' || str_ends_with($path, '/')) {
            return $this->notFound();
        }

        $section = $this->tree->getSection($moduleName, $sectionName);

        if ($section === null) {
            return $this->notFound();
        }

        $sectionRoot = $this->pathResolver->resolveSectionRoot($moduleName, $section->getPath());

        if ($sectionRoot === null) {
            return $this->notFound();
        }

        $file = $this->pathResolver->resolveFile($sectionRoot, $path, self::ALLOWED_EXTENSIONS);

        if ($file === null) {
            return $this->notFound();
        }

        $mimeType = self::MIME_TYPES[$this->extensionOf($file)] ?? null;

        // The resolver already accepted the extension, so this only catches the two lists drifting apart.
        if ($mimeType === null) {
            return $this->notFound();
        }

        try {
            $size = $this->fileDriver->stat($file)['size'] ?? null;

            if (!is_numeric($size) || (float)$size > self::MAX_BYTES) {
                $this->logger->warning(
                    'Magebit_Documentation refused a documentation image that is too large.',
                    ['path' => $file, 'size' => $size]
                );

                return $this->notFound();
            }

            $contents = $this->fileDriver->fileGetContents($file);
        } catch (FileSystemException $e) {
            $this->logger->warning(
                'Magebit_Documentation could not read a documentation image.',
                ['path' => $file, 'exception' => $e->getMessage()]
            );

            return $this->notFound();
        }

        return $this->image($contents, $mimeType);
    }

    /**
     * Read one request parameter as a string.
     *
     * Never decoded again: a second decode would turn an encoded "%2e%2e%2f" back into a real "../".
     *
     * @param string $name
     * @return string
     */
    private function stringParam(string $name): string
    {
        $value = $this->getRequest()->getParam($name, '');

        return is_string($value) ? trim($value) : '';
    }

    /**
     * The file ending of a path, lower cased and without the dot.
     *
     * @param string $path
     * @return string
     */
    private function extensionOf(string $path): string
    {
        $dot = strrpos($path, '.');

        return $dot === false ? '' : strtolower(substr($path, $dot + 1));
    }

    /**
     * The image itself, with the headers that keep the browser from treating it as anything else.
     *
     * @param string $contents
     * @param string $mimeType
     * @return Raw
     */
    private function image(string $contents, string $mimeType): Raw
    {
        $result = $this->rawFactory->create();
        $result->setContents($contents);
        $result->setHeader('Content-Type', $mimeType);
        $result->setHeader('Content-Disposition', 'inline');
        $result->setHeader('X-Content-Type-Options', 'nosniff');

        // An SVG can carry script. "sandbox" still holds if Magento_Csp prepends its own directives.
        if ($mimeType === self::SVG_MIME_TYPE) {
            $result->setHeader('Content-Security-Policy', self::SVG_POLICY);
        }

        return $result;
    }

    /**
     * The same empty 404 for every refusal, so the response never says why.
     *
     * @return Raw
     */
    private function notFound(): Raw
    {
        $result = $this->rawFactory->create();
        $result->setContents('');
        $result->setHttpResponseCode(404);

        return $result;
    }
}
