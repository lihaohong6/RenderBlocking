# RenderBlocking
A MediaWiki extension that allows interface administrators to specify render-blocking CSS and JavaScript.

This extension can be used to heavily modify a wiki's skin without introducing [FOUC](https://en.wikipedia.org/wiki/Flash_of_unstyled_content). For example, interface administrators can completely overhaul the page's DOM with JavaScript before the page is displayed.

## Usage
For site-wide render-blocking CSS and JavaScript, put them in
- MediaWiki:Renderblocking.css
- MediaWiki:Renderblocking.js

Note that MediaWiki:Common.css is already render-blocking, so the CSS page is unnecessary and is provided only for the sake of completion.

For skin-specific render-blocking CSS and JavaScript, put them in
- MediaWiki:Renderblocking-skinname.css
- MediaWiki:Renderblocking-skinname.js

In addition to the pages above, the following pages can be used to specify more pages to be loaded:
- MediaWiki:Renderblocking-pages
- MediaWiki:Renderblocking-skinname-pages

The format is as follows. All pages must be in the MediaWiki namespace.
```
; Any line not starting with a * will be treated as a comment
* Pagename.css
* Pagename.js
```

## Caveats
Do not assume anything besides vanilla JavaScript is available. In particular, do not attempt to use MediaWiki libraries (i.e. `mw`) and jQuery (i.e. `$`): they could work under special circumstances but are not guaranteed to be available.
