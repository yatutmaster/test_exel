<?php
////для перехвата ошибок
function exception_error_handler($severity, $message, $file, $line)
{
    if (!(error_reporting() & $severity)) {
        // Этот код ошибки не входит в error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}
set_error_handler("exception_error_handler");


// загрузка файла
$fileSize = 1000000; //1mb

if (!$file = $_FILES['userfile']['tmp_name'] ?? 0 or $_FILES['userfile']['error'] != 0) {
    die('Файл не загружен, или загружен с ошибкой');
}


if ($_FILES['userfile']['size'] > $fileSize) {
    die('Файл слишком большой, макс. размер 1mb');
}


require 'vendor/autoload.php';
///sheets
$sheetnames = ['first', 'second'];

$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$reader->setLoadSheetsOnly($sheetnames);
$reader->setReadDataOnly(true);

try {
    $spreadsheet = $reader->load($file);
} catch (Throwable $e) {

    die('Загруженый файл не является XLSX файлом!');
}


///validation
$errors = [];

//validation sheets
if (
    !$firstSheet = $spreadsheet->getSheetByName($sheetnames[0])
    or !$secondSheet = $spreadsheet->getSheetByName($sheetnames[1])
) {

    $errors[] = 'Файл не содержит следующие страницы ' . implode(' или ', $sheetnames) . '!';
} else {
    ///проверка первой страницы
    foreach ($firstSheet->getRowIterator() as $row) {

        $cellIterator = $row->getCellIterator('A', 'C');

        $id = $cellIterator->seek('A')->current()->getValue();
        $name = $cellIterator->seek('B')->current()->getValue();
        $summ = $cellIterator->seek('C')->current()->getValue();
        //все отрицательные и положительные числа сумм,
        //но есть один момент, пройдут числа вида 0.0000000
        //мы их тоже пропустим, и потом округлим до сотых
        //с регуляркой можно было бы сделать строже, но будет медленно и затратно.
        //Так же есть нюанс про $id, могут пройти числа 008888, это можно пофиксить, 
        //но опять же, если это необходимо, я решил не фиксить это
        if (empty($name) or !is_numeric($summ) or !($id > 0 and is_int(+$id))) {

            $template = 'Ошибка на странице %s! </br>' .
                'Строка "%s".</br>' .
                'В ячейке A должно быть положительное число, передано "%s".</br>' .
                'Ячейка B не должна быть пустой, передано "%s". </br>' .
                'В ячейке C должно быть число (начальный остаток), передано "%s".';

            $errors[] = sprintf(
                $template,
                $sheetnames[0],
                $row->getRowIndex(),
                htmlentities($id),
                htmlentities($name),
                htmlentities($summ)
            );
            break;
        }
    }
    ///проверка второй страницы
    foreach ($secondSheet->getRowIterator() as $row) {

        $cellIterator = $row->getCellIterator('A', 'B');

        $id = $cellIterator->seek('A')->current()->getValue();
        $action = $cellIterator->seek('B')->current()->getValue();

        if (!is_numeric($action) or !($id > 0 and is_int(+$id))) {

            $template = 'Ошибка на странице %s! </br>' .
                'Строка "%s".</br>' .
                'В ячейке A должно быть положительное число, передано "%s". </br>' .
                'В ячейке B должно быть число (сумма вода/вывода), передано "%s".';

            $errors[] = sprintf(
                $template,
                $sheetnames[1],
                $row->getRowIndex(),
                htmlentities($id),
                htmlentities($action)
            );
            break;
        }
    }
}


if (!empty($errors)) {
    echo '<div style="color:red">'.implode("</br>", $errors).'</div>';
    exit;
}

//end validation





// если все хорошо
echo '<table>' . PHP_EOL;
foreach ($firstSheet->getRowIterator() as $row) {

    echo '<tr>' . PHP_EOL;
    $cellIterator = $row->getCellIterator('A', 'C');

    $id = $cellIterator->seek('A')->current()->getValue();
    $name = $cellIterator->seek('B')->current()->getValue();
    $summ = $cellIterator->seek('C')->current()->getValue();

    foreach ($secondSheet->getRowIterator() as $row2) {
        $cellIterator2 = $row2->getCellIterator('A', 'B');
        if ($id != $cellIterator2->seek('A')->current()->getValue()) continue;
        $action = $cellIterator2->seek('B')->current()->getValue();
        $summ = $summ + $action;
    }

    echo '<td>' . $id . '</td>' .
        '<td>' . htmlentities($name) . '</td>' .
        '<td>' . number_format($summ, 2, '.', '') . ' руб.</td>' .
        '</tr>' . PHP_EOL;
}
echo '</table>' . PHP_EOL;
