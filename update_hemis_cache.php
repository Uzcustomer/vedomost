<?php
/**
 * update_hemis_cache.php (TUZATILGAN VERSIYA - education_year_id muammosi hal qilindi)
 */

declare(strict_types=1);
ignore_user_abort(true);
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/wp-config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✓ Database ulanish muvaffaqiyatli\n\n";
} catch (Throwable $e) {
    echo "❌ DB connection error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

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
    if (isset($json['success']) && $json['success'] === false) {
        throw new RuntimeException("HEMIS error: " . ($json['error'] ?? 'unknown'));
    }
    return $json;
}

function hemis_fetch_all(string $path, array $baseQuery = [], int $limit = 200): array
{
    $all   = [];
    $page  = 1;

    echo "  Yuklanmoqda: {$path} (limit={$limit})...\n";

    while (true) {
        $query = $baseQuery;
        $query['page']  = $page;
        $query['limit'] = $limit;

        try {
            $json = hemis_get($path, $query);
        } catch (Throwable $e) {
            echo "  ⚠ Sahifa {$page} yuklanmadi: " . $e->getMessage() . "\n";
            break;
        }

        // HEMIS struktura turiga moslashuv
        $data = $json['data'] ?? null;

        if (!$data) {
            // ma'lumot tugadi
            break;
        }

        $items = $data['items'] ?? ($data[0]['items'] ?? []);

        if (!$items || count($items) === 0) {
            // bo'sh sahifa — to‘xtash sharti
            break;
        }

        echo "  Sahifa {$page}: " . count($items) . " ta yozuv\n";

        foreach ($items as $row) {
            $all[] = $row;
        }

        // Limit sahifa to‘la bo‘lsa — keyingi sahifaga o‘tadi
        if (count($items) < $limit) {
            // API oxirgi sahifaga keldi
            break;
        }

        $page++;
    }

    echo "  Jami yuklandi: " . count($all) . " ta yozuv\n\n";
    return $all;
}


// HTML ko'rinish uchun
echo "<pre>";
echo "========================================\n";
echo "HEMIS CACHE YANGILASH BOSHLANDI\n";
echo "========================================\n\n";

// =====================================================================
// BOSQICH 1: STUDENT-LIST
// =====================================================================

echo "BOSQICH 1: STUDENT-LIST MA'LUMOTLARINI YUPLASH\n";
echo "----------------------------------------\n";

try {
    $students = hemis_fetch_all('student-list', ['limit' => 200]);
} catch (Throwable $e) {
    echo "❌ XATO: " . $e->getMessage() . "\n";
    exit;
}

echo "Talabalar soni: " . count($students) . "\n\n";

if (empty($students)) {
    echo "⚠ DIQQAT: Talabalar ro'yxati bo'sh!\n";
    exit;
}

$educationTypes = [];
$years          = [];
$departments    = [];
$specialties    = [];
$levels         = [];
$semesters      = [];
$groups         = [];
$currentSemesters = [];

// =====================================================================
// BOSQICH 1.5: GROUP-LIST (QO'SHIMCHA)
// =====================================================================

echo "BOSQICH 1.5: GROUP-LIST MA'LUMOTLARINI YUPLASH\n";
echo "----------------------------------------\n";

try {
    $groupsList = hemis_fetch_all('group-list', ['limit' => 200]);
    echo "Guruhlar soni (group-list dan): " . count($groupsList) . "\n\n";
    
    // group-list dan olingan guruhlarni saqlash
    $groupsFromAPI = [];
    foreach ($groupsList as $grp) {
        // group-list strukturasi
        $items = $grp['items'] ?? [$grp]; // Ba'zida items ichida, ba'zida to'g'ridan-to'g'ri
        if (!is_array($items)) {
            $items = [$grp];
        }
        
        foreach ($items as $item) {
            $gId = (int)($item['id'] ?? 0);
            $gName = (string)($item['name'] ?? '');
            
            if ($gId > 0 && $gName !== '') {
                // Guruh ma'lumotlarini saqlash
                $dept = $item['department'] ?? null;
                $spec = $item['specilaty'] ?? null; // API da "specilaty" deb yozilgan (typo)
                $curr = $item['_curriculum'] ?? 0;
                
                $deptId = is_array($dept) ? (int)($dept['id'] ?? 0) : 0;
                $specId = is_array($spec) ? (int)($spec['id'] ?? 0) : 0;
                
                $groupsFromAPI[$gId] = [
                    'id'              => $gId,
                    'name'            => $gName,
                    'department_id'   => $deptId,
                    'specialty_id'    => $specId,
                    'curriculum_id'   => $curr,
                ];
            }
        }
    }
    echo "group-list dan yuklangan guruhlar: " . count($groupsFromAPI) . "\n\n";
} catch (Throwable $e) {
    echo "⚠ DIQQAT: group-list yuklanmadi: " . $e->getMessage() . "\n";
    echo "Student-list dan olingan ma'lumotlar bilan davom etiladi.\n\n";
    $groupsFromAPI = [];
}

// =====================================================================
// Student-list dan ma'lumotlarni yig'ish
// =====================================================================

echo "Student-list ma'lumotlarini tahlil qilish...\n";

foreach ($students as $st) {
    // Ta'lim turi
    $educationType = $st['educationType'] ?? null;
    if (is_array($educationType)) {
        $etCode = (string)($educationType['code'] ?? '');
        $etName = (string)($educationType['name'] ?? '');
        if ($etCode !== '' && $etName !== '') {
            if (!isset($educationTypes[$etCode])) {
                $educationTypes[$etCode] = [
                    'code' => $etCode,
                    'name' => $etName,
                ];
            }
        }
    }

    // O'quv yili
    $educationYear = $st['educationYear'] ?? null;
    if (is_array($educationYear)) {
        $yCode = (string)($educationYear['code'] ?? '');
        $yName = (string)($educationYear['name'] ?? '');
        $yCur  = (bool)($educationYear['current'] ?? false);
        
        if ($yCode !== '' && $yName !== '') {
            if (!isset($years[$yCode])) {
                $years[$yCode] = [
                    'code'    => $yCode,
                    'name'    => $yName,
                    'current' => $yCur,
                ];
            } else {
                if ($yCur && !$years[$yCode]['current']) {
                    $years[$yCode]['current'] = true;
                }
            }
        }
    } else {
        $yCode = '';
    }

    // Fakultet
    $department = $st['department'] ?? null;
    if (is_array($department)) {
        $depId   = (int)($department['id'] ?? 0);
        $depName = (string)($department['name'] ?? '');
        if ($depId > 0 && $yCode !== '' && $depName !== '') {
            $key = $yCode . ':' . $depId;
            if (!isset($departments[$key])) {
                $departments[$key] = [
                    'year_code' => $yCode,
                    'hemis_id'  => $depId,
                    'name'      => $depName,
                ];
            }
        }
    } else {
        $depId = 0;
    }

    // Yo'nalish
    $specialty = $st['specialty'] ?? null;
    if (is_array($specialty)) {
        $specId   = (int)($specialty['id'] ?? 0);
        $specName = (string)($specialty['name'] ?? '');
        if ($specId > 0 && $depId > 0 && $specName !== '') {
            $k = $depId . ':' . $specId;
            if (!isset($specialties[$k])) {
                $specialties[$k] = [
                    'hemis_id'      => $specId,
                    'department_id' => $depId,
                    'name'          => $specName,
                ];
            }
        }
    } else {
        $specId = 0;
    }

    // Kurs
    $level = $st['level'] ?? null;
    if (is_array($level)) {
        $levelCode = (string)($level['code'] ?? '');
        $levelName = (string)($level['name'] ?? '');
        if ($levelCode !== '' && $levelName !== '') {
            if (!isset($levels[$levelCode])) {
                $levels[$levelCode] = [
                    'code' => $levelCode,
                    'name' => $levelName,
                ];
            }
        }
    } else {
        $levelCode = '';
    }

    // Semestr
    $semester = $st['semester'] ?? null;
    if (is_array($semester)) {
        $semCode = (string)($semester['code'] ?? '');
        $semName = (string)($semester['name'] ?? '');
        if ($semCode !== '' && $semName !== '') {
            if (!isset($semesters[$semCode])) {
                $semesters[$semCode] = [
                    'code' => $semCode,
                    'name' => $semName,
                ];
            }
        }
    } else {
        $semCode = '';
    }

    // Guruh
    $group = $st['group'] ?? null;
    if (is_array($group)) {
        $groupHemisId = (int)($group['id'] ?? 0);
        $groupCode = (string)($group['name'] ?? '');
        
        // Agar student-list da ID bo'lmasa, group-list dan topishga harakat qilish
        if ($groupHemisId === 0 && $groupCode !== '' && !empty($groupsFromAPI)) {
            foreach ($groupsFromAPI as $apiGroup) {
                if ($apiGroup['name'] === $groupCode && $apiGroup['department_id'] === $depId) {
                    $groupHemisId = $apiGroup['id'];
                    break;
                }
            }
        }
        
        if ($groupCode !== '' && $groupHemisId > 0) {
            $gKey = $yCode . ':' . $depId . ':' . $specId . ':' . $levelCode . ':' . $semCode . ':' . $groupCode;
            if (!isset($groups[$gKey])) {
                $groups[$gKey] = [
                    'hemis_id'      => $groupHemisId,
                    'year_code'     => $yCode,
                    'department_id' => $depId,
                    'specialty_id'  => $specId,
                    'level_code'    => $levelCode,
                    'semester_code' => $semCode,
                    'name'          => $groupCode,
                ];
            }
        }
    }

    // Joriy semestr
    if ($yCode !== '' && $levelCode !== '' && $semCode !== '') {
        $csKey = $yCode . ':' . $levelCode . ':' . $semCode;
        if (!isset($currentSemesters[$csKey])) {
            $currentSemesters[$csKey] = [
                'year_code'     => $yCode,
                'level_code'    => $levelCode,
                'semester_code' => $semCode,
            ];
        }
    }
}

echo "Ta'lim turlari: " . count($educationTypes) . "\n";
echo "O'quv yillari: " . count($years) . "\n";
echo "Fakultetlar: " . count($departments) . "\n";
echo "Yo'nalishlar: " . count($specialties) . "\n";
echo "Kurslar: " . count($levels) . "\n";
echo "Semestrlar: " . count($semesters) . "\n";
echo "Guruhlar: " . count($groups) . "\n";
echo "Joriy semestrlar: " . count($currentSemesters) . "\n\n";

// =====================================================================
// BOSQICH 2: CURRICULUM VA FANLAR
// =====================================================================

echo "BOSQICH 2: CURRICULUM VA FANLAR\n";
echo "----------------------------------------\n";

echo "HEMIS curriculum-list yuklanmoqda...\n";
try {
    $curricula = hemis_fetch_all('curriculum-list', ['limit' => 200]);
} catch (Throwable $e) {
    echo "❌ XATO: " . $e->getMessage() . "\n";
    exit;
}
echo "Curriculum soni: " . count($curricula) . "\n";

// YANGI: Curriculum-list dan ham o'quv yillarini yig'ish
echo "Curriculum-list dan o'quv yillarini qo'shimcha yig'ish...\n";
$additionalYearsFound = 0;
foreach ($curricula as $c) {
    $educationYear = $c['educationYear'] ?? null;
    if (is_array($educationYear)) {
        $yCode = (string)($educationYear['code'] ?? '');
        $yName = (string)($educationYear['name'] ?? '');
        // current qiymati bo'lmasligi mumkin, shuning uchun false qilamiz
        $yCur = (bool)($educationYear['current'] ?? false);
        
        if ($yCode !== '' && $yName !== '') {
            if (!isset($years[$yCode])) {
                $years[$yCode] = [
                    'code'    => $yCode,
                    'name'    => $yName,
                    'current' => $yCur,
                ];
                $additionalYearsFound++;
            } else {
                // Agar current true bo'lsa, yangilash
                if ($yCur && !$years[$yCode]['current']) {
                    $years[$yCode]['current'] = true;
                }
            }
        }
    }
}
echo "Qo'shimcha topilgan o'quv yillari: {$additionalYearsFound}\n";
echo "Jami o'quv yillari: " . count($years) . "\n\n";

echo "HEMIS curriculum-subject-list yuklanmoqda...\n";
try {
    $currSubjects = hemis_fetch_all('curriculum-subject-list', ['limit' => 200]);
} catch (Throwable $e) {
    echo "❌ XATO: " . $e->getMessage() . "\n";
    exit;
}
echo "Curriculum-subject yozuvlar soni: " . count($currSubjects) . "\n\n";

// =========================================================================
    // BOSQICH 3: STUDENT-SUBJECT-LIST
    // =========================================================================
    
    echo "BOSQICH 3: STUDENT-SUBJECT-LIST\n";
    echo "----------------------------------------\n";
    
    echo "HEMIS student-subject-list yuklanmoqda...\n";
    try {
        $studentSubjects = hemis_fetch_all('student-subject-list', ['limit' => 200]);
    } catch (Throwable $e) {
        echo "❌ XATO: " . $e->getMessage() . "\n";
        $studentSubjects = [];
    }
    echo "Student-Subject yozuvlar soni: " . count($studentSubjects) . "\n\n";
    
    // Student-subject ma'lumotlarini saqlash
    if (!empty($studentSubjects)) {
        $pdo->beginTransaction();
        
        echo "hemis_student_subjects jadvalini yangilash...\n";
        
        // Eski ma'lumotlarni o'chirish (ixtiyoriy)
        // $pdo->exec("DELETE FROM hemis_student_subjects");
        
        $insStudentSubject = $pdo->prepare("
            INSERT INTO hemis_student_subjects 
                (student_hemis_id, group_hemis_id, subject_hemis_id, curriculum_hemis_id, 
                 education_year_hemis_id, semester_hemis_id, 
                 group_id, subject_id, curriculum_id, education_year_id, semester_id, 
                 position, active, created_at, updated_at)
            VALUES 
                (:student_hemis_id, :group_hemis_id, :subject_hemis_id, :curriculum_hemis_id,
                 :education_year_hemis_id, :semester_hemis_id,
                 :group_id, :subject_id, :curriculum_id, :education_year_id, :semester_id,
                 :position, :active, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE
                group_hemis_id = VALUES(group_hemis_id),
                curriculum_hemis_id = VALUES(curriculum_hemis_id),
                education_year_hemis_id = VALUES(education_year_hemis_id),
                semester_hemis_id = VALUES(semester_hemis_id),
                group_id = VALUES(group_id),
                subject_id = VALUES(subject_id),
                curriculum_id = VALUES(curriculum_id),
                education_year_id = VALUES(education_year_id),
                semester_id = VALUES(semester_id),
                position = VALUES(position),
                active = VALUES(active),
                updated_at = VALUES(updated_at)
        ");
        
        $ssCount = 0;
        $ssSkipped = 0;
        $ssSkipReasons = [
            'no_student' => 0,
            'no_subject' => 0,
            'group_not_found' => 0,
            'subject_not_found' => 0,
            'duplicate' => 0,
            'other_error' => 0,
        ];
        
        foreach ($studentSubjects as $ss) {
            // API dan kelgan HEMIS ID lar
            $studentHemisId = (int)($ss['_student'] ?? 0);
            $groupHemisId = (int)($ss['_group'] ?? 0);
            $subjectHemisId = (int)($ss['_subject'] ?? 0);
            $curriculumHemisId = (int)($ss['_curriculum'] ?? 0);
            $yearHemisId = (int)($ss['_education_year'] ?? 0);
            $semesterHemisId = (int)($ss['_semester'] ?? 0);
            $position = (int)($ss['position'] ?? 0);
            $active = ($ss['active'] === 'ИСТИНА' || $ss['active'] === true || $ss['active'] === 1) ? 1 : 0;
            $createdAt = (int)($ss['created_at'] ?? 0);
            $updatedAt = (int)($ss['updated_at'] ?? 0);
            
            // Majburiy maydonlar tekshiruvi
            if ($studentHemisId === 0) {
                $ssSkipped++;
                $ssSkipReasons['no_student']++;
                continue;
            }
            
            if ($subjectHemisId === 0) {
                $ssSkipped++;
                $ssSkipReasons['no_subject']++;
                continue;
            }
            
            // Local ID larni topish
            
            // 1. Group ID
            $groupId = null;
            if ($groupHemisId > 0) {
                $stmtGroup = $pdo->prepare("SELECT id FROM hemis_groups WHERE hemis_id = ? LIMIT 1");
                $stmtGroup->execute([$groupHemisId]);
                $groupRow = $stmtGroup->fetch();
                if ($groupRow) {
                    $groupId = $groupRow['id'];
                }
            }
            
            // 2. Subject ID
            $subjectId = null;
            $stmtSubject = $pdo->prepare("SELECT id FROM hemis_subjects WHERE hemis_id = ? LIMIT 1");
            $stmtSubject->execute([$subjectHemisId]);
            $subjectRow = $stmtSubject->fetch();
            if ($subjectRow) {
                $subjectId = $subjectRow['id'];
            } else {
                $ssSkipped++;
                $ssSkipReasons['subject_not_found']++;
                continue;
            }
            
            // 3. Curriculum ID
            $curriculumId = null;
            if ($curriculumHemisId > 0) {
                $stmtCurr = $pdo->prepare("SELECT id FROM hemis_curriculums WHERE hemis_id = ? LIMIT 1");
                $stmtCurr->execute([$curriculumHemisId]);
                $currRow = $stmtCurr->fetch();
                if ($currRow) {
                    $curriculumId = $currRow['id'];
                }
            }
            
            // 4. Education Year ID
            $educationYearId = null;
            if ($yearHemisId > 0) {
                $stmtYear = $pdo->prepare("SELECT id FROM hemis_education_years WHERE hemis_id = ? LIMIT 1");
                $stmtYear->execute([$yearHemisId]);
                $yearRow = $stmtYear->fetch();
                if ($yearRow) {
                    $educationYearId = $yearRow['id'];
                }
            }
            
            // 5. Semester ID
            $semesterId = null;
            if ($semesterHemisId > 0) {
                $stmtSem = $pdo->prepare("SELECT id FROM hemis_semesters WHERE hemis_id = ? LIMIT 1");
                $stmtSem->execute([$semesterHemisId]);
                $semRow = $stmtSem->fetch();
                if ($semRow) {
                    $semesterId = $semRow['id'];
                }
            }
            
            // Timestamps (Unix timestamp -> DateTime)
            $createdAtDate = $createdAt > 0 ? date('Y-m-d H:i:s', $createdAt) : null;
            $updatedAtDate = $updatedAt > 0 ? date('Y-m-d H:i:s', $updatedAt) : null;
            
            // Bazaga saqlash
            try {
                $result = $insStudentSubject->execute([
                    ':student_hemis_id' => $studentHemisId,
                    ':group_hemis_id' => $groupHemisId > 0 ? $groupHemisId : null,
                    ':subject_hemis_id' => $subjectHemisId,
                    ':curriculum_hemis_id' => $curriculumHemisId > 0 ? $curriculumHemisId : null,
                    ':education_year_hemis_id' => $yearHemisId > 0 ? $yearHemisId : null,
                    ':semester_hemis_id' => $semesterHemisId > 0 ? $semesterHemisId : null,
                    ':group_id' => $groupId,
                    ':subject_id' => $subjectId,
                    ':curriculum_id' => $curriculumId,
                    ':education_year_id' => $educationYearId,
                    ':semester_id' => $semesterId,
                    ':position' => $position > 0 ? $position : null,
                    ':active' => $active,
                    ':created_at' => $createdAtDate,
                    ':updated_at' => $updatedAtDate,
                ]);
                
                if ($result) {
                    $ssCount++;
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $ssSkipped++;
                    $ssSkipReasons['duplicate']++;
                } else {
                    $ssSkipped++;
                    $ssSkipReasons['other_error']++;
                    if ($ssSkipReasons['other_error'] <= 3) {
                        echo "   ⚠ Xato (student:{$studentHemisId}, subject:{$subjectHemisId}): " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        
        $pdo->commit();
        
        echo "   ✓ {$ssCount} ta student-subject saqlandi";
        if ($ssSkipped > 0) {
            echo " (o'tkazib yuborilgan: {$ssSkipped})";
            echo "\n\n   Skip sabablari:";
            if ($ssSkipReasons['no_student'] > 0) {
                echo "\n   - Student ID yo'q: " . $ssSkipReasons['no_student'];
            }
            if ($ssSkipReasons['no_subject'] > 0) {
                echo "\n   - Subject ID yo'q: " . $ssSkipReasons['no_subject'];
            }
            if ($ssSkipReasons['group_not_found'] > 0) {
                echo "\n   - Guruh topilmadi: " . $ssSkipReasons['group_not_found'];
            }
            if ($ssSkipReasons['subject_not_found'] > 0) {
                echo "\n   - Fan topilmadi: " . $ssSkipReasons['subject_not_found'];
            }
            if ($ssSkipReasons['duplicate'] > 0) {
                echo "\n   - Duplicate (takroriy): " . $ssSkipReasons['duplicate'];
            }
            if ($ssSkipReasons['other_error'] > 0) {
                echo "\n   - Boshqa xatoliklar: " . $ssSkipReasons['other_error'];
            }
        }
        echo "\n\n";
    } else {
        echo "⚠ Student-Subject ma'lumotlari topilmadi\n\n";
    }




// =====================================================================
// BOSQICH 3: MA'LUMOTLARNI SAQLASH
// =====================================================================

echo "BOSQICH 3: MA'LUMOTLARNI SAQLASH\n";
echo "----------------------------------------\n";

try {
    $pdo->beginTransaction();

    // Jadval strukturasini tekshirish
    echo "Jadval strukturasini tekshirish...\n";
    
    // hemis_subjects ga index qo'shish (agar yo'q bo'lsa)
    try {
        // Avval index mavjudligini tekshiramiz
        $stmt = $pdo->query("SHOW INDEX FROM hemis_subjects WHERE Key_name = 'idx_education_type_id'");
        $indexExists = $stmt->fetch();
        
        if (!$indexExists) {
            $pdo->exec("ALTER TABLE hemis_subjects ADD INDEX idx_education_type_id (education_type_id)");
            echo "   ✓ Index qo'shildi\n";
        } else {
            echo "   ✓ Index allaqachon mavjud\n";
        }
    } catch (PDOException $e) {
        echo "   ⚠ Index tekshirishda xato: " . $e->getMessage() . "\n";
    }

    // =========================================================================
    // 1. hemis_education_types
    // =========================================================================
    echo "\n1. hemis_education_types yangilanmoqda...\n";
    $pdo->exec("DELETE FROM hemis_education_types");
    $insEdType = $pdo->prepare("
        INSERT INTO hemis_education_types (hemis_id, name) 
        VALUES (:hemis_id, :name)
    ");
    
    $eduTypeMap = [];
    foreach ($educationTypes as $et) {
        $insEdType->execute([
            ':hemis_id' => $et['code'],
            ':name'     => $et['name'],
        ]);
        $eduTypeMap[$et['code']] = (int)$pdo->lastInsertId();
    }
    echo "   ✓ " . count($educationTypes) . " ta ta'lim turi saqlandi\n\n";

    // =========================================================================
    // 2. hemis_education_years
    // =========================================================================
    echo "2. hemis_education_years yangilanmoqda...\n";
    $pdo->exec("DELETE FROM hemis_education_years");
    $insYear = $pdo->prepare("
        INSERT INTO hemis_education_years (hemis_id, name, `current`) 
        VALUES (:hemis_id, :name, :current)
    ");
    
    $yearMap = [];
    foreach ($years as $y) {
        $insYear->execute([
            ':hemis_id' => $y['code'],
            ':name'     => $y['name'],
            ':current'  => $y['current'] ? 1 : 0,
        ]);
        $yearMap[$y['code']] = (int)$pdo->lastInsertId();
    }
    echo "   ✓ " . count($years) . " ta o'quv yili saqlandi\n\n";

    // =========================================================================
    // 3. hemis_departments
    // =========================================================================
    echo "3. hemis_departments yangilanmoqda...\n";
    $pdo->exec("DELETE FROM hemis_departments");
    $insDept = $pdo->prepare("
        INSERT IGNORE INTO hemis_departments (hemis_id, education_year_id, name) 
        VALUES (:hemis_id, :education_year_id, :name)
    ");
    
    $deptMap = [];
    $deptCount = 0;
    $deptSkipped = 0;
    
    foreach ($departments as $d) {
        $yearCode = $d['year_code'];
        $year_id = $yearMap[$yearCode] ?? null;
        if (!$year_id) {
            $deptSkipped++;
            continue;
        }
        
        try {
            $insDept->execute([
                ':hemis_id'         => $d['hemis_id'],
                ':education_year_id'=> $year_id,
                ':name'             => $d['name'],
            ]);
            $deptMap[$d['hemis_id']] = (int)$pdo->lastInsertId();
            $deptCount++;
        } catch (PDOException $e) {
            $deptSkipped++;
        }
    }
    echo "   ✓ {$deptCount} ta fakultet saqlandi";
    if ($deptSkipped > 0) echo " (tashlab ketilgan: {$deptSkipped})";
    echo "\n\n";

    // =========================================================================
    // 4. hemis_specialties
    // =========================================================================
    echo "4. hemis_specialties yangilanmoqda...\n";
    $pdo->exec("DELETE FROM hemis_specialties");
    $insSpec = $pdo->prepare("
        INSERT IGNORE INTO hemis_specialties (hemis_id, department_id, name) 
        VALUES (:hemis_id, :department_id, :name)
    ");
    
    $specMap = [];
    $specCount = 0;
    $specSkipped = 0;
    
    foreach ($specialties as $s) {
        $dept_id = $deptMap[$s['department_id']] ?? null;
        if (!$dept_id) {
            $specSkipped++;
            continue;
        }
        
        try {
            $insSpec->execute([
                ':hemis_id'      => $s['hemis_id'],
                ':department_id' => $dept_id,
                ':name'          => $s['name'],
            ]);
            $specMap[$s['hemis_id']] = (int)$pdo->lastInsertId();
            $specCount++;
        } catch (PDOException $e) {
            $specSkipped++;
        }
    }
    echo "   ✓ {$specCount} ta yo'nalish saqlandi";
    if ($specSkipped > 0) echo " (tashlab ketilgan: {$specSkipped})";
    echo "\n\n";

    // =========================================================================
    // 5. hemis_curriculums (TUZATILGAN - education_year_id to'g'ri saqlanadi)
    // =========================================================================
    echo "5. hemis_curriculums yangilanmoqda (TUZATILGAN)...\n";
    $pdo->exec("DELETE FROM hemis_curriculums");
    $insCurr = $pdo->prepare("
        INSERT IGNORE INTO hemis_curriculums 
            (hemis_id, education_type_id, education_year_id, department_id, specialty_id, name) 
        VALUES 
            (:hemis_id, :education_type_id, :education_year_id, :department_id, :specialty_id, :name)
    ");
    
    $currMap = [];
    $currCount = 0;
    $currSkipped = 0;
    
    foreach ($curricula as $c) {
        $hemisId = (int)($c['id'] ?? 0);
        $name = (string)($c['name'] ?? '');
        
        if ($hemisId === 0 || $name === '') {
            $currSkipped++;
            continue;
        }
        
        // ===== ASOSIY TUZATISH: education_year_id ni to'g'ri olish =====
        $eduTypeId = null;
        $eduYearId = null;
        $deptId = null;
        $specId = null;
        
        // Ta'lim turi
        if (isset($c['educationType']) && is_array($c['educationType'])) {
            $etCode = (string)($c['educationType']['code'] ?? '');
            $eduTypeId = $eduTypeMap[$etCode] ?? null;
        }
        
        // O'quv yili - BU YERDA MUAMMO BOR EDI!
        if (isset($c['educationYear']) && is_array($c['educationYear'])) {
            $yearCode = (string)($c['educationYear']['code'] ?? '');
            $eduYearId = $yearMap[$yearCode] ?? null;
            
            // DEBUG: Agar topilmasa, xabar berish
            if (!$eduYearId && $yearCode !== '') {
                echo "   ⚠ Curriculum #{$hemisId} uchun o'quv yili topilmadi (code: {$yearCode})\n";
            }
        }
        
        // Fakultet
        if (isset($c['department']) && is_array($c['department'])) {
            $deptHemisId = (int)($c['department']['id'] ?? 0);
            $deptId = $deptMap[$deptHemisId] ?? null;
        }
        
        // Yo'nalish
        if (isset($c['specialty']) && is_array($c['specialty'])) {
            $specHemisId = (int)($c['specialty']['id'] ?? 0);
            $specId = $specMap[$specHemisId] ?? null;
        }
        
        try {
            $insCurr->execute([
                ':hemis_id'          => $hemisId,
                ':education_type_id' => $eduTypeId,
                ':education_year_id' => $eduYearId,  // ← BU YERDA NULL EMASga aylanishi kerak edi
                ':department_id'     => $deptId,
                ':specialty_id'      => $specId,
                ':name'              => $name,
            ]);
            $currMap[$hemisId] = (int)$pdo->lastInsertId();
            $currCount++;
        } catch (PDOException $e) {
            $currSkipped++;
            echo "   ⚠ Curriculum #{$hemisId} saqlanmadi: " . $e->getMessage() . "\n";
        }
    }
    
    echo "   ✓ {$currCount} ta curriculum saqlandi";
    if ($currSkipped > 0) echo " (tashlab ketilgan: {$currSkipped})";
    echo "\n\n";

    // =========================================================================
    // 6. hemis_levels
    // =========================================================================
    echo "6. hemis_levels yangilanmoqda...\n";
    $pdo->exec("DELETE FROM hemis_levels");
    $insLevel = $pdo->prepare("
        INSERT INTO hemis_levels (hemis_id, name) 
        VALUES (:hemis_id, :name)
    ");
    
    $levelMap = [];
    foreach ($levels as $l) {
        $insLevel->execute([
            ':hemis_id' => $l['code'],
            ':name'     => $l['name'],
        ]);
        $levelMap[$l['code']] = (int)$pdo->lastInsertId();
    }
    echo "   ✓ " . count($levels) . " ta kurs saqlandi\n\n";

    // =========================================================================
    // 7. hemis_semesters
    // =========================================================================
    echo "7. hemis_semesters yangilanmoqda...\n";
    $pdo->exec("DELETE FROM hemis_semesters");
    $insSem = $pdo->prepare("
        INSERT INTO hemis_semesters (hemis_id, name) 
        VALUES (:hemis_id, :name)
    ");
    
    $semMap = [];
    foreach ($semesters as $s) {
        $insSem->execute([
            ':hemis_id' => $s['code'],
            ':name'     => $s['name'],
        ]);
        $semMap[$s['code']] = (int)$pdo->lastInsertId();
    }
    echo "   ✓ " . count($semesters) . " ta semestr saqlandi\n\n";

    // =========================================================================
    // 8. hemis_groups
    // =========================================================================
    echo "8. hemis_groups yangilanmoqda...\n";
    $pdo->exec("DELETE FROM hemis_groups");
    $insGroup = $pdo->prepare("
        INSERT IGNORE INTO hemis_groups 
            (education_type_id, education_year_id, department_id, specialty_id, level_id, semester_id, hemis_id, name)
        VALUES
            (:education_type_id, :education_year_id, :department_id, :specialty_id, :level_id, :semester_id, :hemis_id, :name)
    ");
    
    $groupCount = 0;
    $groupSkipped = 0;
    $groupSkipReasons = [
        'no_mapping' => 0,
        'no_hemis_id' => 0,
        'db_error' => 0,
    ];
    
    foreach ($groups as $g) {
        $yearCode = $g['year_code'];
        $levelCode = $g['level_code'];
        $semCode = $g['semester_code'];
        $deptHemisId = $g['department_id'];
        $specHemisId = $g['specialty_id'];
        $groupHemisId = $g['hemis_id'] ?? 0;
        
        // Agar HEMIS ID topilmasa, skip qilish
        if ($groupHemisId === 0) {
            $groupSkipped++;
            $groupSkipReasons['no_hemis_id']++;
            continue;
        }
        
        $year_id = $yearMap[$yearCode] ?? null;
        $level_id = $levelMap[$levelCode] ?? null;
        $sem_id = $semMap[$semCode] ?? null;
        $dept_id = $deptMap[$deptHemisId] ?? null;
        $spec_id = $specMap[$specHemisId] ?? null;
        
        if (!$year_id || !$level_id || !$sem_id || !$dept_id || !$spec_id) {
            $groupSkipped++;
            $groupSkipReasons['no_mapping']++;
            continue;
        }
        
        $eduTypeId = null;
        
        try {
            $insGroup->execute([
                ':education_type_id' => $eduTypeId,
                ':education_year_id' => $year_id,
                ':department_id'     => $dept_id,
                ':specialty_id'      => $spec_id,
                ':level_id'          => $level_id,
                ':semester_id'       => $sem_id,
                ':hemis_id'          => $groupHemisId,
                ':name'              => $g['name'],
            ]);
            $groupCount++;
        } catch (PDOException $e) {
            $groupSkipped++;
            $groupSkipReasons['db_error']++;
        }
    }
    echo "   ✓ {$groupCount} ta guruh saqlandi";
    if ($groupSkipped > 0) {
        echo " (o'tkazib yuborilgan: {$groupSkipped})";
        echo "\n   Skip sabablari:";
        if ($groupSkipReasons['no_hemis_id'] > 0) {
            echo "\n   - HEMIS ID topilmadi: " . $groupSkipReasons['no_hemis_id'];
        }
        if ($groupSkipReasons['no_mapping'] > 0) {
            echo "\n   - Mapping topilmadi: " . $groupSkipReasons['no_mapping'];
        }
        if ($groupSkipReasons['db_error'] > 0) {
            echo "\n   - Database xato: " . $groupSkipReasons['db_error'];
        }
    }
    echo "\n\n";

    // =========================================================================
    // 9. hemis_current_semesters
    // =========================================================================
    echo "9. hemis_current_semesters yangilanmoqda...\n";
    $pdo->exec("DELETE FROM hemis_current_semesters");
    $insCurrentSem = $pdo->prepare("
        INSERT INTO hemis_current_semesters 
            (education_year_id, level_id, semester_id)
        VALUES
            (:education_year_id, :level_id, :semester_id)
    ");
    
    $currentSemCount = 0;
    $currentSemSkipped = 0;
    
    foreach ($currentSemesters as $cs) {
        $yearCode = $cs['year_code'];
        $levelCode = $cs['level_code'];
        $semCode = $cs['semester_code'];
        
        $year_id = $yearMap[$yearCode] ?? null;
        $level_id = $levelMap[$levelCode] ?? null;
        $sem_id = $semMap[$semCode] ?? null;
        
        if (!$year_id || !$level_id || !$sem_id) {
            $currentSemSkipped++;
            continue;
        }
        
        try {
            $insCurrentSem->execute([
                ':education_year_id' => $year_id,
                ':level_id'          => $level_id,
                ':semester_id'       => $sem_id,
            ]);
            $currentSemCount++;
        } catch (PDOException $e) {
            $currentSemSkipped++;
        }
    }
    
    echo "   ✓ {$currentSemCount} ta joriy semestr saqlandi";
    if ($currentSemSkipped > 0) echo " (tashlab ketilgan: {$currentSemSkipped})";
    echo "\n\n";

    // =========================================================================
    // 10. hemis_subjects (YANGI STRUKTURA)
    // =========================================================================
    echo "10. hemis_subjects yangilanmoqda (YANGI STRUKTURA)...\n";
    
    $pdo->exec("DELETE FROM hemis_subjects");
    
    $insSubj = $pdo->prepare("
        INSERT INTO hemis_subjects
            (hemis_id, curriculum_id, education_type_id, education_year_id, 
             department_id, specialty_id, level_id, semester_id, semester_name,
             name, short_name, active, at_semester,
             curriculum_name, education_type_name, education_year_name,
             department_name, specialty_name, level_name)
        VALUES
            (:hemis_id, :curriculum_id, :education_type_id, :education_year_id,
             :department_id, :specialty_id, :level_id, :semester_id, :semester_name,
             :name, :short_name, :active, :at_semester,
             :curriculum_name, :education_type_name, :education_year_name,
             :department_name, :specialty_name, :level_name)
    ");

    $subjCount = 0;
    $subjSkipped = 0;
    $skipReasons = [
        'no_subject_or_curriculum' => 0,
        'no_hemis_id_or_name' => 0,
        'curriculum_not_mapped' => 0,
        'duplicate_entry' => 0,
        'other_error' => 0,
    ];

    foreach ($currSubjects as $row) {
        $subject    = $row['subject']    ?? null;
        $semester   = $row['semester']   ?? null;
        $currId     = $row['_curriculum'] ?? null;
        $active     = $row['active']     ?? null;
        $atSemester = $row['at_semester'] ?? null;
        
        if (!is_array($subject) || !$currId) {
            $subjSkipped++;
            $skipReasons['no_subject_or_curriculum']++;
            continue;
        }

        $hemisId   = (int)($subject['id'] ?? 0);
        $name      = (string)($subject['name'] ?? '');
        $shortName = (string)($subject['code'] ?? '');

        if ($hemisId === 0 || $name === '') {
            $subjSkipped++;
            $skipReasons['no_hemis_id_or_name']++;
            continue;
        }

        // Curriculum ma'lumotlari
        $localCurrId = $currMap[$currId] ?? null;
        if (!$localCurrId) {
            $subjSkipped++;
            $skipReasons['curriculum_not_mapped']++;
            continue;
        }

        // Curriculum to'liq ma'lumotlari
        $currData = null;
        foreach ($curricula as $c) {
            if (($c['id'] ?? 0) == $currId) {
                $currData = $c;
                break;
            }
        }
        
        // YANGI: Barcha qo'shimcha ma'lumotlarni olish
        $eduTypeId = null;
        $eduYearId = null;
        $deptId = null;
        $specId = null;
        $levelId = null;
        
        $currName = '';
        $eduTypeName = '';
        $eduYearName = '';
        $deptName = '';
        $specName = '';
        $levelName = '';

        if ($currData) {
            // Curriculum nomi
            $currName = $currData['name'] ?? '';
            
            // Ta'lim turi
            if (isset($currData['educationType']) && is_array($currData['educationType'])) {
                $eduTypeCode = (string)($currData['educationType']['code'] ?? '');
                $eduTypeId = $eduTypeMap[$eduTypeCode] ?? null;
                $eduTypeName = $currData['educationType']['name'] ?? '';
            }
            
            // O'quv yili - TUZATILGAN
            if (isset($currData['educationYear']) && is_array($currData['educationYear'])) {
                $yearCode = (string)($currData['educationYear']['code'] ?? '');
                $eduYearId = $yearMap[$yearCode] ?? null;
                $eduYearName = $currData['educationYear']['name'] ?? '';
            }
            
            // Fakultet
            if (isset($currData['department']) && is_array($currData['department'])) {
                $deptHemisId = (int)($currData['department']['id'] ?? 0);
                $deptId = $deptMap[$deptHemisId] ?? null;
                $deptName = $currData['department']['name'] ?? '';
            }
            
            // Yo'nalish
            if (isset($currData['specialty']) && is_array($currData['specialty'])) {
                $specHemisId = (int)($currData['specialty']['id'] ?? 0);
                $specId = $specMap[$specHemisId] ?? null;
                $specName = $currData['specialty']['name'] ?? '';
            }
            
            // Kurs (level)
            if (isset($currData['level']) && is_array($currData['level'])) {
                $levelCode = (string)($currData['level']['code'] ?? '');
                $levelId = $levelMap[$levelCode] ?? null;
                $levelName = $currData['level']['name'] ?? '';
            }
        }

        // Semestr
        $semCode = null;
        $semName = null;
        if (is_array($semester)) {
            $semCode = (string)($semester['code'] ?? '');
            $semName = (string)($semester['name'] ?? '');
        }
        $semId = null;
        if ($semCode !== null && $semCode !== '' && isset($semMap[$semCode])) {
            $semId = $semMap[$semCode];
        }
        
        // YANGI: Semestr raqamiga qarab level_id va level_name ni aniqlash
        // Agar currData dan level topilmasa, semestr asosida hisoblaymiz
        if (!$levelId && $semName !== '') {
            // Semestr nomidan raqamni ajratib olish (masalan: "1-semestr" -> 1)
            if (preg_match('/^(\d+)/', $semName, $matches)) {
                $semesterNum = (int)$matches[1];
                
                // Semestr raqamiga qarab kurs nomini aniqlash
                $courseName = null;
                if (in_array($semesterNum, [1, 2])) {
                    $courseName = '1-kurs';
                } elseif (in_array($semesterNum, [3, 4])) {
                    $courseName = '2-kurs';
                } elseif (in_array($semesterNum, [5, 6])) {
                    $courseName = '3-kurs';
                } elseif (in_array($semesterNum, [7, 8])) {
                    $courseName = '4-kurs';
                } elseif (in_array($semesterNum, [9, 10])) {
                    $courseName = '5-kurs';
                } elseif (in_array($semesterNum, [11, 12])) {
                    $courseName = '6-kurs';
                }
                
                // Kurs nomiga qarab level_id ni topish
                if ($courseName) {
                    foreach ($levels as $lvl) {
                        if ($lvl['name'] === $courseName) {
                            $levelId = $levelMap[$lvl['code']] ?? null;
                            $levelName = $courseName;
                            break;
                        }
                    }
                }
            }
        }

        // Active va at_semester
        $activeBool = ($active === 'ИСТИНА' || $active === true || $active === 1);
        $atSemesterBool = ($atSemester === 'ИСТИНА' || $atSemester === true || $atSemester === 1);

        try {
            $result = $insSubj->execute([
                ':hemis_id'           => $hemisId,
                ':curriculum_id'      => $localCurrId,
                ':education_type_id'  => $eduTypeId,
                ':education_year_id'  => $eduYearId,
                ':department_id'      => $deptId,
                ':specialty_id'       => $specId,
                ':level_id'           => $levelId,
                ':semester_id'        => $semId,
                ':semester_name'      => $semName ?: null,
                ':name'               => $name,
                ':short_name'         => $shortName ?: null,
                ':active'             => $activeBool ? 1 : 0,
                ':at_semester'        => $atSemesterBool ? 1 : 0,
                ':curriculum_name'    => $currName,
                ':education_type_name'=> $eduTypeName,
                ':education_year_name'=> $eduYearName,
                ':department_name'    => $deptName,
                ':specialty_name'     => $specName,
                ':level_name'         => $levelName,
            ]);
            
            if ($result) {
                $subjCount++;
            }
        } catch (PDOException $e) {
            // Faqat duplicate key xatolarini ignore qilish
            if ($e->getCode() == 23000) {
                // Duplicate entry - skip
                $subjSkipped++;
                $skipReasons['duplicate_entry']++;
            } else {
                // Boshqa xatolik - ko'rsatish
                $subjSkipped++;
                $skipReasons['other_error']++;
                if ($skipReasons['other_error'] <= 5) {
                    echo "   ⚠ Fan #{$hemisId} ({$name}) saqlashda xato: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "   ✓ {$subjCount} ta fan saqlandi";
    if ($subjSkipped > 0) {
        echo " (o'tkazib yuborilgan: {$subjSkipped})";
        echo "\n\n   Skip sabablari:";
        if ($skipReasons['no_subject_or_curriculum'] > 0) {
            echo "\n   - Subject yoki curriculum yo'q: " . $skipReasons['no_subject_or_curriculum'];
        }
        if ($skipReasons['no_hemis_id_or_name'] > 0) {
            echo "\n   - Hemis ID yoki nom yo'q: " . $skipReasons['no_hemis_id_or_name'];
        }
        if ($skipReasons['curriculum_not_mapped'] > 0) {
            echo "\n   - Curriculum mapping topilmadi: " . $skipReasons['curriculum_not_mapped'];
        }
        if ($skipReasons['duplicate_entry'] > 0) {
            echo "\n   - Duplicate (takroriy): " . $skipReasons['duplicate_entry'];
        }
        if ($skipReasons['other_error'] > 0) {
            echo "\n   - Boshqa xatoliklar: " . $skipReasons['other_error'];
        }
    }
    echo "\n\n";

    // Transaction ni commit qilish
    if ($pdo->inTransaction()) {
        $pdo->commit();
        echo "   ✓ Transaction commit qilindi\n";
    } else {
        echo "   ⚠️ Transaction faol emas\n";
    }

    echo "========================================\n";
    echo "✓ BARCHA MA'LUMOTLAR MUVAFFAQIYATLI SAQLANDI\n";
    echo "========================================\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ XATO YUZAGA KELDI:\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n</pre>";