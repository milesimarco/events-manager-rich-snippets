=== Events Manager Rich Snippets ===
Contributors: Milmor
Donate link: https://www.paypal.me/milesimarco
Tags: events manager, structured data, schema, json-ld, seo
Requires at least: 6.1
Tested up to: 7.1
Requires PHP: 7.0
Requires Plugins: events-manager
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends Events Manager WordPress plugin with rich snippets preview (Google structured data).

== Description ==

Extends [Events Manager](https://wordpress.org/plugins/events-manager/) with schema.org Event structured data (JSON-LD), so search engines can show your events as rich results.

* Single event pages: structured data for the event itself.
* Events Manager pages (events list, calendar…): structured data for the next 25 upcoming events.
* Dates with the event timezone, all-day events, cancelled events.
* Physical locations, online (URL) locations and mixed events.
* Tickets as offers, with price, currency and availability, when bookings are enabled.
* No settings needed.

= Developers =

* `emrs_event_data` filters the structured data array of each event.
* `emrs_events_args` filters the `EM_Events::get()` arguments used on Events Manager pages.

== Frequently Asked Questions ==

= How can I check the output? =

Open an event page in the [Rich Results Test](https://search.google.com/test/rich-results) or the [Schema Markup Validator](https://validator.schema.org/).

== Changelog ==

= 1.1.0 2026-10-07 =
* New: structured data on single event pages.
* New: description, event status, attendance mode, online locations, ticket offers and organizer.
* New: `emrs_event_data` and `emrs_events_args` filters.
* Fixed: invalid JSON when event or location names contain quotes.
* Fixed: dates now use the event timezone instead of a fixed +02:00 offset.
* Fixed: all-day events use plain dates.
* Fixed: PHP warning on recent PHP versions.
* Improved: compatibility with Events Manager 7 and WordPress 7.1.

= 1.0.1 2020-10-02 =
* Minor improvements
* Compatibility check

= 1.0 2015-09-07 =
* First release
