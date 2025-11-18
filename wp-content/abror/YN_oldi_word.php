<?php
// wp-content/abror/yn_oldi_word.php

// Kiruvchi parametrlardan filterlar olinadi
$params = $_REQUEST;

// YN oldi vedomost logikasini ulash
require_once __DIR__ . '/yn_oldi_vedomost.php';

// Ma'lumotlarni olamiz
$rows = yn_oldi_fetch_rows($params);

// Meta ma'lumotlarni tayyorlaymiz
$first = $rows[0] ?? [];
$meta = [
    'faculty_name' => $first['faculty_name'] ?? '',
    'group_name'   => $first['group_name']   ?? '',
    'subject_name' => $first['subject_name'] ?? '',
    'teacher_name' => $first['teacher_name'] ?? '',
    'date'         => date('Y-m-d'),
    'filters'      => $params,
];

// Word renderer-ni ulash
require_once __DIR__ . '/YN_oldi_word.php';
yn_oldi_render_word($rows, $meta);
