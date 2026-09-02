# Vendored mermaid

`mermaid.min.js` is the official `dist/mermaid.min.js` from the `mermaid` npm package, wrapped in one
extra line at the top and one at the bottom. The wrapper passes an undefined `define` into the bundle,
because two libraries inside it (fastdom and fastdom-promised) look for RequireJS before CommonJS and
would otherwise register themselves as AMD modules and leave mermaid without them.

To update: download the new `dist/mermaid.min.js`, put it between the same two lines, then update the
version below and the inner-file checksum in `.github/workflows/ci.yml`.

Version: 11.17.2
