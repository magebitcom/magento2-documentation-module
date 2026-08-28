<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Tree;

use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Data\Section;
use Magebit\Documentation\Model\Tree\Storage;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    /**
     * @var FrontendInterface&MockObject
     */
    private FrontendInterface $cache;

    /**
     * @var Storage
     */
    private Storage $storage;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(FrontendInterface::class);
        $this->storage = new Storage($this->cache, new Json());
    }

    public function testReturnsNullWhenNothingIsCached(): void
    {
        $this->cache->method('load')->willReturn(false);

        $this->assertNull($this->storage->load());
    }

    public function testSavesUnderTheDocumentationTreeKey(): void
    {
        $this->cache->expects($this->once())
            ->method('save')
            ->with($this->isType('string'), 'magebit_documentation_tree');

        $this->storage->save($this->tree());
    }

    public function testRoundTripsTheWholeTreeThroughTheCache(): void
    {
        $loaded = $this->roundTrip($this->tree());

        $this->assertNotNull($loaded);
        $this->assertSame(['Vendor_A'], array_keys($loaded));

        $module = $loaded['Vendor_A'];
        $this->assertSame('Vendor_A', $module->getModuleName());
        $this->assertSame('Vendor A', $module->getTitle());
        $this->assertSame('Vendor_A::images/i.svg', $module->getIcon());
        $this->assertSame(10, $module->getSortOrder());

        $sections = $module->getSections();
        $this->assertCount(2, $sections);
        $this->assertSame(['Guide', 'Changelog'], array_map(static fn ($s) => $s->getName(), $sections));
        $this->assertSame('Vendor_B::Docs', $sections[0]->getPath());
        $this->assertSame('Vendor_A::secret', $sections[0]->getAcl());
        $this->assertSame(20, $sections[0]->getSortOrder());
        $this->assertFalse($sections[0]->isChangelog());
        $this->assertNull($sections[1]->getAcl());
        $this->assertTrue($sections[1]->isChangelog());

        $root = $sections[0]->getRoot();
        $this->assertSame('', $root->getLabel());
        $this->assertSame(100, $root->getSortOrder());
        $this->assertSame('index.md', $root->getPages()[0]->getRelativePath());
        $this->assertSame('index.md', $root->getPages()[0]->getFileName());
        $this->assertSame('Overview', $root->getPages()[0]->getTitle());
        $this->assertSame(1, $root->getPages()[0]->getSortOrder());
        $this->assertTrue($root->getPages()[0]->isIndex());

        $child = $root->getCategories()[0];
        $this->assertSame('Advanced', $child->getLabel());
        $this->assertSame(20, $child->getSortOrder());
        $this->assertSame('advanced/api.md', $child->getPages()[0]->getRelativePath());
        $this->assertSame('api.md', $child->getPages()[0]->getFileName());
        $this->assertFalse($child->getPages()[0]->isIndex());
    }

    public function testReturnsNullWhenTheCachedPayloadIsNotReadable(): void
    {
        $this->cache->method('load')->willReturn('not json at all');

        $this->assertNull($this->storage->load());
    }

    public function testWritesTheTreeAsPlainArrays(): void
    {
        $written = null;
        $this->cache->method('save')->willReturnCallback(
            static function ($data) use (&$written): bool {
                $written = $data;

                return true;
            }
        );

        $this->storage->save($this->tree());

        $this->assertSame($this->payload(), (new Json())->unserialize((string)$written));
    }

    public function testReturnsNullWhenAModuleFieldHasTheWrongType(): void
    {
        $this->cache->method('load')->willReturn((new Json())->serialize($this->payload(title: 7)));

        $this->assertNull($this->storage->load());
    }

    public function testReturnsNullWhenAPageFieldHasTheWrongType(): void
    {
        $this->cache->method('load')->willReturn((new Json())->serialize($this->payload(isIndex: 'yes')));

        $this->assertNull($this->storage->load());
    }

    /**
     * Save a tree, then load back whatever was written.
     *
     * @param array<string, ModuleDocsInterface> $tree
     * @return array<string, ModuleDocsInterface>|null
     */
    private function roundTrip(array $tree): ?array
    {
        $written = null;
        $this->cache->method('save')->willReturnCallback(
            static function ($data) use (&$written): bool {
                $written = $data;

                return true;
            }
        );
        $this->cache->method('load')->willReturnCallback(
            static function () use (&$written) {
                return $written;
            }
        );

        $this->storage->save($tree);

        return $this->storage->load();
    }

    /**
     * @return array<string, ModuleDocsInterface>
     */
    private function tree(): array
    {
        $advanced = new Category('Advanced', 20, [new Page('advanced/api.md', 'api.md', 'Api', 10, false)], []);
        $root = new Category('', 100, [new Page('index.md', 'index.md', 'Overview', 1, true)], [$advanced]);
        $changelogRoot = new Category('', 100, [new Page('CHANGELOG.md', 'CHANGELOG.md', 'Changelog', 100, true)], []);

        return [
            'Vendor_A' => new ModuleDocs('Vendor_A', 'Vendor A', 'Vendor_A::images/i.svg', 10, [
                new Section('Guide', 'Vendor_B::Docs', 'Vendor_A::secret', 20, $root, false),
                new Section('Changelog', 'CHANGELOG.md', null, 1000, $changelogRoot, true),
            ]),
        ];
    }

    /**
     * The array shape the cache holds, with two fields the tests can spoil.
     *
     * @param mixed $title
     * @param mixed $isIndex
     * @return array<string, mixed>
     */
    private function payload(mixed $title = 'Vendor A', mixed $isIndex = true): array
    {
        return [
            'Vendor_A' => [
                'moduleName' => 'Vendor_A',
                'title' => $title,
                'icon' => 'Vendor_A::images/i.svg',
                'sortOrder' => 10,
                'sections' => [
                    [
                        'name' => 'Guide',
                        'path' => 'Vendor_B::Docs',
                        'acl' => 'Vendor_A::secret',
                        'sortOrder' => 20,
                        'isChangelog' => false,
                        'root' => [
                            'label' => '',
                            'sortOrder' => 100,
                            'pages' => [
                                [
                                    'relativePath' => 'index.md',
                                    'fileName' => 'index.md',
                                    'title' => 'Overview',
                                    'sortOrder' => 1,
                                    'isIndex' => $isIndex,
                                ],
                            ],
                            'categories' => [
                                [
                                    'label' => 'Advanced',
                                    'sortOrder' => 20,
                                    'pages' => [
                                        [
                                            'relativePath' => 'advanced/api.md',
                                            'fileName' => 'api.md',
                                            'title' => 'Api',
                                            'sortOrder' => 10,
                                            'isIndex' => false,
                                        ],
                                    ],
                                    'categories' => [],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Changelog',
                        'path' => 'CHANGELOG.md',
                        'acl' => null,
                        'sortOrder' => 1000,
                        'isChangelog' => true,
                        'root' => [
                            'label' => '',
                            'sortOrder' => 100,
                            'pages' => [
                                [
                                    'relativePath' => 'CHANGELOG.md',
                                    'fileName' => 'CHANGELOG.md',
                                    'title' => 'Changelog',
                                    'sortOrder' => 100,
                                    'isIndex' => true,
                                ],
                            ],
                            'categories' => [],
                        ],
                    ],
                ],
            ],
        ];
    }
}
