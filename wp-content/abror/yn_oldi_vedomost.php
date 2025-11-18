<?php
// wp-content/abror/yn_oldi_vedomost.php

// WordPressni ulash ($wpdb uchun)
if (!defined('ABSPATH')) {
    require_once __DIR__ . '/../../wp-load.php'; // abror/../../ -> wordpress root
}

global $wpdb;

// Word shablonni ulash
require_once __DIR__ . '/YN_oldi_word.php';

/**
 * Filterlarni GET/POST dan olish
 */
function yn_oldi_get_filters(): array
{
    $src = $_REQUEST;

    return [
        'faculty_id'        => $src['faculty_id']        ?? null,
        'course'            => $src['course']            ?? null,
        'semester'          => $src['semester']          ?? null,
        'group_id'          => $src['group_id']          ?? null,
        'subject_id'        => $src['subject_id']        ?? null,
        'education_year_id' => $src['education_year_id'] ?? null,
    ];
}

/**
 * Shu filterlar bo‘yicha YN oldi vedomost uchun talabalar ro‘yxatini olish
 * !!! BU YERDA O‘ZINGNING HAQIQIY JADVAL VA USTUN NOMLARINGNI QO‘Y!!!
 */
function yn_oldi_fetch_rows(array $filt): array
{
    global $wpdb;

    // TODO: mana shu joyni o'zingning sxemangga moslashtir:
    // masalan: ttatf_yn_jn_agg, hemis_students va hokazo.
    $sql = "
        SELECT
            s.id           AS student_id,
            s.full_name    AS student_name,
            g.name         AS group_name,
            subj.name      AS subject_name,
            f.name         AS faculty_name,
            t.full_name    AS teacher_name,
            s.course       AS course,
            jn.jn_avg      AS jn_ball
        FROM ttatf_marks_yn jn              -- TODO: JADVAL NOMINI O'ZGARTIR
        JOIN ttatf_students   s   ON s.id   = jn.student_id   -- TODO
        JOIN ttatf_groups     g   ON g.id   = jn.group_id     -- TODO
        JOIN ttatf_subjects   subj ON subj.id = jn.subject_id -- TODO
        JOIN ttatf_faculties  f   ON f.id   = g.faculty_id    -- TODO
        LEFT JOIN ttatf_teachers t ON t.id = jn.teacher_id    -- TODO
        WHERE 1=1
    ";

    $params = [];

    if (!empty($filt['faculty_id'])) {
        $sql      .= " AND f.id = %d";
        $params[] = (int)$filt['faculty_id'];
    }
    if (!empty($filt['course'])) {
        $sql      .= " AND s.course = %d";
        $params[] = (int)$filt['course'];
    }
    if (!empty($filt['semester'])) {
        $sql      .= " AND jn.semester = %d";
        $params[] = (int)$filt['semester'];
    }
    if (!empty($filt['group_id'])) {
        $sql      .= " AND g.id = %d";
        $params[] = (int)$filt['group_id'];
    }
    if (!empty($filt['subject_id'])) {
        $sql      .= " AND subj.id = %d";
        $params[] = (int)$filt['subject_id'];
    }
    if (!empty($filt['education_year_id'])) {
        $sql      .= " AND jn.education_year_id = %d";
        $params[] = (int)$filt['education_year_id'];
    }

    $sql .= " ORDER BY g.name, s.full_name";

    $prepared = $params ? $wpdb->prepare($sql, $params) : $sql;

    return $wpdb->get_results($prepared, ARRAY_A);
}

// =================== MAIN FLOW ===================

// 1) Filtrlarni ol
$filters = yn_oldi_get_filters();

// 2) Ma'lumotlarni ol
$rows = yn_oldi_fetch_rows($filters);

// 3) Meta ma'lumotlarni tayyorla (birinchi qatordan olib turibsan)
$first = $rows[0] ?? [];

$meta = [
    'faculty_name' => $first['faculty_name']  ?? '',
    'group_name'   => $first['group_name']    ?? '',
    'subject_name' => $first['subject_name']  ?? '',
    'teacher_name' => $first['teacher_name']  ?? '',
    'date'         => date('Y-m-d'),
    'filters'      => $filters,
];

// 4) Word hujjatni chiqar
yn_oldi_render_word($rows, $meta);
