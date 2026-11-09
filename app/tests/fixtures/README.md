# Test fixtures

Saved copies of sources, used to test ingestion without the internet.

- `rss-sample.xml`: item 1 is a real P.M. News item (9 October 2026). Items 2–5 are made up to test
  each rule: a clickbait headline, an XSS attack in the title, description and image, a sponsored
  post, and an item with no title.
- `wordpress-sample.json`: made up in the shape of the WordPress REST API (`/wp-json/wp/v2/posts?_embed`),
  using two headlines from our earlier tests.
