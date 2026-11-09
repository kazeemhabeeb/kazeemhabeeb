<?php
/*
 * Ingestion module, part 2: sanitization (Objective 2, FR-1.2, §3.8).
 * Every item is cleaned here before tier 1, the AI service or the database sees it.
 * Pages still escape everything again with e() when they print it (NFR-1.1).
 */

define('MAX_TITLE_LENGTH', 300);
define('MAX_SUMMARY_LENGTH', 2000);
define('MAX_CATEGORY_LENGTH', 100);
define('MAX_URL_LENGTH', 500);

// Clean one feed item. Returns the same six fields, safe to store.
function sanitize_item($item)
{
    return [
        'title'    => clean_text($item['title'], MAX_TITLE_LENGTH),
        'link'     => clean_url($item['link']),
        'summary'  => clean_text($item['summary'], MAX_SUMMARY_LENGTH),
        'date'     => $item['date'],   // already rebuilt by to_local_datetime()
        'category' => clean_text($item['category'], MAX_CATEGORY_LENGTH),
        'image'    => clean_url($item['image']),
    ];
}

// Turn feed HTML into plain text (§3.4.2B step 4: "HTML tags are stripped ... normalized").
function clean_text($html, $maxLength)
{
    $text = (string) $html;

    // 1. Remove the WordPress footer: "The post <title> appeared first on <site>."
    $text = preg_replace('#<p>\s*The post .*?appeared first on.*?</p>#is', ' ', $text);

    // 2. Strip, decode, then strip again. Decoding can turn "&lt;script&gt;" into a real tag,
    //    so the second pass removes anything the first pass could not see.
    $text = strip_html($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_html($text);

    // 3. The same footer when the feed sends it as plain text, and WordPress's "[…]" ending.
    $text = preg_replace('/The post .*?appeared first on .*$/s', '', $text);
    $text = preg_replace('/\s*\[(…|\.\.\.)\]\s*$/u', '', $text);

    // 4. Remove invisible control characters and collapse runs of spaces and new lines.
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    $text = trim(preg_replace('/\s+/u', ' ', $text));

    return shorten($text, $maxLength);
}

// Remove <script> and <style> blocks with their contents, then every remaining tag.
function strip_html($html)
{
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', $html);
    $html = preg_replace('#<(br|/p|/div|/li|/h[1-6])\b[^>]*>#i', ' ', $html);   // keep words apart
    return strip_tags($html);
}

// Cut text to a maximum length at a word boundary, adding "…".
function shorten($text, $maxLength)
{
    if (mb_strlen($text, 'UTF-8') <= $maxLength) {
        return $text;
    }
    $cut = mb_substr($text, 0, $maxLength - 1, 'UTF-8');
    $space = mb_strrpos($cut, ' ', 0, 'UTF-8');
    if ($space !== false && $space > $maxLength * 0.8) {
        $cut = mb_substr($cut, 0, $space, 'UTF-8');
    }
    return rtrim($cut) . '…';
}

// Keep a link or image address only if it is a valid http/https URL (blocks javascript:, data:, file:).
function clean_url($url)
{
    $url = trim((string) $url);
    if ($url === '' || strlen($url) > MAX_URL_LENGTH) {
        return '';
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return '';
    }
    return $url;
}
