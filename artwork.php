#!/usr/local/bin/php
<?php
$country = $_ENV['country'];
$entity  = $argv[1];
$search  = $argv[2];
$url     = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=' . urlencode($search) . "&country={$country}&entity={$entity}";
$obj     = json_decode(file_get_contents($url));
$items   = array();

foreach ($obj->results as $result) {
    $item        = array();
    $item['arg'] = str_replace('100x100', '1200x1200', $result->artworkUrl100);

    $hires = str_replace('.100x100-75', '', $result->artworkUrl100);
    $parts = parse_url($hires);
    $hires = "http://a4.mzstatic.com{$parts['path']}";

    $item['hires'] = $hires;
    $item['title'] = ($entity == 'movie') ? $result->trackName : $result->collectionName;

    switch ($entity) {
    case 'album':
        $item['hires'] = str_replace('100x100', '1200x1200', $result->artworkUrl100);
        $item['title'] = "{$result->collectionName} (by {$result->artistName})";
        break;
    case 'tvSeason':
        $item['title'] = $result->collectionName;
        break;
    case 'movie':
        $item['title'] = "{$result->trackName} (may not work)";
        break;
    case 'musicVideo':
        $item['title'] = "{$result->trackName} (by {$result->artistName})";
        $item['arg']   = $hires;
        break;
    case 'ebook':
        $item['title'] = "{$result->trackName} (by {$result->artistName}) (probably won’t work)";
        break;
    case 'audiobook':
        $item['title'] = "{$result->collectionName} (by {$result->artistName}) (probably won’t work)";
        break;
    case 'podcast':
        $item['title'] = "{$result->collectionName} (by {$result->artistName})";
        break;
    case 'software':
        $item['url']   = $result->artworkUrl512;
        $item['title'] = $result->trackName;
        break;
    default:
        break;
    }

    $item['uid'] = "{$item['arg']}|{$item['hires']}";

    // Cache 100px square images for icons in results list
    if (!file_exists($_ENV['alfred_workflow_cache'])) {
        mkdir($_ENV['alfred_workflow_cache'], 0755);
    }
    $icon_path = "{$_ENV['alfred_workflow_cache']}/{$result->artistId}-{$result->collectionId}.jpg";
    if (!file_exists($icon_path)) {
        file_put_contents($icon_path, file_get_contents($result->artworkUrl100));
    }
    $item['icon'] = array("path" => $icon_path);

    // Set the QuickLook URL & copy text to the artwork URL
    $item['quicklookurl'] = $item['arg'];
    $item['text'] = array("copy" => $item['arg']);
    $items[] = $item;
}

if ($items) {
    echo json_encode(array("items" => $items));
} else {
    echo json_encode(array("items" => array(array("title" => "No results found for {$search}."))));
}