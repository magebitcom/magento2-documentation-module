# Registering Documentation

To register documentation for your module, create an `etc/documentation.xml` file.

## Basic Example

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magebit_Documentation:etc/documentation.xsd">
    <module name="Vendor_Module" title="My Module" sortOrder="10" icon="Vendor_Module::images/icon.svg">
        <documentation name="Feature Name" path="Docs/Feature" sortOrder="10"/>
    </module>
</config>
```

## Module Attributes

| Attribute | Required | Description |
|-----------|----------|-------------|
| `name` | Yes | Module name (e.g., `Vendor_Module`) |
| `title` | No | Display title shown in sidebar (defaults to module name) |
| `sortOrder` | No | Sort order for module position (lower = higher, default: 100) |
| `icon` | No | Path to icon image/SVG (e.g., `Vendor_Module::images/icon.svg`) |

## Documentation Attributes

| Attribute | Required | Description |
|-----------|----------|-------------|
| `name` | Yes | Display name shown in the sidebar |
| `path` | Yes | Path to documentation folder (supports multiple formats) |
| `acl` | No | ACL resource ID to restrict access |
| `sortOrder` | No | Sort order within module (lower = higher, default: 100) |

## Path Formats

The `path` attribute supports multiple formats:

### Relative Path
```xml
<documentation name="Getting Started" path="Docs/GettingStarted"/>
```
Relative to the current module's directory.

### Module Path (Recommended)
```xml
<documentation name="Analytics" path="Magebit_ChatbotAnalytics::Docs"/>
```
Format: `ModuleName::path/to/docs`

This explicitly references another module's documentation directory.

### Relative Sibling Path
```xml
<documentation name="Other Module" path="../OtherModule/Docs"/>
```
Relative filesystem path (use sparingly).

## Multiple Features

You can register multiple documentation sections with custom ordering:

```xml
<module name="Vendor_Module" title="My Custom Module" sortOrder="50">
    <documentation name="Getting Started" path="Docs/GettingStarted" sortOrder="10"/>
    <documentation name="Configuration" path="Docs/Config" sortOrder="20"/>
    <documentation name="API Reference" path="Docs/Api" sortOrder="30"/>
</module>
```

## Registering Sub-Module Documentation

Sub-modules can add documentation to a parent module:

```xml
<!-- In Vendor_SubModule/etc/documentation.xml -->
<module name="Vendor_ParentModule">
    <documentation name="Sub Module Feature" path="Vendor_SubModule::Docs" sortOrder="40"/>
</module>
```

This adds "Sub Module Feature" to the "Parent Module" documentation section.

## Module Icon

Display a custom icon next to the module name:

```xml
<module name="Vendor_Module" icon="Vendor_Module::images/docs-icon.svg">
    <documentation name="Guide" path="Docs"/>
</module>
```

Place your icon file at `view/adminhtml/web/images/docs-icon.svg`.

## ACL Protection

Restrict documentation visibility using ACL:

```xml
<documentation 
    name="Admin Guide" 
    path="Docs/Admin" 
    acl="Vendor_Module::admin_documentation"/>
```

Users without the specified ACL permission will not see this documentation.
