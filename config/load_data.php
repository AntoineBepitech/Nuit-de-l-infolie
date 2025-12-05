<?php
function loadPageData($pageName) {
    $jsonFile = __DIR__ . '/../data/pages.json';
    if (!file_exists($jsonFile)) {
        return null;
    }
    $json = file_get_contents($jsonFile);
    $allPages = json_decode($json, true);
    return $allPages[$pageName] ?? null;
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$data = loadPageData($currentPage);

if ($data) {
    $pageTitle = $data['title'] ?? 'NIRD';
    $headerTitle = $data['header_title'] ?? 'NIRD – Numérique Inclusif Responsable Durable';
    $menuItems = $data['menu'] ?? [];
    $sections = $data['sections'] ?? [];
    $footerText = $data['footer_text'] ?? '';
}
?>