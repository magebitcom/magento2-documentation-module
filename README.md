# Magebit_Documentation

A Magento 2 admin documentation viewer. Any module can drop Markdown files into a folder, register
that folder in `etc/documentation.xml`, and the pages appear under **Docs** in the admin menu —
rendered, searchable, and permission-aware.

## Requirements

- PHP 8.1 or newer
- Magento 2.4 (`magento/framework` ^103.0)

## Installation

```bash
composer require magebitcom/module-documentation
bin/magento module:enable Magebit_Documentation
bin/magento setup:upgrade
bin/magento cache:flush
```

Open **Docs** in the admin menu. The menu item is top level, not nested under System.

## Quick start

Put Markdown files in your module and point a `documentation.xml` at them.

```
app/code/Vendor/Module/
├── Docs/
│   ├── 1-getting-started.md
│   ├── 2-configuration.md
│   └── images/
│       └── flow.png
└── etc/
    └── documentation.xml
```

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magebit_Documentation:etc/documentation.xsd">
    <module name="Vendor_Module" title="My Module" sortOrder="10">
        <documentation name="Guide" path="Docs" sortOrder="10"/>
    </module>
</config>
```

Then clear the right cache. **After a `documentation.xml` change, run `bin/magento cache:clean config`** —
the merged configuration is stored in the config cache, so cleaning `magebit_documentation` alone will
not pick it up. After adding, renaming or removing a Markdown *file*,
`bin/magento cache:clean magebit_documentation` is enough. See [Caching](#caching) for the whole
picture.

## documentation.xml

### `<module>`

| Attribute | Required | Description |
|---|---|---|
| `name` | yes | Module the sections belong to, e.g. `Vendor_Module`. Groups the sidebar. |
| `title` | no | Label in the sidebar. Falls back to the module name. |
| `sortOrder` | no | Position among modules, lower first. Default `100`; ties break on title. |
| `icon` | no | View-asset path of an icon shown beside the module title **in the documentation sidebar**, e.g. `Vendor_Module::images/icon.svg`. Not the admin menu icon. |

### `<documentation>`

One folder of Markdown pages.

| Attribute | Required | Description |
|---|---|---|
| `name` | yes | Section label in the sidebar. Also the key that merges declarations. |
| `path` | yes | Folder holding the Markdown files. See [Paths](#paths). |
| `acl` | no | ACL resource an admin must hold to see the section. |
| `sortOrder` | no | Position among the module's sections, lower first. Default `100`. |

### `<changelog>`

One Markdown *file* rather than a folder — for a `CHANGELOG.md` that already sits in the module root.

```xml
<module name="Vendor_Module" title="My Module">
    <documentation name="Guide" path="Docs"/>
    <changelog path="CHANGELOG.md"/>
</module>
```

| Attribute | Required | Description |
|---|---|---|
| `path` | yes | Markdown file, relative to the module or written as `Vendor_Other::CHANGELOG.md`. |
| `name` | no | Section label. Default `Changelog`. |
| `acl` | no | ACL resource an admin must hold to see the section. |
| `sortOrder` | no | Position among the module's sections. Default `1000`, so it lands last. |

### Paths

`path` is resolved against the module that declared it, or against another module when it is written
as `Vendor_Other::Docs`:

```xml
<documentation name="Guide" path="Docs"/>
<documentation name="API"   path="Docs/Api"/>
<documentation name="Notes" path="Vendor_Other::Docs"/>
```

A path may not leave the module it resolves against — `../OtherModule/Docs` is refused, and so is any
`..` that would climb out. Use the `Vendor_Other::` form instead.

A section whose path does not resolve is dropped from the tree without an error, which is what
`magebit:documentation:validate` catches. A section whose path resolves but whose folder holds no
Markdown is dropped too; validate passes on that one, so check `magebit:documentation:list` — a
section missing from that table has an empty folder.

### Adding sections to another module's group

A satellite module can file its own pages under the parent's heading by declaring the parent's name:

```xml
<!-- Vendor_SubModule/etc/documentation.xml -->
<module name="Vendor_Module">
    <documentation name="Sub Module" path="Vendor_SubModule::Docs" sortOrder="40"/>
</module>
```

## Writing pages

### File names

- A numeric prefix sets the order and is stripped from the label: `1-getting-started.md` → *Getting Started*.
- `-` and `_` become spaces, and each word is capitalised.
- `index.md` and `readme.md` are the section or folder overview: they are labelled **Overview** and sort first.
- Sub-folders become collapsible categories, named by the same rules. Nesting stops at 10 levels.
- Files that are not `.md` are not pages. They can still be linked to as images.

### Front matter

An optional YAML block at the very top of a page overrides the two things the file name decides:

```markdown
---
title: Getting started with orders
order: 20
---

# Getting started
```

| Key | Type | Effect |
|---|---|---|
| `title` | string | Replaces the label derived from the file name. Blank values are ignored. |
| `order` | integer | Replaces the numeric file-name prefix. |

`order` must be an **unquoted integer**. `order: "20"` is a string, and a string is rejected: the page
keeps its file-name order and a warning naming the key and the file is written to the system log. Only
the first 8 KB of a file is read for front matter, so keep the block at the top.

### Markdown

CommonMark with GitHub Flavored Markdown — tables, task lists, strikethrough, autolinks — plus heading
permalinks, footnotes and a table of contents. Raw HTML in a page is stripped, and unsafe link schemes
are refused. Links that leave the site open in a new tab and carry a small arrow.

Headings automatically fill the **On this page** panel beside the content, and the panel follows the
reader down the page.

GitHub-style callouts work as they do on GitHub — a blockquote opening with `[!NOTE]`, `[!TIP]`,
`[!IMPORTANT]`, `[!WARNING]` or `[!CAUTION]` becomes a coloured box with a title:

```markdown
> [!WARNING]
> Cleaning the wrong cache is the most common mistake.
```

### Links between pages

Relative links ending in `.md` are rewritten to point at the other page:

```markdown
See [configuration](2-configuration.md) and [the API](advanced/1-api.md).
```

A relative link **without** an extension is left exactly as written — `[see](configuration)` is not
turned into `configuration.md`. Add the extension.

Absolute URLs, anchors and `mailto:` links pass through untouched.

### Images

Images are served by an admin controller, not by static content, so they do not need a deploy:

```markdown
![Order flow](images/flow.png)
```

The path is resolved relative to the page and must stay **inside the section folder** — an image
outside it, or above it via `..`, returns a 404. Allowed types are `png`, `jpg`, `jpeg`, `gif`, `svg`
and `webp`, up to 8 MB. SVGs are served with a restrictive `Content-Security-Policy`, so an SVG that
pulls in external resources will not render them.

The module `icon` from `documentation.xml` is the other kind of image and follows the opposite rules:
it is an ordinary static view asset, resolved through `getViewFileUrl()`, so it lives under
`view/adminhtml/web/` and **does** need `bin/magento setup:static-content:deploy` in production mode.
Images inside your pages need no deploy; the sidebar icon does.

### Code blocks

Fenced blocks are highlighted in the browser by a bundled copy of highlight.js. The common languages
(php, javascript, json, xml, yaml, sql, bash, css, diff, ini, markdown and more) work out of the box.
`dockerfile`, `nginx` and `twig` ship as separate files and load on demand.

A language the bundle does not know is rendered as plain text and a warning is logged in the browser
console. To have an extra language ready before the first block that needs it, list it under **Extra
Languages** in the configuration.

To show a fenced block *inside* a fenced block, wrap the outer one in four backticks.

### Diagrams

A fenced block with the language `mermaid` is drawn in the browser by a bundled copy of
[Mermaid](https://mermaid.js.org/) — flowcharts, sequence diagrams, class diagrams, Gantt charts and
the rest. Nothing is fetched from a CDN, so it works under the admin Content-Security-Policy. Mermaid is
only loaded on pages that contain a diagram. A diagram Mermaid cannot read keeps its source visible with
a short message above it.

Clicking an image or a drawn diagram opens it full screen; Escape or another click closes it.

## Access control

The whole viewer is gated by `Magebit_Documentation::documentation` ("View Documentation"), which sits
under **System** in **System → Permissions → User Roles**.

Individual sections can require their own resource:

```xml
<documentation name="Internal" path="Docs/Internal" acl="Vendor_Module::internal_docs"/>
```

```xml
<!-- Vendor_Module/etc/acl.xml -->
<resource id="Magento_Backend::admin">
    <resource id="Magento_Backend::system">
        <resource id="Vendor_Module::internal_docs" title="Internal Documentation"/>
    </resource>
</resource>
```

A section an admin may not see is removed from the tree, from search results and from the page and
image controllers. A module left with no visible section disappears entirely.

Name a resource that no `acl.xml` declares and Magento falls back to the role's blanket permission:
the section stays visible to a full-access administrator and vanishes for every restricted role. That
is hard to notice by hand, so `magebit:documentation:validate` checks it for you.

## Configuration

**Stores → Configuration → Magebit → Documentation**

| Field | Path | Description |
|---|---|---|
| Syntax Theme | `magebit_documentation/appearance/highlight_theme` | Colour theme for code blocks. |
| Extra Languages | `magebit_documentation/appearance/extra_languages` | Extra highlight.js bundles loaded on every page. |
| Enable Search | `magebit_documentation/search/enabled` | Shows the search box above the tree. |

## Commands

```bash
bin/magento magebit:documentation:list
```

Prints every registered section with its module, title, resolved absolute path and page count. It
reads the tree unfiltered, because the command line has no admin session and an ACL-filtered tree
would come back empty. A section that does not resolve is already gone from that tree, so it will not
be listed — which is what the second command is for.

```bash
bin/magento magebit:documentation:validate
```

Exits `0` when every configured path resolves and every `acl` attribute names a resource that
`acl.xml` actually declares. Otherwise it prints one line per problem and exits `1`. It reads the
merged `documentation.xml` directly, so it still sees the sections the tree has dropped.

Neither fault announces itself in the browser — a bad path removes the section for everyone, and a
bad `acl` removes it only for restricted roles — so run this in CI.

## Caching

Three separate things are cached, and they are not all cleaned by the same command.

| What | Where it lives | Cleaned by |
|---|---|---|
| The merged `documentation.xml` | the **config** cache type | `cache:clean config` |
| The documentation tree | the `magebit_documentation` cache type, tagged with the config cache | `cache:clean magebit_documentation` **or** `cache:clean config` |
| The search index | the `magebit_documentation` cache type, tagged with the config cache | `cache:clean magebit_documentation` **or** `cache:clean config` |

Page text is not cached at all — it is read from disk on every request, so an edit inside a page is
visible immediately. Search will keep matching the old text until the index is rebuilt.

So, in practice:

```bash
# changed a documentation.xml — this is the one that also covers everything else
bin/magento cache:clean config

# only added, renamed or removed a Markdown file
bin/magento cache:clean magebit_documentation
```

The `magebit_documentation` type is listed as **Documentation** under **System → Cache Management**,
where it can also be switched off.

## Extending

The public contracts live in `Magebit\Documentation\Api`. Take a preference on one to replace an
implementation.

```php
interface DocumentationTreeInterface
{
    /** @return array<string, ModuleDocsInterface> Keyed by module name, ordered by sort order */
    public function get(): array;

    public function getSection(string $moduleName, string $sectionName): ?SectionInterface;

    /** @return array{module: ModuleDocsInterface, section: SectionInterface, page: PageInterface}|null */
    public function getFirst(): ?array;
}

interface PageRepositoryInterface
{
    public function getContent(string $moduleName, string $sectionName, string $relativePath): ?string;

    public function getContentForSection(
        string $moduleName,
        SectionInterface $section,
        string $relativePath
    ): ?string;

    /** @return array<string, mixed> */
    public function getFrontMatter(string $moduleName, string $sectionName, string $relativePath): array;
}

interface SearchIndexInterface
{
    /** @return list<SearchHitInterface> Highest score first */
    public function search(string $query, int $limit = 20): array;
}

interface MarkdownRendererInterface
{
    /** @param array{module:string,section:string,path:string} $context */
    public function render(string $markdown, array $context): string;
}

interface SyntaxHighlighterInterface
{
    public function decorate(string $html): string;
}

interface PathResolverInterface
{
    public function resolveSectionRoot(string $contextModule, string $configuredPath): ?string;

    /** @param list<string> $allowedExtensions Lowercase, without the dot */
    public function resolveFile(string $sectionRoot, string $relativePath, array $allowedExtensions): ?string;

    /** @return array{path: string, fileName: string}|null */
    public function resolveChangelogFile(string $contextModule, string $configuredPath): ?array;
}

interface DirectoryScannerInterface
{
    public function scan(string $absoluteRoot): CategoryInterface;
}
```

`DocumentationTreeInterface`, `PageRepositoryInterface::getContent()` and `SearchIndexInterface` are
all filtered by the current admin's permissions. `PageRepositoryInterface::getContentForSection()` is
not — it takes an already-resolved section, and the caller owns the authorization check.

The data objects returned by these — `ModuleDocsInterface`, `SectionInterface`, `CategoryInterface`,
`PageInterface`, `SearchHitInterface` — are read-only value objects in `Magebit\Documentation\Api\Data`.

Turning a configured or requested path into a disk path happens only in `Model\Path\Resolver` and
`Model\Scanner`; reading the bytes of an already-resolved path, through the injected
`Filesystem\Driver\File`, also happens in `Model\PageRepository`, `Controller\Adminhtml\Asset\Index`
and `Model\Config\Source\ShippedFiles`.

## Screenshots

<img width="2541" height="1226" alt="Screenshot1" src="https://github.com/user-attachments/assets/3f8df35a-5f2f-4084-ad9f-a7295b295bd2" />
<img width="2541" height="1226" alt="Screenshot2" src="https://github.com/user-attachments/assets/f31faa58-368a-4591-b0c5-fc86292f7a6c" />
<img width="2541" height="1226" alt="Screenshot3" src="https://github.com/user-attachments/assets/7e0d369d-aaf3-48cb-ab20-9198e339478d" />


## Troubleshooting

**Nothing changed after editing `documentation.xml`.** You cleaned the wrong cache. That file is
merged into the **config** cache: `bin/magento cache:clean config`.

**A section does not appear.** Run `bin/magento magebit:documentation:validate`. If it passes, the
folder resolved but held no `.md` files, or your role lacks the section's ACL resource.

**Pages are stale after adding a file.** `bin/magento cache:clean magebit_documentation`.

**The sidebar icon does not load.** Unlike page images, it is a static view asset:
`bin/magento setup:static-content:deploy`.

**A front-matter `order` is ignored.** It was quoted. Write `order: 20`, not `order: "20"`.

**An image is broken.** It must sit inside the section folder and end in an allowed extension.

## License

MIT. See [LICENSE](LICENSE).
