<?php
/**
 * excel_export.php - Joriy baholari o'rtachasi Excel hisobot
 * SimpleXLSXGen kutubxonasi bilan
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__, 2) . '/wp-config.php';
require_once __DIR__ . '/SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die('DB connection error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// ===================== HELPER FUNCTIONS =====================

function db_all(PDO $pdo, string $sql, array $params = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Normalize fan nomi - (a/b/c) variantlarni olib tashlash
 */
function normalize_fan(string $name): string {
    $name = trim($name);
    $name = preg_replace('/\s*\([abc]\)\s*$/i', '', $name);
    return $name;
}

/**
 * Round half up - Python kabi yarim yaxlitlash
 */
function round_half_up(float $x): int {
    $n = floor($x);
    $f = $x - $n;
    return (int)($n + ($f >= 0.5 ? 1 : 0));
}

/**
 * Divisor hisoblash - guruh×fan×semestr bo'yicha valid dars kunlari
 */
function calc_divisors(PDO $pdo, array $filters, string $startDate, string $endDate): array {
    $allowedTypes = ['amaliy', 'laboratoriya', 'seminar'];
    
    $sql = "SELECT 
                g.name as group_name,
                s.subject_id,
                se.name as semester_name,
                a.lesson_date
            FROM hemis_attendances a
            INNER JOIN hemis_students st ON a.student_id = st.id
            INNER JOIN hemis_groups g ON st.group_id = g.id
            INNER JOIN hemis_subjects s ON a.subject_id = s.id
            INNER JOIN hemis_semesters se ON g.semester_id = se.id
            INNER JOIN hemis_training_types tt ON a.training_type_id = tt.id
            WHERE a.lesson_date BETWEEN :start_date AND :end_date
              AND LOWER(tt.name) IN ('" . implode("','", $allowedTypes) . "')";
    
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];
    
    if (!empty($filters['education_year_id'])) {
        $sql .= " AND g.education_year_id = :education_year_id";
        $params['education_year_id'] = $filters['education_year_id'];
    }
    if (!empty($filters['department_id'])) {
        $sql .= " AND g.department_id = :department_id";
        $params['department_id'] = $filters['department_id'];
    }
    if (!empty($filters['level_id'])) {
        $sql .= " AND g.level_id = :level_id";
        $params['level_id'] = $filters['level_id'];
    }
    
    $sql .= " GROUP BY g.name, s.subject_id, se.name, a.lesson_date
              ORDER BY g.name, s.subject_id, a.lesson_date";
    
    $rows = db_all($pdo, $sql, $params);
    
    $counts = [];
    foreach ($rows as $row) {
        $key = $row['group_name'] . '|' . $row['subject_id'] . '|' . $row['semester_name'];
        $date = $row['lesson_date'];
        
        if (!isset($counts[$key])) {
            $counts[$key] = [];
        }
        if (!isset($counts[$key][$date])) {
            $counts[$key][$date] = 0;
        }
        $counts[$key][$date]++;
    }
    
    $divisors = [];
    foreach ($counts as $key => $dates) {
        $total = count($dates);
        $threshold = max(3, (int)(0.30 * max(1, $total)));
        
        $valid_dates = [];
        foreach ($dates as $date => $count) {
            if ($count >= $threshold) {
                $valid_dates[] = $date;
            }
        }
        
        $divisors[$key] = !empty($valid_dates) ? count($valid_dates) : 1;
    }
    
    return $divisors;
}

/**
 * Talabalarning joriy baholarini hisoblash
 */
function get_student_grades(PDO $pdo, array $filters, array $divisors, string $startDate, string $endDate): array {
    $allowedTypes = ['amaliy', 'laboratoriya', 'seminar'];
    
    $sql = "SELECT 
                st.student_id_number,
                CONCAT(st.second_name, ' ', st.first_name, ' ', COALESCE(st.third_name, '')) as full_name,
                g.name as group_name,
                d.name as department_name,
                sp.name as specialty_name,
                l.name as level_name,
                se.name as semester_name,
                s.subject_id,
                s.name as subject_name,
                a.lesson_date,
                CASE 
                    WHEN LOWER(a.status) = 'recorded' THEN a.grade
                    ELSE a.retake_grade
                END as final_grade
            FROM hemis_attendances a
            INNER JOIN hemis_students st ON a.student_id = st.id
            INNER JOIN hemis_groups g ON st.group_id = g.id
            INNER JOIN hemis_departments d ON g.department_id = d.id
            INNER JOIN hemis_specialties sp ON g.specialty_id = sp.id
            INNER JOIN hemis_levels l ON g.level_id = l.id
            INNER JOIN hemis_semesters se ON g.semester_id = se.id
            INNER JOIN hemis_subjects s ON a.subject_id = s.id
            INNER JOIN hemis_training_types tt ON a.training_type_id = tt.id
            WHERE a.lesson_date BETWEEN :start_date AND :end_date
              AND LOWER(tt.name) IN ('" . implode("','", $allowedTypes) . "')";
    
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];
    
    if (!empty($filters['education_year_id'])) {
        $sql .= " AND g.education_year_id = :education_year_id";
        $params['education_year_id'] = $filters['education_year_id'];
    }
    if (!empty($filters['department_id'])) {
        $sql .= " AND g.department_id = :department_id";
        $params['department_id'] = $filters['department_id'];
    }
    if (!empty($filters['specialty_id'])) {
        $sql .= " AND g.specialty_id = :specialty_id";
        $params['specialty_id'] = $filters['specialty_id'];
    }
    if (!empty($filters['level_id'])) {
        $sql .= " AND g.level_id = :level_id";
        $params['level_id'] = $filters['level_id'];
    }
    if (!empty($filters['semester_id'])) {
        $sql .= " AND g.semester_id = :semester_id";
        $params['semester_id'] = $filters['semester_id'];
    }
    
    if (!empty($filters['subject_ids'])) {
        $subjectIds = explode(',', $filters['subject_ids']);
        $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
        $sql .= " AND s.id IN ($placeholders)";
        $params = array_merge($params, $subjectIds);
    }
    
    if (!empty($filters['group_ids'])) {
        $groupIds = explode(',', $filters['group_ids']);
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $sql .= " AND g.id IN ($placeholders)";
        $params = array_merge($params, $groupIds);
    }
    
    $sql .= " ORDER BY sp.name, d.name, g.name, st.second_name, st.first_name";
    
    $rows = db_all($pdo, $sql, $params);
    
    $student_fan_days = [];
    
    foreach ($rows as $row) {
        $student_key = $row['student_id_number'] . '|' . 
                      $row['full_name'] . '|' . 
                      $row['level_name'] . '|' . 
                      $row['department_name'] . '|' . 
                      $row['specialty_name'] . '|' . 
                      $row['group_name'] . '|' . 
                      $row['semester_name'];
        
        $subject_id = $row['subject_id'];
        $subject_name = $row['subject_name'];
        $day = $row['lesson_date'];
        $grade = max(0.0, min(100.0, (float)$row['final_grade']));
        
        if (!isset($student_fan_days[$student_key])) {
            $student_fan_days[$student_key] = [];
        }
        
        if (!isset($student_fan_days[$student_key][$subject_id])) {
            $student_fan_days[$student_key][$subject_id] = [
                'name' => $subject_name,
                'days' => []
            ];
        }
        
        if (!isset($student_fan_days[$student_key][$subject_id]['days'][$day])) {
            $student_fan_days[$student_key][$subject_id]['days'][$day] = [
                'sum' => 0.0,
                'count' => 0
            ];
        }
        
        $student_fan_days[$student_key][$subject_id]['days'][$day]['sum'] += $grade;
        $student_fan_days[$student_key][$subject_id]['days'][$day]['count']++;
    }
    
    $result = [];
    
    foreach ($student_fan_days as $student_key => $subjects) {
        list($sid_num, $full_name, $level, $dept, $spec, $group, $semester) = explode('|', $student_key);
        
        $student_grades = [
            'student_id_number' => $sid_num,
            'full_name' => $full_name,
            'level' => $level,
            'department' => $dept,
            'specialty' => $spec,
            'group' => $group,
            'semester' => $semester,
            'subjects' => []
        ];
        
        $total_sum = 0;
        $total_count = 0;
        
        foreach ($subjects as $subject_id => $subject_data) {
            $subject_name = $subject_data['name'];
            $normalized_name = normalize_fan($subject_name);
            
            $sum_daily = 0;
            $my_days = 0;
            
            foreach ($subject_data['days'] as $day => $day_data) {
                if ($day_data['count'] > 0) {
                    $my_days++;
                    $daily_avg = $day_data['sum'] / $day_data['count'];
                    $sum_daily += round_half_up($daily_avg);
                }
            }
            
            $divisor_key = $group . '|' . $subject_id . '|' . $semester;
            $divisor = $divisors[$divisor_key] ?? 1;
            
            $final_grade = $divisor > 0 ? round_half_up($sum_daily / $divisor) : 0;
            
            if (!isset($student_grades['subjects'][$normalized_name])) {
                $student_grades['subjects'][$normalized_name] = 0;
            }
            $student_grades['subjects'][$normalized_name] = max(
                $student_grades['subjects'][$normalized_name],
                $final_grade
            );
        }
        
        foreach ($student_grades['subjects'] as $grade) {
            if ($grade > 0) {
                $total_sum += $grade;
                $total_count++;
            }
        }
        
        $student_grades['average'] = $total_count > 0 ? round_half_up($total_sum / $total_count) : 0;
        
        $result[] = $student_grades;
    }
    
    return $result;
}

/**
 * Excel fayl yaratish
 */
function create_excel(array $data): string {
    if (empty($data)) {
        throw new Exception("Ma'lumot topilmadi!");
    }
    
    $all_subjects = [];
    foreach ($data as $student) {
        foreach ($student['subjects'] as $subject_name => $grade) {
            if (!in_array($subject_name, $all_subjects)) {
                $all_subjects[] = $subject_name;
            }
        }
    }
    sort($all_subjects);
    
    $rows = [];
    
    $header = ['№', 'FISH', 'Kurs', 'Fakultet', 'Yo\'nalish', 'Guruh', 'Semestr'];
    foreach ($all_subjects as $subject) {
        $header[] = $subject;
    }
    $header[] = 'O\'rtacha';
    $rows[] = $header;
    
    foreach ($data as $idx => $student) {
        $row = [
            $idx + 1,
            $student['full_name'],
            $student['level'],
            $student['department'],
            $student['specialty'],
            $student['group'],
            $student['semester']
        ];
        
        foreach ($all_subjects as $subject) {
            $grade = $student['subjects'][$subject] ?? 0;
            $row[] = $grade;
        }
        
        $row[] = $student['average'];
        
        $rows[] = $row;
    }
    
    $xlsx = SimpleXLSXGen::fromArray($rows);
    
    $filename = 'joriy_baholar_' . date('Y-m-d_His') . '.xlsx';
    $filepath = sys_get_temp_dir() . '/' . $filename;
    
    $xlsx->saveAs($filepath);
    
    return $filepath;
}

// ===================== ASOSIY KOD =====================

try {
    $filters = [
        'education_type_id' => $_GET['education_type_id'] ?? null,
        'education_year_id' => $_GET['education_year_id'] ?? null,
        'department_id' => $_GET['department_id'] ?? null,
        'specialty_id' => $_GET['specialty_id'] ?? null,
        'level_id' => $_GET['level_id'] ?? null,
        'semester_id' => $_GET['semester_id'] ?? null,
        'subject_ids' => $_GET['subject_ids'] ?? '',
        'group_ids' => $_GET['group_ids'] ?? ''
    ];
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    $divisors = calc_divisors($pdo, $filters, $startDate, $endDate);
    $studentGrades = get_student_grades($pdo, $filters, $divisors, $startDate, $endDate);
    
    if (empty($studentGrades)) {
        throw new Exception("Tanlangan filtrlar bo'yicha ma'lumot topilmadi!");
    }
    
    $filepath = create_excel($studentGrades);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . basename($filepath) . '"');
    header('Cache-Control: max-age=0');
    
    readfile($filepath);
    unlink($filepath);
    exit;
    
} catch (Throwable $e) {
    http_response_code(500);
    echo "Xatolik: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    error_log("Excel export error: " . $e->getMessage());
}