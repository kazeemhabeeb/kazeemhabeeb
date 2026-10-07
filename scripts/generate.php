<?php
/**
 * Profile generator.
 *
 * Pulls public data for a GitHub user, then:
 *   1. renders assets/stats.svg  (an animated stats + languages card)
 *   2. rewrites the README section between the PROJECTS markers
 *
 * Usage:
 *   php scripts/generate.php                      # live GitHub API
 *   php scripts/generate.php --fixture=path.json  # offline, from a saved JSON file
 *
 * Env:
 *   GITHUB_USER   username to render (default: kazeemhabeeb)
 *   GITHUB_TOKEN  optional, raises the API rate limit
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/..';

$user = getenv('GITHUB_USER') ?: 'kazeemhabeeb';
$opts = getopt('', ['fixture::']);

$data = isset($opts['fixture'])
    ? json_decode(file_get_contents($opts['fixture']), true, flags: JSON_THROW_ON_ERROR)
    : fetchProfile($user);

$stats = summarize($data);

file_put_contents(ROOT . '/assets/stats.svg', renderStatsCard($stats));
updateReadme(ROOT . '/README.md', renderProjects($stats['recent']));

fwrite(STDOUT, sprintf(
    "Rendered %s: %d repos, %d stars, top language %s\n",
    $user,
    $stats['repos'],
    $stats['stars'],
    $stats['languages'][0]['name'] ?? 'n/a'
));

// ---------------------------------------------------------------------------
// Data
// ---------------------------------------------------------------------------

function api(string $path): array
{
    $ch = curl_init('https://api.github.com' . $path);
    $headers = ['Accept: application/vnd.github+json', 'User-Agent: profile-generator'];
    if ($token = getenv('GITHUB_TOKEN')) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        throw new RuntimeException("GitHub API $path failed with HTTP $status");
    }
    return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
}

function fetchProfile(string $user): array
{
    $repos = [];
    for ($page = 1; $page <= 5; $page++) {
        $batch = api("/users/$user/repos?per_page=100&page=$page&sort=pushed");
        $repos = array_merge($repos, $batch);
        if (count($batch) < 100) {
            break;
        }
    }
    return ['user' => api("/users/$user"), 'repos' => $repos];
}

function summarize(array $data): array
{
    $user = $data['user'];
    // Forks and the profile repo itself aren't "projects".
    $repos = array_values(array_filter(
        $data['repos'],
        fn($r) => !$r['fork'] && strcasecmp($r['name'], $user['login']) !== 0
    ));

    $langCounts = [];
    foreach ($repos as $r) {
        if ($r['language']) {
            $langCounts[$r['language']] = ($langCounts[$r['language']] ?? 0) + 1;
        }
    }
    arsort($langCounts);
    $total = array_sum($langCounts) ?: 1;
    $languages = [];
    foreach (array_slice($langCounts, 0, 5, true) as $name => $count) {
        $languages[] = ['name' => $name, 'share' => $count / $total];
    }

    usort($repos, fn($a, $b) => strcmp($b['pushed_at'], $a['pushed_at']));

    return [
        'login' => $user['login'],
        'name' => $user['name'] ?: $user['login'],
        'repos' => count($repos),
        'stars' => array_sum(array_column($repos, 'stargazers_count')),
        'forks' => array_sum(array_column($repos, 'forks_count')),
        'followers' => $user['followers'],
        'since' => substr($user['created_at'], 0, 4),
        'languages' => $languages,
        'recent' => array_slice($repos, 0, 4),
    ];
}

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

function e(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function languageColor(string $lang): string
{
    return [
        'PHP' => '#777BB4', 'JavaScript' => '#F1E05A', 'TypeScript' => '#3178C6',
        'HTML' => '#E34C26', 'CSS' => '#663399', 'Python' => '#3572A5',
        'Java' => '#B07219', 'Go' => '#00ADD8', 'Rust' => '#DEA584',
        'Vue' => '#41B883', 'Blade' => '#F7523F', 'Shell' => '#89E051',
        'C#' => '#178600', 'Dart' => '#00B4AB', 'Kotlin' => '#A97BFF',
    ][$lang] ?? '#8B949E';
}

function renderStatsCard(array $s): string
{
    $tiles = [
        ['Repos', $s['repos']],
        ['Stars', $s['stars']],
        ['Forks', $s['forks']],
        ['Followers', $s['followers']],
    ];

    $tileSvg = '';
    foreach ($tiles as $i => [$label, $value]) {
        $x = 24 + $i * 98;
        $delay = 0.15 * $i;
        $tileSvg .= <<<SVG
          <g class="fade" style="animation-delay:{$delay}s" transform="translate($x 64)">
            <text class="num" y="0">$value</text>
            <text class="label" y="20">$label</text>
          </g>

        SVG;
    }

    // Stacked language bar, each segment grows in from the left.
    $bar = '';
    $legend = '';
    $offset = 0.0;
    $barWidth = 372;
    foreach ($s['languages'] as $i => $lang) {
        $w = round($lang['share'] * $barWidth, 1);
        $x = round(24 + $offset, 1);
        $color = languageColor($lang['name']);
        $delay = 0.6 + 0.1 * $i;
        $bar .= "<rect class=\"grow\" style=\"animation-delay:{$delay}s\" x=\"$x\" y=\"118\" width=\"$w\" height=\"8\" fill=\"$color\"/>\n";
        $offset += $w;

        $lx = 24 + ($i % 3) * 124;
        $ly = 148 + intdiv($i, 3) * 20;
        $pct = round($lang['share'] * 100);
        $name = e($lang['name']);
        $legend .= "<g class=\"fade\" style=\"animation-delay:{$delay}s\" transform=\"translate($lx $ly)\">"
            . "<circle cx=\"5\" cy=\"-4\" r=\"5\" fill=\"$color\"/>"
            . "<text class=\"label\" x=\"16\">$name <tspan class=\"dim\">$pct%</tspan></text></g>\n";
    }
    if ($bar === '') {
        $legend = '<text class="label" x="24" y="148">No languages yet. Push some code!</text>';
    }

    $name = e($s['name']);
    $since = e($s['since']);
    $updated = gmdate('M j, Y');

    return <<<SVG
    <svg xmlns="http://www.w3.org/2000/svg" width="420" height="200" viewBox="0 0 420 200" role="img" aria-label="GitHub stats for $name">
      <style>
        :root { --bg:#ffffff; --border:#d0d7de; --fg:#1f2328; --dim:#656d76; --accent:#777BB4; }
        @media (prefers-color-scheme: dark) {
          :root { --bg:#0d1117; --border:#30363d; --fg:#e6edf3; --dim:#8b949e; --accent:#a5a8e6; }
        }
        text { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; fill: var(--fg); }
        .title { font-size: 15px; font-weight: 700; }
        .num { font-size: 22px; font-weight: 700; fill: var(--accent); }
        .label { font-size: 11px; }
        .dim { fill: var(--dim); }
        .fade { opacity: 0; animation: fade .6s ease-out forwards; }
        .grow { transform-box: fill-box; transform-origin: left; transform: scaleX(0); animation: grow .8s ease-out forwards; }
        @keyframes fade { to { opacity: 1; } }
        @keyframes grow { to { transform: scaleX(1); } }
        @media (prefers-reduced-motion: reduce) { .fade, .grow { animation: none; opacity: 1; transform: none; } }
      </style>
      <rect x="0.5" y="0.5" width="419" height="199" rx="10" fill="var(--bg)" stroke="var(--border)"/>
      <text class="title" x="24" y="32">$name's GitHub</text>
      <text class="label dim" x="396" y="32" text-anchor="end">coding since $since</text>
    $tileSvg
      <clipPath id="bar"><rect x="24" y="118" width="$barWidth" height="8" rx="4"/></clipPath>
      <rect x="24" y="118" width="$barWidth" height="8" rx="4" fill="var(--border)"/>
      <g clip-path="url(#bar)">
    $bar  </g>
    $legend
      <text class="label dim" x="396" y="188" text-anchor="end" font-size="9">updated $updated</text>
    </svg>

    SVG;
}

function renderProjects(array $repos): string
{
    if (!$repos) {
        return "_Nothing public yet. Something's cooking._\n";
    }
    $rows = ["| Project | What it is | Lang | ★ |", "|---|---|---|---|"];
    foreach ($repos as $r) {
        $desc = str_replace('|', '\|', $r['description'] ?? '') ?: '–';
        $rows[] = sprintf(
            '| [%s](%s) | %s | %s | %d |',
            $r['name'],
            $r['html_url'],
            $desc,
            $r['language'] ?? '–',
            $r['stargazers_count']
        );
    }
    return implode("\n", $rows) . "\n";
}

function updateReadme(string $path, string $section): void
{
    $readme = file_get_contents($path);
    $pattern = '/(<!-- PROJECTS:START -->\n).*?(<!-- PROJECTS:END -->)/s';
    if (!preg_match($pattern, $readme)) {
        throw new RuntimeException('README is missing the PROJECTS markers');
    }
    file_put_contents($path, preg_replace_callback(
        $pattern,
        fn($m) => $m[1] . $section . $m[2],
        $readme
    ));
}
