<?php
require_once('database.php');

$options = getopt("p:c:");

function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    if ($html === false) {
        $error = curl_error($curl);
        echo "cURL error: " . $error;
        exit;
    }
    curl_close($ch);
    return $html;
}

function parseCharacteristics($xpath) {
    $characteristics = [];
    $sections = $xpath->query("//div[contains(@class, 'ProductAttributes_root__x3RlF')]//section");
    
    foreach ($sections as $section) {
        $attributes = $xpath->query(".//li[contains(@class, 'ProductAttribute_attribute__VsKnH')]", $section);

        foreach ($attributes as $attr) {
            $nameNode = $xpath->query(".//*[contains(@class, 'ProductAttribute_attributeName__2esbO')]", $attr);
            $valueNode = $xpath->query(".//*[contains(@class, 'ProductAttribute_attributeValue__ILZUV')]", $attr);

            if ($nameNode->length > 0 && $valueNode->length > 0) {
                $name = trim($nameNode[0]->nodeValue);
                $value = trim($valueNode[0]->nodeValue);
                $links = $xpath->query(".//a", $valueNode[0]);
                if ($links->length > 0) {
                    $value = trim($links[0]->nodeValue);
                }
                
                $characteristics[$name] = $value;
            }
        }
    }
    
    return $characteristics;
}

function parseProduct($url) {
    $html = fetchUrl($url);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_use_internal_errors(false);
    $xpath = new DOMXPath($dom);

    $title = $xpath->query("//h1[contains(@class, 'ProductTitle_title__pomAd')]")[0]->nodeValue ?? '';

    $price = $xpath->query("//p[contains(@class, 'ProductPrice_price__nh9mE')]")[0]->nodeValue ?? '';
    $price = preg_replace('/[^0-9]/', '', $price);

    $imageUrl = "https://4lapy.ru" . $xpath->query("//img[@sizes='100vw']")[0]->getAttribute('src') ?? '';

    $descriptionNodes = $xpath->query("//article[@class='ProductAbout_text__iNdAj']//p//text()");
    $description = trim(implode(" ", array_map(fn($node) => trim($node->nodeValue), iterator_to_array($descriptionNodes))));

    $characteristics = parseCharacteristics($xpath);

    return [
        'url' => $url,
        'image_url' => $imageUrl,
        'price' => $price,
        'title' => trim($title),
        'description' => $description,
        'characteristics' => json_encode($characteristics, JSON_UNESCAPED_UNICODE)
    ];
}

function parseCategory($url) {
    $html = fetchUrl($url);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_use_internal_errors(false);
    $xpath = new DOMXPath($dom);

    $links = [];
    $nodes = $xpath->query("//a[@class='CardProduct_link__Rg5M2']/@href");
    foreach ($nodes as $node) {
        $href = $node->nodeValue;
        $links[] = "https://4lapy.ru" . $href;
    }

    return $links;
}

function saveToCSV($data, $filename = 'products.csv') {
    $fileExists = file_exists($filename);
    $fp = fopen($filename, 'a');
    if (!$fileExists) {
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, array('URL', 'Image URL', 'Price', 'Title', 'Description', 'Characteristics'), ';');
    }
    foreach ($data as $row) {
        fputcsv($fp, mb_convert_encoding($row, 'UTF-8', 'auto'), ';');
    }
    fclose($fp);
}

$products = [];

if (!empty($options['p'])) {
    $products[] = parseProduct($options['p']);
} elseif (!empty($options['c'])) {
    $productUrls = parseCategory($options['c']);
    foreach ($productUrls as $url) {
        $products[] = parseProduct($url);
    }
}

if (!empty($products)) {
    saveToCSV($products);
    $db = new Database();
    $db->saveData($products);
    echo "Данные успешно сохранены!";
} else {
    echo "Не удалось получить данные.";
}
?>