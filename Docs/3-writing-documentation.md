# Writing documentation

Pages are plain Markdown files. The folder they sit in becomes the tree, and the file name decides
both the label and the order.

## Folder and file names

```
Docs/
├── index.md              → Overview, always first
├── 1-getting-started.md  → Getting Started
├── 2-configuration.md    → Configuration
└── advanced/             → Advanced
    ├── index.md          → Overview
    └── 1-api.md          → Api
```

- A leading number followed by `-` or `_` sets the order and is stripped from the label.
- `-` and `_` become spaces, and every word is capitalised.
- `index.md` and `readme.md` are the overview of their folder: labelled **Overview**, sorted first.
- Sub-folders become collapsible categories, named by the same rules, up to ten levels deep.
- Anything that is not a `.md` file is not a page. Images still work — see below.

## Front matter

A YAML block at the very top of a file overrides what the file name decided.

```markdown
---
title: Getting started with orders
order: 20
---

# Getting started
```

| Key | Type | Effect |
|-----|------|--------|
| `title` | string | Replaces the label taken from the file name. A blank value is ignored. |
| `order` | integer | Replaces the number in front of the file name. |

`order` has to be an **unquoted integer**. `order: "20"` is a string, and a string is rejected — the
page keeps the order its file name gave it and a warning naming the key and the file goes to the
system log. So write this:

```markdown
---
order: 20
---
```

and never this:

```markdown
---
order: "20"
---
```

Only the first 8 KB of a file is read looking for front matter, so the block has to be at the top.

## Formatting

CommonMark with GitHub Flavored Markdown: headings, lists, tables, task lists, strikethrough and
autolinks all work. Every heading gets a link of its own, and the headings on a page fill the
**On this page** panel beside the text.

Raw HTML written into a page is stripped rather than rendered, and links using an unsafe scheme are
dropped.

## Linking to another page

A relative link that ends in `.md` is rewritten to point at that page inside the viewer.

```markdown
See [registering documentation](2-registering-documentation.md).
```

A relative link **without** an extension is left exactly as it is — `[see](configuration)` is not
turned into `configuration.md`. Add the extension.

Absolute URLs, `mailto:` links and plain `#anchors` are passed through untouched.

## Images

Images are served by an admin controller straight off disk, so there is no static content deploy to
run and no `pub/media` copy to make.

```markdown
![How it fits together](images/how-it-fits-together.svg)
```

The path is resolved relative to the page, and the file has to stay **inside the section folder**. A
path pointing above it with `..` is refused with a 404. Allowed types are `png`, `jpg`, `jpeg`, `gif`,
`svg` and `webp`, up to 8 MB each. An SVG is served under a strict content security policy, so keep it
self-contained — anything it tries to pull in from elsewhere will not load.

## Code blocks

Fenced blocks are highlighted in the browser. Give the fence a language name:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

use Magebit\Documentation\Api\SearchIndexInterface;

class DocumentationSearch
{
    public function __construct(
        private readonly SearchIndexInterface $searchIndex
    ) {
    }

    /**
     * Page titles matching a query, best match first.
     *
     * @param string $query
     * @return list<string>
     */
    public function titles(string $query): array
    {
        return array_map(
            static fn ($hit): string => $hit->getTitle(),
            $this->searchIndex->search($query, 5)
        );
    }
}
```

The common languages — php, javascript, json, xml, yaml, sql, bash, css, diff, ini, markdown and a few
dozen more — are built in. `dockerfile`, `nginx` and `twig` ship as separate files and are fetched the
first time a page needs them.

A language nothing knows is shown as plain text and a warning appears in the browser console. Add it
under **Extra Languages** in the configuration if you want it loaded up front.

To show a fenced block inside a fenced block, wrap the outer one in four backticks:

````markdown
```php
echo 'this stays inside the outer block';
```
````
