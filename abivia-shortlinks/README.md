# Abivia Link Shortener for Wordpress

Advanced URL shortener for WordPress with analytics, link rotation, geotargeting,
and password protection (based on Custom Link Shortener by Lukastech).

License: GPLv3 or later. Requires PHP: 8.4 or later.

## Features

- Create custom short URLs (e.g., `yoursite.com/l/product`).
- Detailed click analytics with:
  - Visitor IP addresses
  - User agents
  - Timestamps
- URL rotation (randomize destinations).
- Location-specific targets: send visitors to the best link for their location.
- Password protection for private links.
- CSV export for all analytics data.
- Edit existing short URLs.
- Delete short URLs with confirmation.
- Clean WordPress admin interface.
- Doesn't suggest you create a new link in every post/page/whatever.

## PRO Features

- None, nothing, nada. This plugin doesn't limit any feature.
- There is no nagging for support, no pop-up asking for ratings or "feedback". 

## Installation

1. Upload the plugin files to `/wp-content/plugins/abivia-shortlinks`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WP Shortener in your admin menu to create links

## Usage

### Creating Short Links
1. Go to Custom Link Shortener in your admin menu
2. In settings select the prefix for your short links and your IP lookup provider.
Note: the ipapi.co provider will allow a limited number of lookups without a key.
3. Enter:
   - Your preferred alias (e.g., "product")
   - Destination URL(s) (one per line for rotation)
4. Optional: Enable rotation or set a password
5. Click "Create Short Link"

### Viewing Analytics
1. Go to Custom Link Shortener → Analytics
2. View all links with click counts
3. Click "View" on any link to see detailed stats
4. Export data as CSV when needed

### Managing Links
- Edit: Change destinations, rotation, or password
- Delete: Remove short links (with confirmation)
- Test: Verify your links work before sharing

## Advanced Features

### URL Rotation

When a short code has multiple destinations:
- Visitors get randomly redirected to one of the URLs
- All clicks are still tracked accurately
- Facilitates A/B (or A/B/C/D...) testing.

### Geo-targeting
Links can be set to apply only to specific regions based on an IP lookup.
To limit a link, add the geo-filter code in brackets before the target URL.

IP address lookups only performed when a link is geocoded.

### Password Protection
- Set a password when creating/editing links
- Visitors must enter password before redirect
- Works with both GET and POST requests

## Shortcodes

### Insert a short link

[short alias="code" text="link text" class="class"]

Only the alias is required. The text attribute overrides any text defined in the link.
If provided, the class is applied to the link.

### Insert a list of links

Useful when filtering links by geocode. Note that:
- Clicks are not tracked in this case.
- Link rotation does not apply. All matching links are listed.
 
[shortlist alias="code"]

Optional attributes:
- empty: text to display if all links are filtered by geocoding, an empty string if not provided.
- list_class: a class or classes to apply to the overall list (the \<ul> element). 
- item_class: a class or classes to apply to the list items (the \<li> elements).
- link_class: a class or classes to apply to the links (the \<a> elements).
- password: if the shortcode is password protected, a valid password must be supplied.

If there are errors, the "empty" text is displayed unless the current user has edit rights,
in which case an error message is shown.

## Frequently Asked Questions

### Can I use custom slugs?
Yes! You can choose any alphanumeric slug (letters, numbers, hyphens, underscores).

### How does the plugin track visitor data?
The plugin automatically tracks:
- IP address
- Device/browser (via user agent)
- Timestamp

### Can I export my analytics?
Yes! Click "Export CSV" on any analytics page to download all data.

### What happens if I delete a short URL?
All associated data (destinations, click records) are permanently deleted.

## Changelog

### 1.1.1 2025-10-07
- Minor improvements: cancel buttons, add default text to the overview page.

### 1.1.0 2025-10-06
- Added support for a custom link prefix.
- Added the ability to select IP address provider.
- Added an IP lookup screen to test lookup credentials.
- Fixed documentation errors.

### 1.0.0 2025-09-23
- Adapted from links plugin 1.4.3 by Lukastech
- Extracted views into Penknife templates.
- Changed the link stub from "go" to "l", made it a constant.
- Encapsulated everything in a class.
- Link aliases are converted to a lowercase slug.
- Minimum PHP 8.4.
- Removed the "random" feature.
- Added geo-based redirection
- Modified geo-lookups to only run on geo-directed links
- Now logs which link was used on a rotating shortcode.

## Support

If you find a problem open an issue on github.

---

Pro Tip: For maximum compatibility, always test new short links before sharing them widely!
