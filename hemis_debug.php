<?php
/**
 * import_hemis_students.php
 * HEMIS /v1/data/student-list dan ma'lumotlarni yuklab, hemis_students jadvaliga UPSERT qiladi.
 * WordPress muhitidagi wp-config.php dan DB credlarni oladi.
 *
 * Ishlatish:  php import_hemis_students.php
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

///////////////////////////
// 1) MUHIT VA DB ULANISH
///////////////////////////
$root = __DIR__;
require_once $root . '/wp-config.php';

$dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
$pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
]);

///////////////////////////
// 2) API KONFIG
///////////////////////////
const HEMIS_TOKEN = '1iOc7IajuJCeTJ0fQR2y_31GaE-7tsUR'; // Bearer token
const API_BASE    = 'https://student.ttatf.uz/rest/v1/data/student-list';
const PAGE_LIMIT  = 500;     // X-Rate-Limit ni inobatga oling, kerak bo‘lsa kamaytiring
const SLEEP_MS    = 250;     // sahifalar orasida dam olish (ms)

///////////////////////////
// 3) DDL (jadval)
///////////////////////////
$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `hemis_students` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hemis_id`         BIGINT UNSIGNED NOT NULL,
  `meta_id`          BIGINT UNSIGNED DEFAULT NULL,

  `full_name`        VARCHAR(255)   DEFAULT NULL,
  `short_name`       VARCHAR(255)   DEFAULT NULL,
  `first_name`       VARCHAR(255)   DEFAULT NULL,
  `second_name`      VARCHAR(255)   DEFAULT NULL,
  `third_name`       VARCHAR(255)   DEFAULT NULL,
  `email`            VARCHAR(255)   DEFAULT NULL,

  `image`            TEXT           DEFAULT NULL,
  `image_full`       TEXT           DEFAULT NULL,
  `student_id_number` VARCHAR(64)   DEFAULT NULL,

  `birth_date_unix`  BIGINT         DEFAULT NULL,
  `birth_date`       DATE           DEFAULT NULL,

  `avg_gpa`          DECIMAL(6,3)   DEFAULT NULL,
  `avg_grade`        DECIMAL(6,3)   DEFAULT NULL,
  `total_credit`     DECIMAL(10,3)  DEFAULT NULL,

  `university_code`  VARCHAR(32)    DEFAULT NULL,
  `university_name`  VARCHAR(255)   DEFAULT NULL,

  `gender_code`      VARCHAR(32)    DEFAULT NULL,
  `gender_name`      VARCHAR(64)    DEFAULT NULL,

  `country_code`     VARCHAR(32)    DEFAULT NULL,
  `country_name`     VARCHAR(255)   DEFAULT NULL,

  `province_code`    VARCHAR(32)    DEFAULT NULL,
  `province_name`    VARCHAR(255)   DEFAULT NULL,
  `district_code`    VARCHAR(32)    DEFAULT NULL,
  `district_name`    VARCHAR(255)   DEFAULT NULL,
  `terrain_code`     VARCHAR(32)    DEFAULT NULL,
  `terrain_name`     VARCHAR(255)   DEFAULT NULL,

  `citizenship_code` VARCHAR(32)    DEFAULT NULL,
  `citizenship_name` VARCHAR(255)   DEFAULT NULL,

  `education_year_code` VARCHAR(32) DEFAULT NULL,
  `education_year_name` VARCHAR(64) DEFAULT NULL,

  `educationForm_code` VARCHAR(32)  DEFAULT NULL,
  `educationForm_name` VARCHAR(64)  DEFAULT NULL,

  `educationType_code` VARCHAR(32)  DEFAULT NULL,
  `educationType_name` VARCHAR(64)  DEFAULT NULL,

  `paymentForm_code`   VARCHAR(32)  DEFAULT NULL,
  `paymentForm_name`   VARCHAR(64)  DEFAULT NULL,

  `studentType_code`   VARCHAR(32)  DEFAULT NULL,
  `studentType_name`   VARCHAR(64)  DEFAULT NULL,

  `socialCategory_code` VARCHAR(32) DEFAULT NULL,
  `socialCategory_name` VARCHAR(64) DEFAULT NULL,

  `povertyLevel_code`   VARCHAR(32) DEFAULT NULL,
  `povertyLevel_name`   VARCHAR(64) DEFAULT NULL,

  `accommodation_code`  VARCHAR(32) DEFAULT NULL,
  `accommodation_name`  VARCHAR(64) DEFAULT NULL,

  `studentStatus_code`  VARCHAR(32) DEFAULT NULL,
  `studentStatus_name`  VARCHAR(64) DEFAULT NULL,

  `department_id`     BIGINT        DEFAULT NULL,
  `department_name`   VARCHAR(255)  DEFAULT NULL,
  `department_code`   VARCHAR(64)   DEFAULT NULL,
  `dept_structure_type_code` VARCHAR(32) DEFAULT NULL,
  `dept_structure_type_name` VARCHAR(64) DEFAULT NULL,
  `dept_locality_type_code`  VARCHAR(32) DEFAULT NULL,
  `dept_locality_type_name`  VARCHAR(64) DEFAULT NULL,
  `dept_parent`       BIGINT        DEFAULT NULL,
  `dept_active`       TINYINT(1)    DEFAULT NULL,

  `specialty_id`      BIGINT        DEFAULT NULL,
  `specialty_code`    VARCHAR(64)   DEFAULT NULL,
  `specialty_name`    VARCHAR(255)  DEFAULT NULL,

  `group_id`          BIGINT        DEFAULT NULL,
  `group_name`        VARCHAR(255)  DEFAULT NULL,
  `group_lang_code`   VARCHAR(32)   DEFAULT NULL,
  `group_lang_name`   VARCHAR(64)   DEFAULT NULL,

  `level_code`        VARCHAR(32)   DEFAULT NULL,
  `level_name`        VARCHAR(64)   DEFAULT NULL,

  `semester_id`       BIGINT        DEFAULT NULL,
  `semester_code`     VARCHAR(32)   DEFAULT NULL,
  `semester_name`     VARCHAR(64)   DEFAULT NULL,

  `_curriculum`       BIGINT        DEFAULT NULL,
  `year_of_enter`     INT           DEFAULT NULL,
  `roommate_count`    INT           DEFAULT NULL,
  `is_graduate`       TINYINT(1)    DEFAULT NULL,
  `total_acload`      DECIMAL(10,3) DEFAULT NULL,

  `other`             TEXT          DEFAULT NULL,

  `created_at_unix`   BIGINT        DEFAULT NULL,
  `created_at`        DATETIME      DEFAULT NULL,
  `updated_at_unix`   BIGINT        DEFAULT NULL,
  `updated_at`        DATETIME      DEFAULT NULL,

  `hash`              VARCHAR(128)  DEFAULT NULL,
  `validateUrl`       TEXT          DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hemis_students_hemis_id` (`hemis_id`),
  KEY `ix_student_id_number` (`student_id_number`),
  KEY `ix_department` (`department_id`),
  KEY `ix_specialty` (`specialty_id`),
  KEY `ix_group` (`group_id`),
  KEY `ix_level_semester` (`level_name`,`semester_name`),
  KEY `ix_edu_year_code` (`education_year_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

///////////////////////////
// 4) INSERT/UPSERT PREP
///////////////////////////
$sql = <<<'SQL'
INSERT INTO hemis_students (
  hemis_id, meta_id,
  full_name, short_name, first_name, second_name, third_name, email,
  image, image_full, student_id_number,
  birth_date_unix, birth_date,
  avg_gpa, avg_grade, total_credit,
  university_code, university_name,
  gender_code, gender_name,
  country_code, country_name,
  province_code, province_name,
  district_code, district_name,
  terrain_code, terrain_name,
  citizenship_code, citizenship_name,
  education_year_code, education_year_name,
  educationForm_code, educationForm_name,
  educationType_code, educationType_name,
  paymentForm_code, paymentForm_name,
  studentType_code, studentType_name,
  socialCategory_code, socialCategory_name,
  povertyLevel_code, povertyLevel_name,
  accommodation_code, accommodation_name,
  studentStatus_code, studentStatus_name,
  department_id, department_name, department_code,
  dept_structure_type_code, dept_structure_type_name,
  dept_locality_type_code,  dept_locality_type_name,
  dept_parent, dept_active,
  specialty_id, specialty_code, specialty_name,
  group_id, group_name, group_lang_code, group_lang_name,
  level_code, level_name,
  semester_id, semester_code, semester_name,
  _curriculum, year_of_enter, roommate_count, is_graduate, total_acload,
  other,
  created_at_unix, created_at, updated_at_unix, updated_at,
  hash, validateUrl
) VALUES (
  :hemis_id, :meta_id,
  :full_name, :short_name, :first_name, :second_name, :third_name, :email,
  :image, :image_full, :student_id_number,
  :birth_date_unix, :birth_date,
  :avg_gpa, :avg_grade, :total_credit,
  :university_code, :university_name,
  :gender_code, :gender_name,
  :country_code, :country_name,
  :province_code, :province_name,
  :district_code, :district_name,
  :terrain_code, :terrain_name,
  :citizenship_code, :citizenship_name,
  :education_year_code, :education_year_name,
  :educationForm_code, :educationForm_name,
  :educationType_code, :educationType_name,
  :paymentForm_code, :paymentForm_name,
  :studentType_code, :studentType_name,
  :socialCategory_code, :socialCategory_name,
  :povertyLevel_code, :povertyLevel_name,
  :accommodation_code, :accommodation_name,
  :studentStatus_code, :studentStatus_name,
  :department_id, :department_name, :department_code,
  :dept_structure_type_code, :dept_structure_type_name,
  :dept_locality_type_code,  :dept_locality_type_name,
  :dept_parent, :dept_active,
  :specialty_id, :specialty_code, :specialty_name,
  :group_id, :group_name, :group_lang_code, :group_lang_name,
  :level_code, :level_name,
  :semester_id, :semester_code, :semester_name,
  :_curriculum, :year_of_enter, :roommate_count, :is_graduate, :total_acload,
  :other,
  :created_at_unix, :created_at, :updated_at_unix, :updated_at,
  :hash, :validateUrl
)
ON DUPLICATE KEY UPDATE
  meta_id = VALUES(meta_id),
  full_name = VALUES(full_name),
  short_name = VALUES(short_name),
  first_name = VALUES(first_name),
  second_name = VALUES(second_name),
  third_name = VALUES(third_name),
  email = VALUES(email),
  image = VALUES(image),
  image_full = VALUES(image_full),
  student_id_number = VALUES(student_id_number),
  birth_date_unix = VALUES(birth_date_unix),
  birth_date = VALUES(birth_date),
  avg_gpa = VALUES(avg_gpa),
  avg_grade = VALUES(avg_grade),
  total_credit = VALUES(total_credit),
  university_code = VALUES(university_code),
  university_name = VALUES(university_name),
  gender_code = VALUES(gender_code),
  gender_name = VALUES(gender_name),
  country_code = VALUES(country_code),
  country_name = VALUES(country_name),
  province_code = VALUES(province_code),
  province_name = VALUES(province_name),
  district_code = VALUES(district_code),
  district_name = VALUES(district_name),
  terrain_code = VALUES(terrain_code),
  terrain_name = VALUES(terrain_name),
  citizenship_code = VALUES(citizenship_code),
  citizenship_name = VALUES(citizenship_name),
  education_year_code = VALUES(education_year_code),
  education_year_name = VALUES(education_year_name),
  educationForm_code = VALUES(educationForm_code),
  educationForm_name = VALUES(educationForm_name),
  educationType_code = VALUES(educationType_code),
  educationType_name = VALUES(educationType_name),
  paymentForm_code = VALUES(paymentForm_code),
  paymentForm_name = VALUES(paymentForm_name),
  studentType_code = VALUES(studentType_code),
  studentType_name = VALUES(studentType_name),
  socialCategory_code = VALUES(socialCategory_code),
  socialCategory_name = VALUES(socialCategory_name),
  povertyLevel_code = VALUES(povertyLevel_code),
  povertyLevel_name = VALUES(povertyLevel_name),
  accommodation_code = VALUES(accommodation_code),
  accommodation_name = VALUES(accommodation_name),
  studentStatus_code = VALUES(studentStatus_code),
  studentStatus_name = VALUES(studentStatus_name),
  department_id = VALUES(department_id),
  department_name = VALUES(department_name),
  department_code = VALUES(department_code),
  dept_structure_type_code = VALUES(dept_structure_type_code),
  dept_structure_type_name = VALUES(dept_structure_type_name),
  dept_locality_type_code  = VALUES(dept_locality_type_code),
  dept_locality_type_name  = VALUES(dept_locality_type_name),
  dept_parent = VALUES(dept_parent),
  dept_active = VALUES(dept_active),
  specialty_id = VALUES(specialty_id),
  specialty_code = VALUES(specialty_code),
  specialty_name = VALUES(specialty_name),
  group_id = VALUES(group_id),
  group_name = VALUES(group_name),
  group_lang_code = VALUES(group_lang_code),
  group_lang_name = VALUES(group_lang_name),
  level_code = VALUES(level_code),
  level_name = VALUES(level_name),
  semester_id = VALUES(semester_id),
  semester_code = VALUES(semester_code),
  semester_name = VALUES(semester_name),
  _curriculum = VALUES(_curriculum),
  year_of_enter = VALUES(year_of_enter),
  roommate_count = VALUES(roommate_count),
  is_graduate = VALUES(is_graduate),
  total_acload = VALUES(total_acload),
  other = VALUES(other),
  created_at_unix = VALUES(created_at_unix),
  created_at = VALUES(created_at),
  updated_at_unix = VALUES(updated_at_unix),
  updated_at = VALUES(updated_at),
  hash = VALUES(hash),
  validateUrl = VALUES(validateUrl)
SQL;

$ins = $pdo->prepare($sql);

///////////////////////////
// 5) YORDAMCHI FUNKSIYALAR
///////////////////////////
function val($x) {
    if ($x === '' || $x === 'null') return null;
    return $x;
}
function epochToDate(?int $u): ?string {
    if (!$u) return null;
    return gmdate('Y-m-d', $u);
}
function epochToDT(?int $u): ?string {
    if (!$u) return null;
    return gmdate('Y-m-d H:i:s', $u);
}

function pick($arr, $path, $def=null) {
    // "a.b.c" yo'l bo‘yicha olish
    $parts = explode('.', $path);
    $cur = $arr;
    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return $def;
        $cur = $cur[$p];
    }
    return $cur;
}

function api_get(int $page, int $limit): array {
    $url = API_BASE . '?page=' . $page . '&limit=' . $limit;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . HEMIS_TOKEN,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL error: $err");
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) {
        throw new RuntimeException("HTTP $code: $body");
    }
    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException("JSON decode failed");
    }
    // API responsedagi forma: {success, error, data:{items:[], pagination:{...}}, code}
    $data = $json['data'] ?? [];
    $items = $data['items'] ?? [];
    $pagination = $data['pagination'] ?? [];
    return [$items, $pagination];
}

///////////////////////////
// 6) ASOSIY LOOP (PAGINATION)
///////////////////////////
$page = 1;
$totalInserted = 0;
$totalPages = null;

$pdo->beginTransaction();
try {
    while (true) {
        [$items, $pagination] = api_get($page, PAGE_LIMIT);
        if ($totalPages === null) {
            $totalPages = (int)($pagination['pageCount'] ?? 1);
        }

        foreach ($items as $it) {
            $params = [
                ':hemis_id'   => (int)($it['id'] ?? 0),
                ':meta_id'    => val($it['meta_id'] ?? null),

                ':full_name'  => val($it['full_name'] ?? null),
                ':short_name' => val($it['short_name'] ?? null),
                ':first_name' => val($it['first_name'] ?? null),
                ':second_name'=> val($it['second_name'] ?? null),
                ':third_name' => val($it['third_name'] ?? null),
                ':email'      => val($it['email'] ?? null),

                ':image'      => val($it['image'] ?? null),
                ':image_full' => val($it['image_full'] ?? null),
                ':student_id_number' => val($it['student_id_number'] ?? null),

                ':birth_date_unix' => val($it['birth_date'] ?? null),
                ':birth_date'      => epochToDate($it['birth_date'] ?? null),

                ':avg_gpa'     => val($it['avg_gpa'] ?? null),
                ':avg_grade'   => val($it['avg_grade'] ?? null),
                ':total_credit'=> val($it['total_credit'] ?? null),

                ':university_code' => val(pick($it,'university.code')),
                ':university_name' => val(pick($it,'university.name')),

                ':gender_code' => val(pick($it,'gender.code')),
                ':gender_name' => val(pick($it,'gender.name')),

                ':country_code'=> val(pick($it,'country.code')),
                ':country_name'=> val(pick($it,'country.name')),

                ':province_code'=> val(pick($it,'province.code')),
                ':province_name'=> val(pick($it,'province.name')),
                ':district_code'=> val(pick($it,'district.code')),
                ':district_name'=> val(pick($it,'district.name')),
                ':terrain_code' => val(pick($it,'terrain.code')),
                ':terrain_name' => val(pick($it,'terrain.name')),

                ':citizenship_code'=> val(pick($it,'citizenship.code')),
                ':citizenship_name'=> val(pick($it,'citizenship.name')),

                ':education_year_code'=> val(pick($it,'educationYear.code')),
                ':education_year_name'=> val(pick($it,'educationYear.name')),

                ':educationForm_code'=> val(pick($it,'educationForm.code')),
                ':educationForm_name'=> val(pick($it,'educationForm.name')),

                ':educationType_code'=> val(pick($it,'educationType.code')),
                ':educationType_name'=> val(pick($it,'educationType.name')),

                ':paymentForm_code'=> val(pick($it,'paymentForm.code')),
                ':paymentForm_name'=> val(pick($it,'paymentForm.name')),

                ':studentType_code'=> val(pick($it,'studentType.code')),
                ':studentType_name'=> val(pick($it,'studentType.name')),

                ':socialCategory_code'=> val(pick($it,'socialCategory.code')),
                ':socialCategory_name'=> val(pick($it,'socialCategory.name')),

                ':povertyLevel_code'=> val(pick($it,'povertyLevel.code')),
                ':povertyLevel_name'=> val(pick($it,'povertyLevel.name')),

                ':accommodation_code'=> val(pick($it,'accommodation.code')),
                ':accommodation_name'=> val(pick($it,'accommodation.name')),

                ':studentStatus_code'=> val(pick($it,'studentStatus.code')),
                ':studentStatus_name'=> val(pick($it,'studentStatus.name')),

                ':department_id'     => val(pick($it,'department.id')),
                ':department_name'   => val(pick($it,'department.name')),
                ':department_code'   => val(pick($it,'department.code')),
                ':dept_structure_type_code' => val(pick($it,'department.structureType.code')),
                ':dept_structure_type_name' => val(pick($it,'department.structureType.name')),
                ':dept_locality_type_code'  => val(pick($it,'department.localityType.code')),
                ':dept_locality_type_name'  => val(pick($it,'department.localityType.name')),
                ':dept_parent'       => val(pick($it,'department.parent')),
                ':dept_active'       => is_null(pick($it,'department.active')) ? null : (pick($it,'department.active') ? 1 : 0),

                ':specialty_id'      => val(pick($it,'specialty.id')),
                ':specialty_code'    => val(pick($it,'specialty.code')),
                ':specialty_name'    => val(pick($it,'specialty.name')),

                ':group_id'          => val(pick($it,'group.id')),
                ':group_name'        => val(pick($it,'group.name')),
                ':group_lang_code'   => val(pick($it,'group.educationLang.code')),
                ':group_lang_name'   => val(pick($it,'group.educationLang.name')),

                ':level_code'        => val(pick($it,'level.code')),
                ':level_name'        => val(pick($it,'level.name')),

                ':semester_id'       => val(pick($it,'semester.id')),
                ':semester_code'     => val(pick($it,'semester.code')),
                ':semester_name'     => val(pick($it,'semester.name')),

                ':_curriculum'       => val($it['_curriculum'] ?? null),
                ':year_of_enter'     => val($it['year_of_enter'] ?? null),
                ':roommate_count'    => val($it['roommate_count'] ?? null),
                ':is_graduate'       => is_null($it['is_graduate'] ?? null) ? null : (($it['is_graduate'] ?? false) ? 1 : 0),
                ':total_acload'      => val($it['total_acload'] ?? null),

                ':other'             => val($it['other'] ?? null),

                ':created_at_unix'   => val($it['created_at'] ?? null),
                ':created_at'        => epochToDT($it['created_at'] ?? null),
                ':updated_at_unix'   => val($it['updated_at'] ?? null),
                ':updated_at'        => epochToDT($it['updated_at'] ?? null),

                ':hash'              => val($it['hash'] ?? null),
                ':validateUrl'       => val($it['validateUrl'] ?? null),
            ];

            $ins->execute($params);
            $totalInserted++;
        }

        // sahifa tugadi
        if ($page >= $totalPages) {
            break;
        }
        $page++;
        usleep(SLEEP_MS * 1000);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo "OK. Upsert: {$totalInserted} yozuv.\n";