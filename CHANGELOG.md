# Changelog

Every entry below is generated from this module's conventional commit messages.

## [1.1.0](https://github.com/magebitcom/magento2-documentation-module/compare/v1.0.0...v1.1.0) - 2026-09-02

### Features
- Add callouts, footnotes, heading tracking, image zoom and print styles ([e417917](https://github.com/magebitcom/magento2-documentation-module/commit/e4179170dcb4bc00439f696e1325c3b6d672b01e))
- Draw mermaid fences as diagrams with a bundled mermaid.js ([9a483d7](https://github.com/magebitcom/magento2-documentation-module/commit/9a483d7199401c3bc7779bc01a595a4a21f942dc))

### Bug Fixes
- Push the release with a deploy key and make the commit and tag one atomic push ([77bb99f](https://github.com/magebitcom/magento2-documentation-module/commit/77bb99f646c6c9c4cea78f9ab663e99d129ec431))

## [1.0.0](https://github.com/magebitcom/magento2-documentation-module/compare/v0.0.1...v1.0.0) - 2026-08-31

### Features
- Add list and validate commands and rewrite the public documentation ([d905eec](https://github.com/magebitcom/magento2-documentation-module/commit/d905eec62c631bc522eaf7ef3f1469850d183bfc))
- Mask an open book svg into the menu icon and shorten the rail label to Docs ([cfff9be](https://github.com/magebitcom/magento2-documentation-module/commit/cfff9be6a429d616de7516ad5a1973053b4c6fdc))
- Give the documentation menu item its own admin icon ([9e02514](https://github.com/magebitcom/magento2-documentation-module/commit/9e025144b2bb0f98ffec1a50034eca9e61a7df28))
- Rewrite the admin ui with a single-pass tree, toc, keyboard search and copy buttons ([eac8cb1](https://github.com/magebitcom/magento2-documentation-module/commit/eac8cb1818c099987334fc8943d02ad102751ff5))
- Add the page, search and image controllers with their view models ([a09c8dc](https://github.com/magebitcom/magento2-documentation-module/commit/a09c8dce42f039491619097c492e85f03446b48a))
- Search document content and headings instead of file names ([e2ab6ca](https://github.com/magebitcom/magento2-documentation-module/commit/e2ab6ca511bdbc56d35bab9a52c3b881a021ed95))
- Highlight code blocks with the bundled highlight.js and make the theme configurable ([9cf9b83](https://github.com/magebitcom/magento2-documentation-module/commit/9cf9b8379cbacee4a90282bacc4952a73692cfbe))
- Rewrite markdown links and images in the AST instead of the output HTML ([e5f84d6](https://github.com/magebitcom/magento2-documentation-module/commit/e5f84d6ebbc410e2a481a5d2a2433f6bd13c33ac))
- Read page content and front matter behind the ACL-filtered tree ([493237d](https://github.com/magebitcom/magento2-documentation-module/commit/493237da5d5aa31a1a8645e1302d9975d080ea33))
- Let front matter override the title and order a page sorts by ([088e700](https://github.com/magebitcom/magento2-documentation-module/commit/088e700dae61612743af35550faed5dfc4ab432b))
- Build the documentation tree once and cache it before ACL filtering ([bc4241c](https://github.com/magebitcom/magento2-documentation-module/commit/bc4241c2c02119abe2a2fbce6c2ad218c48ee6f8))
- Flatten documentation.xml into typed module and section records ([d5c37c8](https://github.com/magebitcom/magento2-documentation-module/commit/d5c37c88de4fad6ebf133480d8995e88e7a8f2f1))
- Scan documentation folders into an ordered category tree ([d264bd0](https://github.com/magebitcom/magento2-documentation-module/commit/d264bd08bd910bcedb52d1a146e043e91abd1ae6))
- Resolve section paths against the target module and block traversal ([f3bd3e1](https://github.com/magebitcom/magento2-documentation-module/commit/f3bd3e13129300b71fb7063299b3ec2a9eef7252))
- Add immutable data contracts for the documentation tree ([3539e10](https://github.com/magebitcom/magento2-documentation-module/commit/3539e10dcb517d8ae907a56daf777da310d0b9cd))

### Bug Fixes
- Ignore configured highlight languages that no longer ship ([9ef6a71](https://github.com/magebitcom/magento2-documentation-module/commit/9ef6a7192e72e0b6714c758907924def8f8515ca))
- Send validate failures to stderr and correct the cache, icon and cache-type documentation ([defbdfa](https://github.com/magebitcom/magento2-documentation-module/commit/defbdfa53af3fb54a555687997286f852e41d7b4))
- Widen the narrow-width content padding so the h1 permalink stays inside it ([8470a00](https://github.com/magebitcom/magento2-documentation-module/commit/8470a0063b014010a31080859ee1d54be7bd4f8b))
- Announce search result selection and sidebar toggle target to screen readers ([efa616b](https://github.com/magebitcom/magento2-documentation-module/commit/efa616b74fd9e600fbd5ced33cd959b1fa95534d))
- Keep the current page's tree ancestors open after a reload ([3cfd460](https://github.com/magebitcom/magento2-documentation-module/commit/3cfd460bc40025bb2e156f0ffde9dbbb70b5f196))
- Scope the search highlight mark selector to the docs page ([251af13](https://github.com/magebitcom/magento2-documentation-module/commit/251af1301cccf4f6c75c57e4273536a7cb0820d3))
- Darken the muted text colour so it clears the 4.5:1 contrast floor ([3da4171](https://github.com/magebitcom/magento2-documentation-module/commit/3da41714b87ddcb4f7d7e2e3643472592e90e9e9))
- Swap the pale permalink mark for a hover-revealed # that no longer shifts headings ([fddc8b9](https://github.com/magebitcom/magento2-documentation-module/commit/fddc8b91a5b3be7fd8e23f182ff5f033ebfea5fe))
- Build the page with the framework factory so the admin menu block exists ([510ee00](https://github.com/magebitcom/magento2-documentation-module/commit/510ee00179292812c31051911cb4d28b09bc1e34))
- Sandbox served svg documents and cap the size of a served image ([e5cbb6c](https://github.com/magebitcom/magento2-documentation-module/commit/e5cbb6cba4ebac2ed34c6d69945ab4a3dd3afe30))
- Drop front matter and emphasis from indexed text and clean headings before collecting them ([4b5a051](https://github.com/magebitcom/magento2-documentation-module/commit/4b5a05170604c3a98519c5dc0e737d71434a6c9e))
- Load each highlight language on its own request and keep admin comments translatable ([34298b9](https://github.com/magebitcom/magento2-documentation-module/commit/34298b9444867f2a9b0f963b01c7935fb2766741))
- Offer only light themes and fall back when the stored theme no longer ships ([959670c](https://github.com/magebitcom/magento2-documentation-module/commit/959670c62571dfdbbab12c3e20baede1f438aacd))
- Support anchored and encoded documentation links and contain every render failure ([847ab94](https://github.com/magebitcom/magento2-documentation-module/commit/847ab94f67d79043467c3b6106c4dd127bd79831))
- Warn the author when a front matter title or order cannot be used ([fe9ffd9](https://github.com/magebitcom/magento2-documentation-module/commit/fe9ffd96115bf23628d1c1c8464b2e59a08ac354))
- Read front matter behind a byte order mark and always close the file handle ([39da34c](https://github.com/magebitcom/magento2-documentation-module/commit/39da34ccd4aa506b2d73bfa5db1a901e9699ed6b))
- Return the changelog file name from the resolver and cover the missing tree cases ([a7ad127](https://github.com/magebitcom/magento2-documentation-module/commit/a7ad127375d8ac77d176b716c7a124c39e294a25))
- Give the documentation cache its own type and clear it with the config cache ([988a182](https://github.com/magebitcom/magento2-documentation-module/commit/988a1826efed38591e10a29580c62f3fa7435c7a))
- Make module metadata merging last-wins and read sections in document order ([2b1a9be](https://github.com/magebitcom/magento2-documentation-module/commit/2b1a9be9fd5731fceba78feb935ed6ec1fa53b9e))
- Strip only markdown suffixes, cap scan depth and tighten the scanner tests ([bdc9c66](https://github.com/magebitcom/magento2-documentation-module/commit/bdc9c66a521a5251ae7fae4e8dc6a6c317b8d374))
- Tighten the documentation path guard and cover its refusal branches ([2fd26af](https://github.com/magebitcom/magento2-documentation-module/commit/2fd26af9478354f1a47361e110d39205648ec7e4))
- Exclude vendor/docs from phpstan, fix icon/categories docs, cover title fallback ([0dd5996](https://github.com/magebitcom/magento2-documentation-module/commit/0dd5996f536550ed5961739d631c44d581990c72))

### Refactoring
- Rename EnvironmentFactory to EnvironmentBuilder ([2978a42](https://github.com/magebitcom/magento2-documentation-module/commit/2978a42dbe08d56d54aed0f5aa54e538e15e59d9))
- Remove the unused languagePath setting from the highlight loader ([eda23eb](https://github.com/magebitcom/magento2-documentation-module/commit/eda23eb508d64f525bd72fe38fea40032b03a161))
- Drop the last phpcs:ignore from the directory scanner and give it a class docblock ([b8bfce9](https://github.com/magebitcom/magento2-documentation-module/commit/b8bfce9be03dd71c4655bc02f0f3844fa6c69169))
- Drop the dark theme pairing and ship a light only viewer ([64c5f53](https://github.com/magebitcom/magento2-documentation-module/commit/64c5f53093bc1a71ae1c97b90ae73dc799d4b488))
- Split shipped file names on the driver's slash instead of basename ([faf4cb3](https://github.com/magebitcom/magento2-documentation-module/commit/faf4cb3efd91ca9c9262099247145362a57af05d))
- Remove the old implementation and add module static-analysis gates ([664a3c4](https://github.com/magebitcom/magento2-documentation-module/commit/664a3c4b48e8d2d3b1f1a5895692df529878c44f))

### Documentation
- Correct the icon description and say where the file driver is used ([1cefe96](https://github.com/magebitcom/magento2-documentation-module/commit/1cefe9639d905a4d4cca353b2f703a9fe23b0ee2))

## [0.0.1](https://github.com/magebitcom/magento2-documentation-module/releases/tag/v0.0.1) - 2026-02-10

### Bug Fixes
- Package name ([a8f8693](https://github.com/magebitcom/magento2-documentation-module/commit/a8f8693266df0d3cc925d6e7c22693576ac73687))
