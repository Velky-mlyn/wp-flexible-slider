# Mlýn Flexible Slider

A reusable WordPress slider manager plugin.

## Usage

Create sliders under **Sliders** in WordPress administration. Embed a published slider in any shortcode-capable area:

```text
[mlyn_slider id="homepage-hero"]
```

Template usage:

```php
echo mlyn_render_slider( 'homepage-hero' );
```

The **Mlýn slider** block provides the native block-editor equivalent. Choose
a published slider in the block sidebar; rendering continues to use the same
server-side slider implementation as the shortcode and template function.

Optional shortcode overrides are `variant="hero|default"`, `autoplay="true|false"`, and `class="custom-class"`.

Slides can use a custom image, a Media Library video, or linked WordPress content. A linked event inherits its title, first tag, start date, URL, and featured image unless the corresponding slide fields override them.

The linked-content picker searches all published public content that the current administrator may edit, including past calendar events. Search by title/text or exact numeric ID, optionally filter by content type, and use the displayed ID link to verify the item on its WordPress edit screen. Existing links are preserved and identified even if their content later becomes unpublished or is deleted.

For image slides (including linked featured images), expand **Image focal point / crop** and move the point to the area that should stay visible. Wide and narrow previews show how the crop changes with the slider shape. **Center image** resets the point to the center. The crop is saved per slide and does not change the original image or an event's detail banner. Selecting a different image resets the slide crop; existing slides retain their previous 50% / 35% positioning until edited.

## Import and export

Open **Sliders → Import / Export** to move sliders between WordPress sites. The versioned transfer ZIP contains the selected sliders, their settings and ordered slides, plus copies of directly selected images, videos, and posters. Media files are deduplicated by content hash when the same archive is imported again.

Linked posts, pages, and events are not duplicated. They are matched on the destination by post type and slug/path instead of database ID, and unresolved links are reported after import for manual replacement. Internal button URLs pointing to the source site's home URL are rewritten to the destination site's home URL. An import can replace a slider with the same slug or create a separate copy.

## Changelog

### 1.5.0

- Added a native image focal-point picker with wide and narrow previews for image and linked-content slides.
- Preserved per-slide crop settings in slider export/import and retained the existing crop for older slides.

### 1.4.3

- Added pixel-based video cover sizing and centering for embedded TV browsers that ignore CSS video fitting and transforms.
- Recalculate video geometry when its metadata loads and when the viewport changes.

### 1.4.2

- Centered cover videos with a legacy-compatible minimum-size and transform technique for TV browsers with unreliable video `object-fit` support.

### 1.4.1

- Added explicit positioning fallbacks so slides, videos, and overlays fill their containers in older browsers that do not support the CSS `inset` shorthand.

### 1.4.0

- Added native, versioned slider transfer ZIP export and import under Sliders.
- Included directly selected media and remapped it without relying on WordPress IDs.
- Matched linked content by post type and slug/path, reported unresolved links, and rewrote source-site internal button URLs.

### 1.3.1

- Loaded the slider's structural styles in Gutenberg so its server-rendered preview matches the public hero instead of stacking every slide.
- Marked preview controls as non-interactive and added a subtle editor-only preview label.

### 1.3.0

- Added a native, server-rendered Mlýn Slider block with a published-slider selector and editor preview.

### 1.2.0

- Replaced the capped linked-content dropdown with an AJAX search picker.
- Added content-type filtering, exact-ID lookup, pagination, and edit-screen ID links.
- Preserved existing unavailable links and added clear status warnings.

Deleting the plugin leaves slider content in the database. This is intentional so a temporary deactivation or reinstall does not destroy editorial data.
