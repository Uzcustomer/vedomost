
<?php

// Word hujjat uchun headerlar
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=YN_oldi_qaydnoma.doc");

// Kelayotgan qiymatlar (POST orqali)
$student = $_POST['student'] ?? 'Nomaʼlum talaba';
$group   = $_POST['group'] ?? 'Nomaʼlum guruh';
$fan     = $_POST['fan'] ?? 'Fan nomi yo‘q';
$today   = date("Y-m-d");

// Wordga mos HTML
$doc = '
<html xmlns:w="urn:schemas-microsoft-com:office:word">
<body>

<h2 style="text-align:center;">Yakuniy Nazorat Oldi Qaydnoma</h2>

<p><b>Talaba:</b> ' . $student . '</p>
<p><b>Guruh:</b> ' . $group . '</p>
<p><b>Fan:</b> ' . $fan . '</p>
<p><b>Sana:</b> ' . $today . '</p>

<br><br>

<p><b>Izoh:</b> Yakuniy nazorat oldidan tayyorlangan qaydnoma.</p>

</body>
</html>
';

echo $doc;
exit;
