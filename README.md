# Magebit Documentation Module

A Magento 2 module that provides a centralized documentation viewer in the admin panel. View, search, and navigate module documentation.

## Installation

### Via Composer

```bash
composer require magebitcom/module-documentation
bin/magento module:enable Magebit_Documentation
bin/magento setup:upgrade
```

## Requirements

- PHP 8.1 or higher
- Magento 2.4+

## Quick Start

### 1. Create Documentation Files

Create a `Docs/` folder in your module with Markdown files:

```
app/code/Vendor/YourModule/
├── Docs/
│   ├── 1-getting-started.md
│   ├── 2-configuration.md
│   └── 3-api-reference.md
├── etc/
│   └── documentation.xml
└── ...
```

### 2. Register Documentation

Create `etc/documentation.xml` in your module:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magebit_Documentation:etc/documentation.xsd">
    <module name="Vendor_YourModule" title="Your Module" sortOrder="10">
        <documentation name="Getting Started" path="Docs" sortOrder="10"/>
    </module>
</config>
```

### 3. Clear Cache

```bash
bin/magento cache:flush
```

### 4. View Documentation

Navigate to **System → Documentation** in the Magento admin panel.

## Configuration

### Module Attributes

| Attribute | Required | Description |
|-----------|----------|-------------|
| `name` | Yes | Module name (e.g., `Vendor_Module`) |
| `title` | No | Display title in sidebar (defaults to module name) |
| `sortOrder` | No | Module position (lower = higher, default: 100) |
| `icon` | No | Path to icon (e.g., `Vendor_Module::images/icon.svg`) |

### Documentation Attributes

| Attribute | Required | Description |
|-----------|----------|-------------|
| `name` | Yes | Display name in sidebar |
| `path` | Yes | Path to documentation folder |
| `acl` | No | ACL resource to restrict access |
| `sortOrder` | No | Position within module (lower = higher, default: 100) |

## Path Formats

### Relative Path
```xml
<documentation name="Guide" path="Docs/Guide"/>
```
Relative to the current module directory.

### Module Path (Recommended)
```xml
<documentation name="API" path="Vendor_Module::Docs/Api"/>
```
Format: `ModuleName::path/to/docs`

### Multiple Documentation Sections
```xml
<module name="Vendor_Module" title="My Module" sortOrder="10">
    <documentation name="Getting Started" path="Docs/GettingStarted" sortOrder="10"/>
    <documentation name="Configuration" path="Docs/Config" sortOrder="20"/>
    <documentation name="API Reference" path="Docs/Api" sortOrder="30"/>
</module>
```

## Advanced Features

### ACL Protection

Restrict documentation visibility:

```xml
<documentation 
    name="Admin Guide" 
    path="Docs/Admin" 
    acl="Vendor_Module::admin_documentation"/>
```

Define the ACL resource in `etc/acl.xml`:

```xml
<acl>
    <resources>
        <resource id="Magento_Backend::admin">
            <resource id="Vendor_Module::admin_documentation" title="Admin Documentation"/>
        </resource>
    </resources>
</acl>
```

### Custom Module Icons

Add visual branding with custom icons:

```xml
<module name="Vendor_Module" icon="Vendor_Module::images/module-icon.svg">
    <documentation name="Guide" path="Docs"/>
</module>
```

Place your SVG icon at: `view/adminhtml/web/images/module-icon.svg`

### File Naming Convention

Use numeric prefixes for ordered navigation:

```
Docs/
├── 1-introduction.md       (appears first)
├── 2-installation.md       (appears second)
├── 3-configuration.md      (appears third)
└── advanced/
    ├── 1-api.md
    └── 2-troubleshooting.md
```

## Markdown Support

The module uses [CommonMark](https://commonmark.org/) with GitHub Flavored Markdown extensions.

### Supported Features

- **Headers** (`#`, `##`, `###`, etc.)
- **Bold** (`**text**`) and *Italic* (`*text*`)
- **Lists** (ordered and unordered)
- **Links** (`[text](url)`)
- **Images** (`![alt](url)`)
- **Code blocks** (with syntax highlighting)
- **Tables**
- **Blockquotes** (`>`)
- **Horizontal rules** (`---`)
- **Task lists** (`- [ ]` / `- [x]`)
- **Strikethrough** (`~~text~~`)

### Example Markdown

```markdown
# Getting Started

## Installation

Follow these steps:

1. Install the module
2. Enable it
3. Clear cache

## Code Example

```php
$documentation = $this->documentationProvider->getDocumentation('Vendor_Module');
```

## Important Note

> Always clear cache after modifying documentation.xml
```

## API

### DocumentationProviderInterface

```php
<?php

namespace Magebit\Documentation\Api;

interface DocumentationProviderInterface
{
    /**
     * Get all registered documentation
     *
     * @return array
     */
    public function getDocumentation(): array;

    /**
     * Get documentation for specific module
     *
     * @param string $moduleName
     * @return array|null
     */
    public function getModuleDocumentation(string $moduleName): ?array;
}
```

### SearchServiceInterface

```php
<?php

namespace Magebit\Documentation\Api;

interface SearchServiceInterface
{
    /**
     * Search documentation
     *
     * @param string $query
     * @return \Magebit\Documentation\Api\Data\SearchResultInterface
     */
    public function search(string $query): SearchResultInterface;
}
```

## Permissions

Grant access to documentation in **System → Permissions → User Roles**:

- **View Documentation**: `Magebit_Documentation::view`

## Troubleshooting

### Documentation Not Appearing

1. Check that `etc/documentation.xml` is valid
2. Clear cache: `bin/magento cache:flush`
3. Verify the module is enabled: `bin/magento module:status Magebit_Documentation`
4. Check file permissions on `Docs/` folder

### Icons Not Displaying

1. Verify icon path in `documentation.xml`
2. Check that SVG file exists at the specified location
3. Clear static content: `bin/magento setup:static-content:deploy`
