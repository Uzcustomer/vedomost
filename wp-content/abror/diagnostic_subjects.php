<?php
/**
 * HEMIS API Test - Curriculum Subjects
 * 
 * Bu skript HEMIS API dan to'g'ridan-to'g'ri ma'lumot oladi
 */

header('Content-Type: text/html; charset=utf-8');

$HEMIS_BASE  = 'https://student.ttatf.uz/rest/v1/data';
$HEMIS_TOKEN = '1iOc7IajuJCeTJ0fQR2y_31GaE-7tsUR';

function hemis_get(string $path, array $query = []): array
{
    global $HEMIS_BASE, $HEMIS_TOKEN;
    $url = rtrim($HEMIS_BASE, '/') . '/' . ltrim($path, '/');
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . $HEMIS_TOKEN,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HEMIS request error: {$err}");
    }
    
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    
    if ($code !== 200) {
        throw new RuntimeException("HEMIS HTTP error code: {$code}");
    }
    
    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException("HEMIS invalid JSON");
    }
    
    return $json;
}

echo "<pre style='font-family: monospace; font-size: 12px;'>";
echo "=================================================\n";
echo "HEMIS API TEST - CURRICULUM SUBJECTS\n";
echo "=================================================\n\n";

// 1. Curriculum-list dan 2024-2025 Davolash ishi curriculum larini topish
echo "1. CURRICULUM-LIST DAN 2024-2025 DAVOLASH ISHI TOPISH\n";
echo "-------------------------------------------------\n";

try {
    $curriculums = hemis_get('curriculum-list', ['limit' => 200]);
    
    $targetCurriculums = [];
    
    foreach ($curriculums['data']['items'] as $c) {
        // 2024-2025 yili va Davolash ishi
        $eduYear = $c['educationYear'] ?? null;
        $specialty = $c['specialty'] ?? null;
        
        if (is_array($eduYear) && is_array($specialty)) {
            $yearCode = $eduYear['code'] ?? '';
            $specName = $specialty['name'] ?? '';
            
            if ($yearCode == '2024' && strpos($specName, 'Davolash') !== false) {
                $targetCurriculums[] = [
                    'id' => $c['id'] ?? 0,
                    'name' => $c['name'] ?? '',
                    'year' => $eduYear['name'] ?? '',
                    'specialty' => $specName,
                ];
                
                echo "✓ Topildi: Curriculum #{$c['id']}: {$c['name']}\n";
                echo "  O'quv yili: {$eduYear['name']}\n";
                echo "  Yo'nalish: {$specName}\n\n";
            }
        }
    }
    
    echo "Jami topildi: " . count($targetCurriculums) . " ta curriculum\n\n";
    
    if (empty($targetCurriculums)) {
        echo "⚠ 2024-2025 Davolash ishi curriculum lari topilmadi!\n";
        exit;
    }
    
    // 2. Birinchi curriculum uchun fanlarni olish
    $testCurriculum = $targetCurriculums[0];
    $curriculumId = $testCurriculum['id'];
    
    echo "\n2. CURRICULUM #{$curriculumId} UCHUN FANLARNI OLISH\n";
    echo "-------------------------------------------------\n";
    echo "Curriculum: {$testCurriculum['name']}\n\n";
    
    $subjects = hemis_get('curriculum-subject-list', ['limit' => 200, '_curriculum' => $curriculumId]);
    
    $fanlar = [];
    
    foreach ($subjects['data']['items'] as $row) {
        $subject = $row['subject'] ?? null;
        $semester = $row['semester'] ?? null;
        
        if (is_array($subject)) {
            $fanId = $subject['id'] ?? 0;
            $fanName = $subject['name'] ?? '';
            $fanCode = $subject['code'] ?? '';
            $semesterName = is_array($semester) ? ($semester['name'] ?? '') : '';
            
            $fanlar[] = [
                'id' => $fanId,
                'name' => $fanName,
                'code' => $fanCode,
                'semester' => $semesterName,
            ];
        }
    }
    
    echo "Jami fanlar: " . count($fanlar) . " ta\n\n";
    
    // Faqat "Odam anatomiyasi" va "Gistologiya" ni ko'rsatish
    echo "3. 'ODAM ANATOMIYASI' VA 'GISTOLOGIYA' FANLARINI QIDIRISH\n";
    echo "-------------------------------------------------\n";
    
    $found = false;
    
    foreach ($fanlar as $fan) {
        if (stripos($fan['name'], 'odam anatomi') !== false || 
            stripos($fan['name'], 'gistolog') !== false) {
            
            echo "✓ TOPILDI: #{$fan['id']} - {$fan['name']}\n";
            echo "  Kod: {$fan['code']}\n";
            echo "  Semestr: {$fan['semester']}\n\n";
            
            $found = true;
        }
    }
    
    if (!$found) {
        echo "⚠ 'Odam anatomiyasi' yoki 'Gistologiya' topilmadi!\n\n";
    }
    
    // 4. Barcha fanlar ro'yxati
    echo "\n4. BARCHA FANLAR RO'YXATI (birinchi 20 ta):\n";
    echo "-------------------------------------------------\n";
    
    $count = 0;
    foreach ($fanlar as $fan) {
        if ($count >= 20) break;
        
        echo ($count + 1) . ". {$fan['name']} ({$fan['semester']})\n";
        $count++;
    }
    
    echo "\n=================================================\n";
    echo "TEST TUGADI\n";
    echo "=================================================\n";
    
} catch (Throwable $e) {
    echo "\n❌ XATO: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";