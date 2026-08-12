# Mlýn Flexible Slider

A reusable WordPress slider manager following the conventions of the `mlyn-social-feed-curator` project plugin.

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

Deleting the plugin leaves slider content in the database. This is intentional so a temporary deactivation or reinstall does not destroy editorial data.
