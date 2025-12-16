# Writing Documentation

Documentation files use Markdown format with GitHub Flavored Markdown (GFM) support.

## File Structure

```
Docs/
├── index.md              # Main page (displayed as "Overview")
├── getting-started.md    # Additional pages
└── Category/             # Subdirectories become categories
    ├── index.md
    └── advanced.md
```

## File Naming

- `index.md` files are displayed as "Overview"
- Other files: `my-feature.md` → "My Feature"
- Underscores and hyphens are converted to spaces

## Supported Markdown

### Headers

```markdown
# H1 Header
## H2 Header
### H3 Header
```

### Text Formatting

```markdown
**bold text**
*italic text*
`inline code`
```

### Code Blocks

    ```php
    <?php
    echo "Hello World";
    ```

### Lists

```markdown
- Bullet item
- Another item

1. Numbered item
2. Another item
```

### Tables

```markdown
| Column 1 | Column 2 |
|----------|----------|
| Value 1  | Value 2  |
```

### Blockquotes

```markdown
> This is a quote
```

### Links

```markdown
[Link text](https://example.com)
```
