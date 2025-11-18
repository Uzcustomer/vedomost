<?php
// wp-content/abror/YN_oldi_word.php

if (!function_exists('yn_oldi_render_word')) {

    /**
     * YN oldi vedomost Word hujjatini chiqarish
     *
     * @param array $rows  - talabalar ro'yxati (har biri associative array)
     * @param array $meta  - umumiy ma'lumotlar (fakultet, fan, sana va hokazo)
     */
    function yn_oldi_render_word(array $rows, array $meta = []): void
    {
        // Yordamchi funksiyalar
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        $faculty_name = $meta['faculty_name']  ?? '';
        $group_name   = $meta['group_name']    ?? '';
        $subject_name = $meta['subject_name']  ?? '';
        $teacher_name = $meta['teacher_name']  ?? '';
        $today        = $meta['date']          ?? date('Y-m-d');

        // Word headerlar
        header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        header("Content-Disposition: attachment; filename=YN_oldi_vedomost.doc");

        // HTML → Word
        $doc = '
<html xmlns:w="urn:schemas-microsoft-com:office:word">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 4px; }
        th { text-align: center; }
    </style>
</head>
<body>

<h2 style="text-align:center;">Yakuniy nazorat oldi VEDOMOSTI</h2>

<p><b>Fakultet:</b> ' . $h($faculty_name) . '</p>
<p><b>Guruh:</b> ' . $h($group_name) . '</p>
<p><b>Fan:</b> '   . $h($subject_name) . '</p>
<p><b>O‘qituvchi:</b> ' . $h($teacher_name) . '</p>
<p><b>Sana:</b> ' . $h($today) . '</p>

<br>

<table>
    <tr>
        <th>№</th>
        <th>Talaba F.I.O.</th>
        <th>Guruh</th>
        <th>JN balli</th>
        <th>Imzo</th>
    </tr>
';

        $i = 1;
        if ($rows) {
            foreach ($rows as $r) {
                $doc .= '
    <tr>
        <td style="text-align:center;">' . $i++ . '</td>
        <td>' . $h($r['student_name'] ?? '') . '</td>
        <td style="text-align:center;">' . $h($r['group_name'] ?? '') . '</td>
        <td style="text-align:center;">' . $h($r['jn_ball'] ?? '') . '</td>
        <td></td>
    </tr>';
            }
        } else {
            $doc .= '
    <tr>
        <td colspan="5" style="text-align:center;">Maʼlumot topilmadi</td>
    </tr>';
        }

        $doc .= '
</table>

<br><br><br>

<table width="100%">
    <tr>
        <td style="text-align:left;">
            Kafedra mudiri: _______________________
        </td>
        <td style="text-align:right;">
            O‘qituvchi: _______________________
        </td>
    </tr>
</table>

</body>
</html>
';

        echo $doc;
        exit;
    }
}
