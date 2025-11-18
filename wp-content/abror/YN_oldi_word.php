<?php
if (!function_exists('yn_oldi_render_word')) {
    function yn_oldi_render_word(array $rows, array $meta = []) {
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        header("Content-Disposition: attachment; filename=YN_oldi_vedomost.doc");
        $doc = '';
        $doc .= '<h2>Yakuniy nazorat oldi VEDOMOSTI</h2>';
        $doc .= '<p><b>Fakultet:</b> '.$h($meta['faculty_name']??'').'</p>';
        $doc .= '<p><b>Guruh:</b> '.$h($meta['group_name']??'').'</p>';
        $doc .= '<p><b>Fan:</b> '.$h($meta['subject_name']??'').'</p>';
        $doc .= '<p><b>O\'qituvchi:</b> '.$h($meta['teacher_name']??'').'</p>';
        $doc .= '<p><b>Sana:</b> '.($meta['date']??date('Y-m-d')).'</p>';
        $doc .= '<table><tbody><tr><th>№</th><th>Talaba F.I.O.</th><th>Guruh</th><th>JN balli</th><th>Imzo</th></tr>';
        $i=1;
        if ($rows) foreach ($rows as $r)
            $doc .= '<tr><td>'.$i++.'</td><td>'.$h($r['student_name']??'').'</td><td>'.$h($r['group_name']??'').'</td><td>'.$h($r['jn_ball']??'').'</td><td></td></tr>';
        else
            $doc .= '<tr><td>Maʼlumot topilmadi</td></tr>';
        $doc .= '</tbody></table><br><table><tbody><tr><td>Kafedra mudiri: _______________________</td><td>O\'qituvchi: _______________________</td></tr></tbody></table>';
        echo $doc; exit;
    }
}
