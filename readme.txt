=== Shortcode & Plugin Inspector ===
Author: Abhirup Goswami, Indranil Mondal
Author URI : https://github.com/abhirup97, https://github.com/Indranil-Mondal
Requires at least: 5.0
Tested up to: 6.x
Stable tag: 1.0

Developer utility. After activation, go to Tools > Shortcode Inspector.

It shows:
- Shortcode output grouped by the plugin/theme that registers each shortcode,
  with the pages where that output appears (plugins surfacing data listed first).
- A flat table of every registered shortcode, its source, and usage.
- Unregistered "dead" shortcodes left in content by removed plugins.
- All active plugins (site and network).

Scanning covers post_content and Elementor (_elementor_data) on each page load.
This is a temporary tool — deactivate and delete once the audit is done.
