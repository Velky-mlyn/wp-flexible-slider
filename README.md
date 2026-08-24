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

Optional shortcode overrides are `variant="hero|default"`, `autoplay="true|false"`, and `class="custom-class"`.

Slides can use a custom image, a Media Library video, or linked WordPress content. A linked event inherits its title, first tag, start date, URL, and featured image unless the corresponding slide fields override them.

The linked-content picker searches all published public content that the current administrator may edit, including past calendar events. Search by title/text or exact numeric ID, optionally filter by content type, and use the displayed ID link to verify the item on its WordPress edit screen. Existing links are preserved and identified even if their content later becomes unpublished or is deleted.

## Changelog

### 1.2.0

- Replaced the capped linked-content dropdown with an AJAX search picker.
- Added content-type filtering, exact-ID lookup, pagination, and edit-screen ID links.
- Preserved existing unavailable links and added clear status warnings.

Deleting the plugin leaves slider content in the database. This is intentional so a temporary deactivation or reinstall does not destroy editorial data.
