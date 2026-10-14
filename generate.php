<?php
declare(strict_types=1);

function cleanText($value): string {
    $text = trim(strip_tags((string)$value));
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}
function xmlEscape($value): string {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
function limitUtf8(string $s, int $length): string {
    if (function_exists('mb_substr')) return mb_substr($s, 0, $length, 'UTF-8');
    return implode('', array_slice(preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY), 0, $length));
}
function fixedPrice($value): ?string {
    if (!is_scalar($value)) return null;
    $n = str_replace(["\xC2\xA0", ' ', ','], ['', '', '.'], trim((string)$value));
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $n) || (float)$n <= 0) return null;
    return number_format((float)$n, 2, '.', '');
}
function inStock($value): bool {
    $s = trim((string)$value);
    if ($s === '' || preg_match('/нет в наличии/ui', $s)) return false;
    if (preg_match('/заказ|изготовлен|производств/ui', $s)) return false;
    return (bool)preg_match('/в наличии/ui', $s);
}
function absoluteLocalUrl($raw, string $base): string {
    if (!is_scalar($raw)) return '';
    $raw = trim((string)$raw);
    if ($raw === '' || preg_match('/no-photo|placeholder/i', $raw)) return '';
    if (preg_match('~^https?://~i', $raw)) {
        $u = parse_url($raw);
        if (!is_array($u) || !isset($u['host'])) return '';
        if (strtolower($u['host']) !== strtolower((string)parse_url($base, PHP_URL_HOST))) return '';
        $path = $u['path'] ?? '';
    } else {
        $path = '/' . ltrim($raw, '/');
    }
    if ($path === '' || strpos($path, '..') !== false || preg_match('/[?#]/', $path)) return '';
    return rtrim($base, '/') . $path;
}
function loadRows(string $file): array {
    if (!is_readable($file)) return [];
    $raw = file_get_contents($file);
    if ($raw === false) return [];
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $json = json_decode($raw, true);
    return is_array($json) && array_values($json) === $json ? $json : [];
}
function generateFeed(array $config): array {
    $base = rtrim((string)$config['base_url'], '/');
    $root = realpath((string)$config['public_root']);
    if (!$root || !preg_match('~^https://[^/]+$~i', $base)) throw new RuntimeException('Valid HTTPS base_url and public_root are required.');
    $items = [];
    $skipped = 0;
    foreach ($config['catalog_files'] as $file) {
        foreach (loadRows((string)$file) as $p) {
            if (!is_array($p)) { $skipped++; continue; }
            $title = cleanText($p['item_title'] ?? '');
            $price = fixedPrice($p['item_price'] ?? null);
            $priceLabel = (string)($p['price_label'] ?? '');
            $path = trim((string)($p['item_path'] ?? ''), '/');
            $path = preg_replace('~^catalog/~', '', $path);
            if ($title === '' || $price === null || $path === '' || strpos($path, '..') !== false || preg_match('/[?#]/', $path) || !inStock($p['is_available'] ?? '')) { $skipped++; continue; }
            if (preg_match('/^\s*от\b|по\s*запросу|договорн/ui', $priceLabel)) { $skipped++; continue; }
            $url = $base . '/catalog/' . $path;
            $image = absoluteLocalUrl($p['main_photo'] ?? '', $base);
            $localImage = $root . (string)parse_url($image, PHP_URL_PATH);
            if ($image === '' || !is_file($localImage)) { $skipped++; continue; }
            if (isset($items[$url])) { $skipped++; continue; }
            $additional = [];
            for ($i=1; $i<=5; $i++) {
                $extra = absoluteLocalUrl($p['dop_photo_' . $i] ?? '', $base);
                if ($extra !== '' && $extra !== $image && is_file($root . (string)parse_url($extra, PHP_URL_PATH))) $additional[$extra] = true;
            }
            $brand = cleanText($p['brand'] ?? ($config['default_brand'] ?? ''));
            if ($brand === '') { $skipped++; continue; }
            $desc = cleanText($p['short_description'] ?? '');
            $items[$url] = [
                'id' => substr(sha1($url), 0, 20),
                'title' => limitUtf8($title, 150),
                'description' => limitUtf8($desc !== '' ? $desc : $title, 5000),
                'url' => $url, 'image' => $image,
                'additional' => array_keys($additional), 'price' => $price,
                'brand' => $brand, 'mpn' => cleanText($p['articul'] ?? ''),
                'product_type' => cleanText($p['category'] ?? '') . (empty($p['subcategory']) ? '' : ' > ' . cleanText($p['subcategory']))
            ];
        }
    }
    ksort($items, SORT_STRING);
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $out .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n<channel>\n";
    $out .= '<title>' . xmlEscape($config['store_name']) . "</title>\n";
    $out .= '<link>' . xmlEscape($base . '/') . "</link>\n";
    $out .= '<description>Fixed-price products available in stock</description>' . "\n";
    foreach ($items as $p) {
        $out .= "<item>\n";
        foreach (['id'=>'id','title'=>'title','description'=>'description','link'=>'url','image_link'=>'image'] as $tag=>$key) $out .= '<g:' . $tag . '>' . xmlEscape($p[$key]) . '</g:' . $tag . ">\n";
        foreach ($p['additional'] as $img) $out .= '<g:additional_image_link>' . xmlEscape($img) . "</g:additional_image_link>\n";
        $out .= "<g:availability>in_stock</g:availability>\n";
        $out .= '<g:price>' . $p['price'] . ' ' . xmlEscape($config['currency']) . "</g:price>\n";
        $out .= "<g:condition>new</g:condition>\n";
        $out .= '<g:brand>' . xmlEscape($p['brand']) . "</g:brand>\n";
        if ($p['mpn'] !== '') $out .= '<g:mpn>' . xmlEscape($p['mpn']) . "</g:mpn>\n";
        if ($p['product_type'] !== '') $out .= '<g:product_type>' . xmlEscape($p['product_type']) . "</g:product_type>\n";
        $out .= "</item>\n";
    }
    return [$out . "</channel>\n</rss>\n", count($items), $skipped];
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $opts = getopt('', ['config:', 'output:']);
    $file = $opts['config'] ?? (__DIR__ . '/config.demo.json');
    $config = json_decode((string)file_get_contents($file), true);
    if (!is_array($config)) { fwrite(STDERR, "Invalid config\n"); exit(1); }
    $directory = dirname(realpath($file) ?: $file);
    $config['public_root'] = $directory . '/' . ltrim($config['public_root'], '/');
    $config['catalog_files'] = array_map(function ($p) use ($directory) { return $directory . '/' . ltrim($p, '/'); }, $config['catalog_files']);
    try {
        list($xml, $count, $skipped) = generateFeed($config);
        $output = $opts['output'] ?? (__DIR__ . '/examples/google-products.xml');
        if (file_put_contents($output, $xml) === false) throw new RuntimeException('Cannot write output.');
        fwrite(STDOUT, "Generated {$count} products; skipped {$skipped}. Output: {$output}\n");
    } catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
}
