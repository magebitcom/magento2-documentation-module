# Registering documentation

A module joins the viewer by shipping an `etc/documentation.xml`. Nothing else is needed — no setup
script, no database row.

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magebit_Documentation:etc/documentation.xsd">
    <module name="Vendor_Module" title="My Module" sortOrder="10" icon="Vendor_Module::images/icon.svg">
        <documentation name="Guide" path="Docs" sortOrder="10"/>
        <changelog path="CHANGELOG.md"/>
    </module>
</config>
```

Run `bin/magento cache:clean config` afterwards. The merged `documentation.xml` lives in the config
cache, so that is the command that picks up a change to this file — cleaning `magebit_documentation`
alone will not. It clears the documentation tree and the search index too, because both are tagged
with the config cache.

Once the section is registered, adding or renaming a page is a smaller change and
`bin/magento cache:clean magebit_documentation` covers it.

## The `<module>` element

Groups sections under one heading in the sidebar.

| Attribute | Required | Description |
|-----------|----------|-------------|
| `name` | yes | Module the sections belong to, e.g. `Vendor_Module`. |
| `title` | no | Heading shown in the sidebar. Falls back to the module name. |
| `sortOrder` | no | Position among modules, lower first. Default `100`; ties break on title. |
| `icon` | no | View-asset path of an icon shown beside the module title **in the documentation sidebar**, e.g. `Vendor_Module::images/icon.svg`. Not the admin menu icon. |

## The `<documentation>` element

One folder of Markdown pages.

| Attribute | Required | Description |
|-----------|----------|-------------|
| `name` | yes | Section label in the sidebar. |
| `path` | yes | Folder holding the Markdown files. |
| `acl` | no | ACL resource an admin must hold to see this section. |
| `sortOrder` | no | Position among the module's sections, lower first. Default `100`. |

## The `<changelog>` element

Points at a single Markdown **file** instead of a folder, so the `CHANGELOG.md` already sitting in
your module root can be shown without moving or copying it.

```xml
<module name="Vendor_Module" title="My Module">
    <documentation name="Guide" path="Docs"/>
    <changelog name="Release notes" path="CHANGELOG.md" sortOrder="900"/>
</module>
```

| Attribute | Required | Description |
|-----------|----------|-------------|
| `path` | yes | Markdown file, relative to the module or written as `Vendor_Other::CHANGELOG.md`. |
| `name` | no | Section label. Default `Changelog`. |
| `acl` | no | ACL resource an admin must hold to see this section. |
| `sortOrder` | no | Position among the module's sections. Default `1000`, so it lands last. |

## How `path` is resolved

A bare path is resolved inside the module that declared it. Prefixing it with a module name resolves
it inside that module instead.

```xml
<documentation name="Guide" path="Docs"/>
<documentation name="API"   path="Docs/Api"/>
<documentation name="Notes" path="Vendor_Other::Docs"/>
```

A path may never leave the module it resolves against. `../OtherModule/Docs` is refused, and so is any
`..` that would climb out of it — use the `Vendor_Other::` form for another module's folder.

A section whose path does not resolve is dropped from the tree with no visible error. That is exactly
what the validate command is for:

```bash
bin/magento magebit:documentation:validate
```

A folder that resolves but holds no Markdown is dropped too, and validate passes on that one — it is
a real folder. Run `bin/magento magebit:documentation:list`: a section missing from that table is an
empty folder.

## Adding sections to another module's heading

Give the parent module's name and your section joins its group. Nothing has to change in the parent.

```xml
<!-- Vendor_SubModule/etc/documentation.xml -->
<module name="Vendor_Module">
    <documentation name="Sub Module" path="Vendor_SubModule::Docs" sortOrder="40"/>
</module>
```

Two sections declared with the same `name` under the same module are one section: the later
declaration wins.

## Restricting a section

Name an ACL resource and the section disappears for admins who do not hold it — from the sidebar,
from search, and from the page and image controllers.

```xml
<documentation name="Internal" path="Docs/Internal" acl="Vendor_Module::internal_docs"/>
```

Declare the resource in your own `etc/acl.xml`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::admin">
                <resource id="Magento_Backend::system">
                    <resource id="Vendor_Module::internal_docs" title="Internal Documentation"/>
                </resource>
            </resource>
        </resources>
    </acl>
</config>
```

A misspelled resource name is worse than it looks: Magento falls back to the role's blanket
permission, so the section stays visible to a full-access administrator and quietly disappears for
every restricted role. `magebit:documentation:validate` fails on that too.

Access to the viewer as a whole is a separate resource, `Magebit_Documentation::documentation`, listed
as **View Documentation** under **System → Permissions → User Roles**.

## Settings

**Stores → Configuration → Magebit → Documentation** has three fields.

| Field | Description |
|-------|-------------|
| Syntax Theme | Colour theme used for code blocks. |
| Extra Languages | Extra highlight.js language bundles loaded on every documentation page. |
| Enable Search | Shows or hides the search box above the tree. |
