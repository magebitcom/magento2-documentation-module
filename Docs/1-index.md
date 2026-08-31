# Documentation

This module gives every Magento module one place to put its documentation: the **Docs** entry in the
admin menu. Write Markdown, point an `etc/documentation.xml` at the folder, and the pages show up —
rendered, searchable, and hidden from admins who are not allowed to see them.

![How it fits together](images/how-it-fits-together.svg)

The page you are reading now is the module documenting itself, so everything described here is
already in use on this screen.

## What you get

- **Markdown pages**, with tables, task lists, code blocks and heading links.
- **A tree in the sidebar**, built from your folder names and file names.
- **Search**, over titles, headings and body text, across every section you may see.
- **Per-section permissions**, so an internal runbook can sit next to a public guide.
- **A changelog section**, for the `CHANGELOG.md` you already keep in the module root.

## Getting started

1. Create a `Docs/` folder in your module and put a Markdown file in it.
2. Add an `etc/documentation.xml` naming that folder — see [Registering documentation](2-registering-documentation.md).
3. Run `bin/magento cache:clean magebit_documentation`.

Then read [Writing documentation](3-writing-documentation.md) for the file naming rules, front matter,
links and images.

## Checking your setup

Two commands come with the module.

```bash
bin/magento magebit:documentation:list
bin/magento magebit:documentation:validate
```

`list` prints every registered section with its resolved folder and its page count, which is the
quickest way to see whether your path landed where you thought it would.

`validate` exits non-zero when a configured path does not resolve, or when an `acl` attribute names a
resource that no `acl.xml` declares. Both faults are silent in the browser — the section simply never
appears — so put `validate` in your build.

## Reading the tree from your own code

The tree, the pages and the search index are all available as services. Everything they hand back is
already filtered to what the signed-in admin may see.

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Block\Adminhtml;

use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class DocumentationLinks implements ArgumentInterface
{
    public function __construct(
        private readonly DocumentationTreeInterface $tree
    ) {
    }

    /**
     * Section names of one module, empty when the admin may not see any of them.
     *
     * @param string $moduleName
     * @return list<string>
     */
    public function getSectionNames(string $moduleName): array
    {
        $module = $this->tree->get()[$moduleName] ?? null;

        if ($module === null) {
            return [];
        }

        return array_map(
            static fn ($section): string => $section->getName(),
            $module->getSections()
        );
    }
}
```

The full list of interfaces is in the module's `README.md`.
