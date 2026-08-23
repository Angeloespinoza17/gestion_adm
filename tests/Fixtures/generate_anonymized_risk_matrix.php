<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$target = __DIR__.'/matriz-iper-anonimizada.xlsx';
$workbook = new Spreadsheet;
$matrix = $workbook->getActiveSheet();
$matrix->setTitle('IPER Anonimizada');
$matrix->fromArray([
    ['Actividad', 'Tarea', 'Puesto de trabajo', 'Lugar específico', 'Exposición F', 'Exposición M', 'Exposición Otro', 'Rutinaria/No rutinaria', 'Factor de riesgo', 'Peligro', 'Riesgo específico', 'Daño posible', 'Probabilidad', 'Consecuencia', 'Magnitud', 'Clasificación', 'Tipo de control', 'Medida de control', 'Riesgo controlado', 'Responsable', 'Periodicidad'],
    ['Operación de prueba', 'Preparar material', 'Cargo A', 'Zona Norte', 2, 1, 0, 'Rutinaria', 'Superficie húmeda', 'Piso resbaladizo', 'Caída al mismo nivel', 'Lesión musculoesquelética', 'Baja (2)', 'Alta (4)', 2, 'Tolerable', 'Ingeniería', 'Instalar superficie antideslizante', 'SI', 'Responsable A', 'Mensual'],
    ['Operación de prueba', 'Verificar equipo', 'Cargo B', 'Zona Sur', 0, 2, 1, 'No rutinaria', 'Energía presente', 'Parte móvil', 'Contacto con parte móvil', 'Lesión en extremidad', 'Media (2)', 'Media (2)', 16, 'Intolerable', 'Administrativo', 'Aplicar lista de verificación', 'No', 'Responsable B', 'Trimestral'],
    ['Operación de prueba', 'Ordenar insumos', 'Cargo C', 'Bodega', 1, 1, 0, 'Rutinaria', 'Apilamiento', 'Carga almacenada', 'Caída de objeto', 'Contusión', 1, 2, 2, 'Tolerable', 'EPP', 'Usar protección de cabeza', 'NO', 'Responsable C', 'Cada vez'],
    ['Operación de prueba', 'Inspeccionar zona', 'Cargo D', 'Patio', 1, 0, 0, 'Ocasional', 'Desnivel', 'Superficie irregular', 'Tropiezo', 'Esguince', 1, 1, 1, 'Tolerable', 'Eliminación', 'Eliminar desnivel', 'Si', 'Responsable D', 'Una vez'],
]);

$criteria = $workbook->createSheet();
$criteria->setTitle('Criterios IPER');
$criteria->fromArray([
    ['Elemento', 'Regla de prueba'],
    ['Probabilidad', '1, 2 o 4'],
    ['Consecuencia', '1, 2 o 4'],
    ['VEP', 'Probabilidad por consecuencia; el backend recalcula siempre'],
]);

$duplicateCriteria = $workbook->createSheet();
$duplicateCriteria->setTitle('Criterios IPER copia');
$duplicateCriteria->fromArray($criteria->toArray());

(new Xlsx($workbook))->save($target);
$workbook->disconnectWorksheets();

fwrite(STDOUT, $target.PHP_EOL);
