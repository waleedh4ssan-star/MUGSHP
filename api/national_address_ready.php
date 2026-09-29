<?php
// /api/national_address.php - نسخة نهائية متوافقة مع apina.address.gov.sa
header('Content-Type: application/json; charset=utf-8');

$short = strtoupper(trim($_GET['short'] ?? $_GET['shortaddress'] ?? ''));
if (strlen($short) < 8) {
    echo json_encode(['success'=>false,'message'=>'العنوان المختصر يجب أن يكون 8 خانات مثل RRRD2929'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========= ضع مفتاحك هنا =========
$API_KEY = 'PUT_YOUR_API_KEY_HERE'; // نفسه اللي تضعه في خانة api_key في Try it
// =================================

if ($API_KEY === 'PUT_YOUR_API_KEY_HERE' || empty($API_KEY)) {
    echo json_encode([
        'success'=>false,
        'message'=>'لم تضع الـ API Key بعد. انسخ مفتاحك من بوابة المطورين وضعه في متغير API_KEY',
        'hint'=>'Products -> NationalAddress -> Subscribe -> انسخ Primary Key'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// بناء الرابط كما في الوثيقة الرسمية
$baseUrl = 'https://apina.address.gov.sa/NationalAddress/NationalAddressByShortAddress/NationalAddressByShortAddress';
$query = http_build_query([
    'format' => 'json',
    'language' => 'A', // A=عربي , E=انجليزي
    'page' => 1,
    'encode' => 'utf8',
    'shortaddress' => $short,
    // المفتاح يمكن ارساله كـ query أيضاً كاحتياط
    'api_key' => $API_KEY
]);

$url = $baseUrl . '?' . $query;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "api_key: $API_KEY", // كما في securitySchemes
    "Accept: application/json",
    "Cache-Control: no-cache"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success'=>false,'message'=>"خطأ اتصال: $curlError"], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($resp, true);

// إذا فشل json_decode جرب xml
if (!$data) {
    $xml = @simplexml_load_string($resp);
    if ($xml) $data = json_decode(json_encode($xml), true);
}

if ($httpCode === 200 && $data) {
    // الرد يأتي بأشكال مختلفة حسب الإصدار - نحاول تغطية الكل
    $addrList = $data['Addresses'] ?? $data['addresses'] ?? $data['Result'] ?? $data['result'] ?? [];
    if (isset($data['Address'])) $addrList = [$data['Address']];
    if (isset($addrList[0])) $address = $addrList[0];
    else $address = $addrList;

    // استخراج الحقول
    $city = $address['City'] ?? $address['city'] ?? $address['CityName'] ?? '';
    $district = $address['District'] ?? $address['district'] ?? $address['DistrictName'] ?? $address['Neighborhood'] ?? '';
    $street = $address['Street'] ?? $address['street'] ?? $address['StreetName'] ?? '';
    $building = $address['BuildingNumber'] ?? $address['buildingNumber'] ?? $address['BuildingNo'] ?? '';
    $zip = $address['PostCode'] ?? $address['ZipCode'] ?? $address['postCode'] ?? '';
    $additional = $address['AdditionalNumber'] ?? $address['additionalNumber'] ?? '';
    $lat = $address['Latitude'] ?? $address['latitude'] ?? $address['Lat'] ?? '';
    $lng = $address['Longitude'] ?? $address['longitude'] ?? $address['Long'] ?? '';

    echo json_encode([
        'success' => true,
        'city' => $city,
        'district' => $district,
        'street' => $street,
        'buildingNumber' => $building,
        'zipCode' => $zip,
        'additionalNumber' => $additional,
        'lat' => $lat,
        'lng' => $lng,
        'raw' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'لم يتم العثور على العنوان أو المفتاح غير مفعل',
    'http_code' => $httpCode,
    'response' => substr($resp, 0, 1500),
    'url_used' => $url
], JSON_UNESCAPED_UNICODE);
