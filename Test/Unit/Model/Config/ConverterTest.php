<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Config;

use DOMDocument;
use Magebit\Documentation\Model\Config\Converter;
use PHPUnit\Framework\TestCase;

class ConverterTest extends TestCase
{
    /**
     * @var Converter
     */
    private Converter $converter;

    protected function setUp(): void
    {
        $this->converter = new Converter();
    }

    public function testConvertsModuleMetadataAndSections(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A" title="Vendor A" sortOrder="10" icon="Vendor_A::images/i.svg">
                <documentation name="Guide" path="Docs" sortOrder="20"/>
             </module>'
        ));

        $this->assertSame('Vendor A', $result['Vendor_A']['title']);
        $this->assertSame(10, $result['Vendor_A']['sortOrder']);
        $this->assertSame('Vendor_A::images/i.svg', $result['Vendor_A']['icon']);
        $this->assertSame(
            [['name' => 'Guide', 'path' => 'Docs', 'acl' => null, 'sortOrder' => 20, 'isChangelog' => false]],
            $result['Vendor_A']['sections']
        );
    }

    public function testAppliesDefaultsForOmittedAttributes(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A"><documentation name="Guide" path="Docs"/></module>'
        ));

        $this->assertSame('', $result['Vendor_A']['title']);
        $this->assertSame(100, $result['Vendor_A']['sortOrder']);
        $this->assertNull($result['Vendor_A']['icon']);
        $this->assertSame(100, $result['Vendor_A']['sections'][0]['sortOrder']);
        $this->assertNull($result['Vendor_A']['sections'][0]['acl']);
    }

    public function testChangelogBecomesAnOrdinarySection(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A"><changelog path="CHANGELOG.md" acl="Vendor_A::secret"/></module>'
        ));

        $this->assertSame(
            [[
                'name' => 'Changelog',
                'path' => 'CHANGELOG.md',
                'acl' => 'Vendor_A::secret',
                'sortOrder' => 1000,
                'isChangelog' => true,
            ]],
            $result['Vendor_A']['sections']
        );
    }

    public function testChangelogAttributesOverrideTheDefaultNameAndSortOrder(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A"><changelog name="Release notes" path="CHANGELOG.md" sortOrder="5"/></module>'
        ));

        $this->assertSame(
            [[
                'name' => 'Release notes',
                'path' => 'CHANGELOG.md',
                'acl' => null,
                'sortOrder' => 5,
                'isChangelog' => true,
            ]],
            $result['Vendor_A']['sections']
        );
    }

    public function testMergesRepeatedModuleDeclarations(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A" title="First"><documentation name="Guide" path="Docs"/></module>'
            . '<module name="Vendor_A" sortOrder="5"><documentation name="Api" path="Docs/Api"/></module>'
        ));

        $this->assertSame('First', $result['Vendor_A']['title']);
        $this->assertSame(5, $result['Vendor_A']['sortOrder']);
        $this->assertSame(['Guide', 'Api'], array_column($result['Vendor_A']['sections'], 'name'));
    }

    public function testMergesModuleDeclarationsThatAreNotAdjacent(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A" title="First" sortOrder="10"><documentation name="Guide" path="Docs"/></module>'
            . '<module name="Vendor_B" title="Other"><documentation name="Manual" path="Docs"/></module>'
            . '<module name="Vendor_A" sortOrder="5"><documentation name="Api" path="Docs/Api"/></module>'
        ));

        $this->assertSame(['Vendor_A', 'Vendor_B'], array_keys($result));
        $this->assertSame(5, $result['Vendor_A']['sortOrder']);
        $this->assertSame(['Guide', 'Api'], array_column($result['Vendor_A']['sections'], 'name'));
        $this->assertSame('Other', $result['Vendor_B']['title']);
        $this->assertSame(100, $result['Vendor_B']['sortOrder']);
        $this->assertSame(['Manual'], array_column($result['Vendor_B']['sections'], 'name'));
    }

    public function testLaterDeclarationsOverrideTitleIconAndSortOrder(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A" title="First" sortOrder="10" icon="Vendor_A::images/first.svg"/>'
            . '<module name="Vendor_A" title="Second" sortOrder="20" icon="Vendor_A::images/second.svg"/>'
            . '<module name="Vendor_A"/>'
        ));

        $this->assertSame('Second', $result['Vendor_A']['title']);
        $this->assertSame(20, $result['Vendor_A']['sortOrder']);
        $this->assertSame('Vendor_A::images/second.svg', $result['Vendor_A']['icon']);
    }

    public function testLastSectionWithTheSameNameWins(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A">
                <documentation name="Guide" path="Docs"/>
                <documentation name="Guide" path="Docs/Override"/>
             </module>'
        ));

        $this->assertCount(1, $result['Vendor_A']['sections']);
        $this->assertSame('Docs/Override', $result['Vendor_A']['sections'][0]['path']);
    }

    public function testDeduplicatedSectionKeepsThePositionOfTheFirstDeclaration(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A">
                <documentation name="Guide" path="Docs" sortOrder="10" acl="Vendor_A::guide"/>
                <documentation name="Api" path="Docs/Api"/>
                <documentation name="Guide" path="Docs/Override" sortOrder="20"/>
             </module>'
        ));

        $this->assertSame(['Guide', 'Api'], array_column($result['Vendor_A']['sections'], 'name'));
        $this->assertSame(
            ['name' => 'Guide', 'path' => 'Docs/Override', 'acl' => null, 'sortOrder' => 20, 'isChangelog' => false],
            $result['Vendor_A']['sections'][0]
        );
    }

    public function testSkipsEntriesMissingRequiredAttributes(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name=""><documentation name="Guide" path="Docs"/></module>'
            . '<other name="Vendor_X"><documentation name="Guide" path="Docs"/></other>'
            . '<module name="Vendor_A"><documentation name="" path="Docs"/>'
            . '<documentation name="Guide" path=""/><changelog path=""/>'
            . '<other name="Guide" path="Docs"/></module>'
        ));

        $this->assertArrayNotHasKey('', $result);
        $this->assertArrayNotHasKey('Vendor_X', $result);
        $this->assertSame(['Vendor_A'], array_keys($result));
        $this->assertSame([], $result['Vendor_A']['sections']);
    }

    public function testSectionsKeepTheirDocumentOrder(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A">
                <changelog path="CHANGELOG.md"/>
                <documentation name="Guide" path="Docs"/>
             </module>'
        ));

        $this->assertSame(['Changelog', 'Guide'], array_column($result['Vendor_A']['sections'], 'name'));
    }

    public function testDocumentOrderDecidesANameCollisionBetweenAChangelogAndADocumentation(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A">
                <changelog path="CHANGELOG.md"/>
                <documentation name="Changelog" path="Docs/Changelog"/>
             </module>'
        ));

        $this->assertCount(1, $result['Vendor_A']['sections']);
        $this->assertSame('Docs/Changelog', $result['Vendor_A']['sections'][0]['path']);
        $this->assertFalse($result['Vendor_A']['sections'][0]['isChangelog']);
    }

    public function testTreatsAZeroAttributeValueAsPresent(): void
    {
        $result = $this->converter->convert($this->dom(
            '<module name="Vendor_A" title="0" icon="0">'
            . '<documentation name="Guide" path="Docs" acl="0"/>'
            . '<changelog name="0" path="CHANGELOG.md" acl="0"/>'
            . '</module>'
        ));

        $this->assertSame('0', $result['Vendor_A']['title']);
        $this->assertSame('0', $result['Vendor_A']['icon']);
        $this->assertSame('0', $result['Vendor_A']['sections'][0]['acl']);
        $this->assertSame('0', $result['Vendor_A']['sections'][1]['name']);
        $this->assertSame('0', $result['Vendor_A']['sections'][1]['acl']);
    }

    /**
     * @param string $modulesXml
     * @return DOMDocument
     */
    private function dom(string $modulesXml): DOMDocument
    {
        $dom = new DOMDocument();
        $dom->loadXML('<?xml version="1.0"?><config>' . $modulesXml . '</config>');

        return $dom;
    }
}
