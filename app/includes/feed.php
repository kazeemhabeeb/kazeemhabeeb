<?php
/*
 * Ingestion module, part 1: fetch a source and read its articles (§3.7 module 1).
 * A source is either an RSS feed (XML) or a WordPress news API (JSON), Objective 1.
 * Every reader returns the same list of items:
 *   ['title', 'link', 'summary', 'date', 'category', 'image']
 * The text is still raw here; sanitize.php cleans it before anything is saved.
 */

define('MAX_FEED_BYTES', 5 * 1024 * 1024);   // a feed reply larger than 5 MB is refused
define('MAX_REDIRECTS', 3);

// Is this address allowed to be fetched? Refuses local and private networks (127.x, 10.x, 192.168.x,
// 169.254.x and other reserved ranges), so a feed URL or a redirect cannot make the server read its own
// internal services (server-side request forgery). Returns ['ok', 'ip' (for pinning, or null), 'error'].
function check_public_url($url, $allowPrivate = false)
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return ['ok' => false, 'ip' => null, 'error' => 'the address has no host name'];
    }
    $host = trim($host, '[]');
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
    if ($ips === false) {
        // Could not look the name up here; cURL will fail to connect too (unless a proxy handles it).
        return ['ok' => true, 'ip' => null, 'error' => ''];
    }
    foreach ($ips as $ip) {
        if (!$allowPrivate && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['ok' => false, 'ip' => null, 'error' => 'the address points to a private or local network'];
        }
    }
    return ['ok' => true, 'ip' => $ips[0], 'error' => ''];
}

// Download a source with cURL (§3.5). Returns ['ok', 'body', 'error'].
// Redirects are followed by hand (at most 3) so that every step is checked by check_public_url().
function fetch_url($url, $allowPrivate = null)
{
    if ($allowPrivate === null) {
        $allowPrivate = defined('ALLOW_PRIVATE_FEED_ADDRESSES') && ALLOW_PRIVATE_FEED_ADDRESSES;   // tests only
    }

    for ($hop = 0; $hop <= MAX_REDIRECTS; $hop++) {
        $check = check_public_url($url, $allowPrivate);
        if (!$check['ok']) {
            return ['ok' => false, 'body' => '', 'error' => 'blocked: ' . $check['error']];
        }

        $body = '';
        $tooBig = false;
        $ch = curl_init($url);
        $options = [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,   // no file:// or other schemes
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => FEED_TIMEOUT_SECONDS,               // §1.6: never hang the run
            CURLOPT_USERAGENT      => SITE_NAME . ' news aggregator',
            CURLOPT_ENCODING       => '',                                  // accept gzip
            // Collect the reply ourselves and stop at MAX_FEED_BYTES (after unzipping), so a huge or
            // "zip bomb" reply cannot use up the server's memory.
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body, &$tooBig) {
                if (strlen($body) + strlen($chunk) > MAX_FEED_BYTES) {
                    $tooBig = true;
                    return 0;   // tells cURL to stop
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ];
        if ($check['ip'] !== null) {
            // Connect to the address we just checked, not one the name might point to a moment later.
            $port = parse_url($url, PHP_URL_PORT) ?: (strtolower(parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80);
            $options[CURLOPT_RESOLVE] = [parse_url($url, PHP_URL_HOST) . ':' . $port . ':' . $check['ip']];
        }
        curl_setopt_array($ch, $options);
        $ok       = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($tooBig) {
            return ['ok' => false, 'body' => '', 'error' => 'the reply was larger than ' . (MAX_FEED_BYTES / 1048576) . ' MB'];
        }
        if ($ok === false) {
            return ['ok' => false, 'body' => '', 'error' => $error];
        }
        if ($status >= 300 && $status < 400 && $redirect) {
            if (clean_redirect($redirect) === '') {
                return ['ok' => false, 'body' => '', 'error' => 'redirected to an address that is not http or https'];
            }
            $url = $redirect;   // checked again at the top of the loop
            continue;
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'body' => '', 'error' => 'HTTP status ' . $status];
        }
        return ['ok' => true, 'body' => $body, 'error' => ''];
    }
    return ['ok' => false, 'body' => '', 'error' => 'too many redirects'];
}

// A redirect target must be an http or https address.
function clean_redirect($url)
{
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return ($scheme === 'http' || $scheme === 'https') ? $url : '';
}

// WordPress only includes category names and images when "_embed" is in the URL,
// so add it if the admin left it out.
function prepare_source_url($url)
{
    if (strpos($url, '/wp-json/') !== false && strpos($url, '_embed') === false) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . '_embed';
    }
    return $url;
}

// Decide how to read the reply: JSON starts with { or [, XML starts with <.
function detect_format($body)
{
    $start = substr(ltrim($body, "\xEF\xBB\xBF \t\r\n"), 0, 1);  // skip a byte-order mark and spaces
    if ($start === '{' || $start === '[') {
        return 'json';
    }
    if ($start === '<') {
        return 'xml';
    }
    return 'unknown';
}

// Read the items from a downloaded source. Returns a list of items, or null if it cannot be read.
function parse_source($body)
{
    $format = detect_format($body);
    $items = null;
    if ($format === 'xml') {
        $items = parse_rss($body);
    } elseif ($format === 'json') {
        $items = parse_wordpress_json($body);
    }
    return $items === null ? null : drop_repeated_images($items);
}

// RSS 2.0 (§3.5 "XML parsing" with SimpleXML).
function parse_rss($xml)
{
    libxml_use_internal_errors(true);
    // LIBXML_NONET: the parser may not download anything the feed points to.
    $rss = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    if ($rss === false || !isset($rss->channel)) {
        return null;
    }

    $items = [];
    foreach ($rss->channel->item as $item) {
        $media   = $item->children('http://search.yahoo.com/mrss/');
        $content = $item->children('http://purl.org/rss/1.0/modules/content/');
        $fullHtml = isset($content->encoded) ? (string) $content->encoded : '';

        // Image: <media:content>, then <media:thumbnail>, then an image <enclosure>, then the first <img>.
        $image = '';
        if (isset($media->content) && isset($media->content->attributes()->url)) {
            $image = (string) $media->content->attributes()->url;
        } elseif (isset($media->thumbnail) && isset($media->thumbnail->attributes()->url)) {
            $image = (string) $media->thumbnail->attributes()->url;
        } elseif (isset($item->enclosure) && strpos((string) $item->enclosure['type'], 'image/') === 0) {
            $image = (string) $item->enclosure['url'];
        } else {
            $image = first_image_src($fullHtml . (string) $item->description);
        }

        $items[] = [
            'title'    => (string) $item->title,
            'link'     => trim((string) $item->link),
            'summary'  => (string) $item->description !== '' ? (string) $item->description : $fullHtml,
            'date'     => to_local_datetime((string) $item->pubDate),
            'category' => isset($item->category[0]) ? (string) $item->category[0] : '',
            'image'    => $image,
        ];
    }
    return $items;
}

// WordPress REST API: /wp-json/wp/v2/posts?_embed (decision 8).
function parse_wordpress_json($json)
{
    $posts = json_decode($json, true);
    if (!is_array($posts) || (count($posts) > 0 && !isset($posts[0]['link']))) {
        return null;   // not a list of WordPress posts
    }

    $items = [];
    foreach ($posts as $post) {
        $embedded = isset($post['_embedded']) ? $post['_embedded'] : [];

        // date_gmt is UTC; fall back to date, which is in the publisher's own time zone.
        $date = '';
        if (!empty($post['date_gmt'])) {
            $date = to_local_datetime($post['date_gmt'] . ' UTC');
        } elseif (!empty($post['date'])) {
            $date = to_local_datetime($post['date']);
        }

        $items[] = [
            'title'    => isset($post['title']['rendered']) ? $post['title']['rendered'] : '',
            'link'     => isset($post['link']) ? trim($post['link']) : '',
            'summary'  => isset($post['excerpt']['rendered']) ? $post['excerpt']['rendered'] : '',
            'date'     => $date,
            'category' => isset($embedded['wp:term'][0][0]['name']) ? $embedded['wp:term'][0][0]['name'] : '',
            'image'    => isset($embedded['wp:featuredmedia'][0]['source_url']) ? $embedded['wp:featuredmedia'][0]['source_url'] : '',
        ];
    }
    return $items;
}

// Some feeds give every item the same picture, usually the publisher's logo (Punch does).
// If one image is used by more than half of a feed's items (and at least 3), it is not a
// photo of the story, so it is dropped and the page shows the placeholder instead.
function drop_repeated_images($items)
{
    $uses = array_count_values(array_filter(array_column($items, 'image')));
    foreach ($items as $i => $item) {
        $n = isset($uses[$item['image']]) ? $uses[$item['image']] : 0;
        if ($n >= 3 && $n > count($items) / 2) {
            $items[$i]['image'] = '';
        }
    }
    return $items;
}

// The src of the first <img> in a block of HTML, or ''.
function first_image_src($html)
{
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

// Turn any feed date into "Y-m-d H:i:s" in the site's time zone, or '' if it cannot be read.
function to_local_datetime($text)
{
    $time = strtotime(trim($text));
    return $time === false ? '' : date('Y-m-d H:i:s', $time);
}
