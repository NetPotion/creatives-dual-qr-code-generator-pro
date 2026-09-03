=== Creatives Dual QR Code Generator Pro ===
Contributors: midstatedesign
Tags: qr-code, qr-generator, shortcode, cloudflare, turnstile, logo
Requires at least: 5.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

QR code generator for WordPress, front end and back end, with optional center logos and Cloudflare Turnstile on the public form.

== Description ==

Creatives Dual QR Code Generator Pro is a complete, standalone QR code solution for WordPress. No dependencies required.

**Features:**
* Admin QR code generator (editors and up, no CAPTCHA or Terms step)
* Frontend shortcode: `[creatives_qr_frontend]`
* Cloudflare Turnstile CAPTCHA (free tier), optional
* Optional center logo (site-wide or visitor-supplied)
* Download PNG or SVG formats
* Configurable Terms of Use gate on the public form
* Rate limiting, configurable per visitor and window
* Private IP blocking (security)
* Responsive mobile design
* Nonce protection, URL validation, output escaping

**Installation:**
1. Upload plugin
2. Activate
3. Go to Dual QR Code Generator → Settings
4. Get free Cloudflare Turnstile keys
5. Add shortcode to page: `[creatives_qr_frontend]`

== Installation ==

1. Download creatives-dual-qr-code-generator-pro.zip
2. Go to Plugins → Add New → Upload Plugin
3. Select the ZIP and click Install
4. Activate the plugin
5. Go to Dual QR Code Generator → Settings
6. Configure Turnstile keys
7. Add `[creatives_qr_frontend]` to any page

== Frequently Asked Questions ==

= Do I need another plugin? =

No. This plugin is completely standalone and includes everything.

= I had the old Creatives QR Generator Pro plugin installed. What happens to my settings? =

They carry forward automatically. On activation this plugin looks for
Turnstile keys, Terms of Use text, rate limit, and Center Logo settings
from the earlier `creatives-qr-generator-pro` build (and from Creatives QR
Code Generator Pro, if that was installed instead) and copies them once.
The old plugin's folder stays on disk until you delete it; deactivate and
delete it once you have confirmed this plugin is working, and update any
admin bookmark from `options-general.php?page=creatives-qr-pro` or the old
plugin's own generate/settings links to this plugin's — the URLs
`admin.php?page=creatives-qr-generate` and `admin.php?page=creatives-qr-pro`
are unchanged from the previous build.

= Can I remove or replace the credit line on the public form? =

Both. QR Generator -> Settings -> Public Form Display has a checkbox that
removes the note entirely, and a text field that replaces it with your own
wording. Your text can include links and basic formatting, and the
{company} placeholder is filled in the same way it is in the terms. Nothing
else changes and no feature is withheld either way.

= Can I translate it? =

Yes. A .pot template covering every string sits in the plugin's languages
folder. Drop your compiled .mo file beside it, named for your locale, for
example creatives-dual-qr-code-generator-pro-de_DE.mo.

= How do I get Turnstile keys? =

Visit https://dash.cloudflare.com/ → Turnstile → Create Site

= How many QR codes can users generate? =

Whatever you set at QR Generator -> Settings -> Rate Limiting. The default
is 5 per visitor per day, counted over a rolling window against the visitor
IP. The admin generator is not rate limited.

= My site is behind Cloudflare. Does rate limiting still work? =

Tick "This site sits behind Cloudflare" under Rate Limiting. Without it the
plugin sees Cloudflare's proxy addresses instead of your visitors, and a
handful of visitors lock everyone else out. Do not tick it if the site is
not proxied: the header it starts trusting can be sent by anyone.

= Can I change the Terms of Use, or turn them off? =

Yes. QR Generator -> Settings -> Terms of Use holds a company name, the
opening text, and the full terms in an editor. Leave the text fields empty
to use the wording that ships with the plugin. Three placeholders are
filled in when the block renders: {company}, {limit} and {window}, so the
usage-limit clause always matches the rate limit you actually set.

Unticking "Show Terms of Use" removes the block, the agreement checkbox and
the matching server-side check. The CAPTCHA and rate limit stay.

= How do I put a logo in the middle? =

Dual QR Code Generator -> Settings -> Center Logo. Pick one of three modes:

* **No logo** - codes stay plain (default).
* **Always use the site logo** - every code generated from the shortcode
  carries the image you select from the media library.
* **Let visitors upload their own logo** - adds an optional file field to
  the frontend form.

= Are visitor-uploaded logos stored on my site? =

No. A visitor upload is read from PHP's temporary file, validated,
re-encoded through GD, drawn into the code, and discarded within the same
request. Nothing is written to the media library or the uploads folder, so
there is no public upload directory to secure and no cleanup to schedule.
The only logo that persists is the one an administrator selects.

= Will a logo stop the code from scanning? =

No. Codes carrying a logo are encoded at error-correction level H (30%
recoverable) instead of the default M, and the logo is budgeted by area
rather than width: Small, Medium and Large cover 4%, 6.5% and 9% of the
code's module area, so a denser code gets a proportionally smaller logo
rather than a proportionally larger dead zone. Those budgets came from
sweeping coverage from 2% to 22% across QR versions 4-21 with square,
tall and wide logos, scored on two independent decoders against a
no-logo baseline. Verify any code before you print it, as always.

= What logo formats are accepted? =

PNG, JPG, and WebP (WebP only when the server's GD build supports it).
SVG is rejected because it is a script-bearing format with no place on a
public upload endpoint, and .ico is rejected because GD cannot decode it.
Maximum file size is 2 MB.

= The logo settings say "Unavailable on this server" =

The PHP GD extension is not installed. Ask your host to enable it. QR
generation itself still works without it, falling back to SVG output.

= Can I have this and another Creatives QR plugin active at once? =

Yes, they will not conflict — each uses its own prefixes internally. You
will see an advisory notice suggesting you keep only the one you actually
use, since running more than one just adds duplicate menu entries.

== Changelog ==

= 3.0.0 =
* Split into its own plugin, distinct from Creatives QR Code Generator and
  Creatives QR Code Generator Pro. New folder slug
  (creatives-dual-qr-code-generator-pro) and new text domain, class,
  function, constant and option prefixes throughout, so all three plugins
  can be installed side by side without any of them silently overriding
  another. The public shortcode `[creatives_qr_frontend]` and both admin
  menu URLs (admin.php?page=creatives-qr-generate and
  admin.php?page=creatives-qr-pro) are unchanged, so existing pages and
  bookmarks keep working.
* New: on activation, Turnstile keys, Terms of Use text, rate limit and
  Center Logo settings are copied forward once from an earlier
  creatives-qr-generator-pro or Creatives QR Code Generator Pro install, so
  upgrading does not mean reconfiguring from scratch.
* New: advisory (non-blocking) notice when another Creatives QR plugin is
  also active, suggesting only one be kept.

= 2.6.8 =
* Renamed to Creatives Dual QR Code Generator Pro. Display name only: the
  plugin folder, text domain, shortcode, menu slug and every option name are
  unchanged, so this upgrades in place and existing settings are kept.

= 2.6.7 =
* Fix: the "remove the saved secret key" checkbox did nothing. Clearing the
  key wrote an empty value, which the key's own sanitiser reads as "keep
  what is stored" so that saving any other setting cannot wipe it, so the
  removal was turned straight back into the old key. Removal now works, and
  a key typed into the field wins over a checkbox left ticked rather than
  being silently discarded.
* Fix: on sites with a persistent object cache (Redis, Memcached), the
  rate-limit window slid forward on every code generated, so a busy visitor
  could stay capped for far longer than the window they were promised. The
  window start is now carried inside the rate-limit record itself instead
  of being read back from a database row that does not exist when
  transients live in an object cache. Records written by earlier versions
  still enforce the cap and migrate on the next request.

= Earlier versions =

See the plugin's repository history for the full changelog back to 2.0.0.

== Support ==

For issues or questions, contact the plugin author.
