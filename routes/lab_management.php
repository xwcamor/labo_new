<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LabManagement\TestDefinitionController;
use App\Http\Controllers\LabManagement\TestFieldController;
use App\Http\Controllers\LabManagement\TestGroupController;
use App\Http\Controllers\LabManagement\WorksheetController;
use App\Http\Controllers\LabManagement\QcChartController;
use App\Http\Controllers\LabManagement\ReceptionController;
use App\Http\Controllers\LabManagement\InstrumentFileController;
use App\Http\Controllers\LabManagement\TestReportController;
use App\Http\Controllers\LabManagement\SampleReportController;

/*
|--------------------------------------------------------------------------
| LabManagement
|--------------------------------------------------------------------------
| Modulos generados con make:module. Cada modulo se gobierna por permisos
| Spatie: test_groups.view, test_groups.create, etc.
|
| ORDEN DE RUTAS CRITICO: las rutas con paths estaticos (test_groups/create,
| test_groups/trash, test_groups/export_*) DEBEN ir ANTES que test_groups/{testGroup}.
*/

Route::prefix('lab_management')->name('lab_management.')->group(function () {

    // ── TestGroups ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('test_groups/trash',                  [TestGroupController::class, 'trash'])->name('test_groups.trash');
        Route::post('test_groups/bulk_restore',          [TestGroupController::class, 'bulkRestore'])->name('test_groups.bulk_restore');
        Route::post('test_groups/{slug}/restore',        [TestGroupController::class, 'restore'])->name('test_groups.restore');
        Route::get('test_groups/{slug}/restore',         fn () => redirect()->route('lab_management.test_groups.trash'));
        Route::delete('test_groups/{slug}/force_delete', [TestGroupController::class, 'forceDelete'])->name('test_groups.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:test_groups.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('test_groups/export_excel', [TestGroupController::class, 'exportExcel'])->name('test_groups.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('test_groups/export_pdf',   [TestGroupController::class, 'exportPdf'])->name('test_groups.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('test_groups/export_word',  [TestGroupController::class, 'exportWord'])->name('test_groups.export_word');
        Route::middleware('throttle:5,1')
            ->post('test_groups/export_csv',   [TestGroupController::class, 'exportCsv'])->name('test_groups.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:test_groups.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('test_groups/import',          [TestGroupController::class, 'import'])->name('test_groups.import');
        Route::get('test_groups/import_template',  [TestGroupController::class, 'importTemplate'])->name('test_groups.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:test_groups.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('test_groups/bulk_delete',     [TestGroupController::class, 'bulkDelete'])->name('test_groups.bulk_delete');
        Route::post('test_groups/bulk_set_active', [TestGroupController::class, 'bulkSetActive'])->name('test_groups.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:test_groups.delete')->group(function () {
        Route::post('test_groups/undo_last_delete', [TestGroupController::class, 'undoLastDelete'])->name('test_groups.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:test_groups.edit')->group(function () {
        Route::get('test_groups/edit_all',         [TestGroupController::class, 'editAll'])->name('test_groups.edit_all');
        Route::post('test_groups/edit_all/update', [TestGroupController::class, 'editAllUpdate'])->name('test_groups.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:test_groups.create')->group(function () {
        Route::get('test_groups/create', [TestGroupController::class, 'create'])->name('test_groups.create');
        Route::post('test_groups',       [TestGroupController::class, 'store'])->name('test_groups.store');
        Route::post('test_groups/{testGroup}/duplicate', [TestGroupController::class, 'duplicate'])->name('test_groups.duplicate');
    });

    Route::middleware('permission:test_groups.view')->group(function () {
        Route::get('test_groups',                [TestGroupController::class, 'index'])->name('test_groups.index');
        Route::get('test_groups/{testGroup}',  [TestGroupController::class, 'show'])->name('test_groups.show');
    });
    Route::middleware('permission:test_groups.edit')->group(function () {
        Route::get('test_groups/{testGroup}/edit', [TestGroupController::class, 'edit'])->name('test_groups.edit');
        Route::put('test_groups/{testGroup}',      [TestGroupController::class, 'update'])->name('test_groups.update');
    });
    Route::middleware('permission:test_groups.delete')->group(function () {
        Route::get('test_groups/{testGroup}/delete',        [TestGroupController::class, 'delete'])->name('test_groups.delete');
        Route::delete('test_groups/{testGroup}/deleteSave', [TestGroupController::class, 'deleteSave'])->name('test_groups.deleteSave');
    });

    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('test_groups/{testGroup}/lock',   [TestGroupController::class, 'lock'])->name('test_groups.lock');
        Route::post('test_groups/{testGroup}/unlock', [TestGroupController::class, 'unlock'])->name('test_groups.unlock');
    });


    // ── TestDefinitions ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('test_definitions/trash',                  [TestDefinitionController::class, 'trash'])->name('test_definitions.trash');
        Route::post('test_definitions/bulk_restore',          [TestDefinitionController::class, 'bulkRestore'])->name('test_definitions.bulk_restore');
        Route::post('test_definitions/{slug}/restore',        [TestDefinitionController::class, 'restore'])->name('test_definitions.restore');
        Route::get('test_definitions/{slug}/restore',         fn () => redirect()->route('lab_management.test_definitions.trash'));
        Route::delete('test_definitions/{slug}/force_delete', [TestDefinitionController::class, 'forceDelete'])->name('test_definitions.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:test_definitions.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('test_definitions/export_excel', [TestDefinitionController::class, 'exportExcel'])->name('test_definitions.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('test_definitions/export_pdf',   [TestDefinitionController::class, 'exportPdf'])->name('test_definitions.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('test_definitions/export_word',  [TestDefinitionController::class, 'exportWord'])->name('test_definitions.export_word');
        Route::middleware('throttle:5,1')
            ->post('test_definitions/export_csv',   [TestDefinitionController::class, 'exportCsv'])->name('test_definitions.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:test_definitions.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('test_definitions/import',          [TestDefinitionController::class, 'import'])->name('test_definitions.import');
        Route::get('test_definitions/import_template',  [TestDefinitionController::class, 'importTemplate'])->name('test_definitions.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:test_definitions.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('test_definitions/bulk_delete',     [TestDefinitionController::class, 'bulkDelete'])->name('test_definitions.bulk_delete');
        Route::post('test_definitions/bulk_set_active', [TestDefinitionController::class, 'bulkSetActive'])->name('test_definitions.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:test_definitions.delete')->group(function () {
        Route::post('test_definitions/undo_last_delete', [TestDefinitionController::class, 'undoLastDelete'])->name('test_definitions.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:test_definitions.edit')->group(function () {
        Route::get('test_definitions/edit_all',         [TestDefinitionController::class, 'editAll'])->name('test_definitions.edit_all');
        Route::post('test_definitions/edit_all/update', [TestDefinitionController::class, 'editAllUpdate'])->name('test_definitions.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:test_definitions.create')->group(function () {
        Route::get('test_definitions/create', [TestDefinitionController::class, 'create'])->name('test_definitions.create');
        Route::post('test_definitions',       [TestDefinitionController::class, 'store'])->name('test_definitions.store');
        Route::post('test_definitions/{testDefinition}/duplicate', [TestDefinitionController::class, 'duplicate'])->name('test_definitions.duplicate');
    });

    Route::middleware('permission:test_definitions.view')->group(function () {
        Route::get('test_definitions',                [TestDefinitionController::class, 'index'])->name('test_definitions.index');
        Route::get('test_definitions/{testDefinition}',  [TestDefinitionController::class, 'show'])->name('test_definitions.show');
    });
    Route::middleware('permission:test_definitions.edit')->group(function () {
        Route::get('test_definitions/{testDefinition}/edit', [TestDefinitionController::class, 'edit'])->name('test_definitions.edit');
        Route::put('test_definitions/{testDefinition}',      [TestDefinitionController::class, 'update'])->name('test_definitions.update');
    });
    Route::middleware('permission:test_definitions.delete')->group(function () {
        Route::get('test_definitions/{testDefinition}/delete',        [TestDefinitionController::class, 'delete'])->name('test_definitions.delete');
        Route::delete('test_definitions/{testDefinition}/deleteSave', [TestDefinitionController::class, 'deleteSave'])->name('test_definitions.deleteSave');
    });

    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('test_definitions/{testDefinition}/lock',   [TestDefinitionController::class, 'lock'])->name('test_definitions.lock');
        Route::post('test_definitions/{testDefinition}/unlock', [TestDefinitionController::class, 'unlock'])->name('test_definitions.unlock');
    });

    /*
    |----------------------------------------------------------------------
    | Columnas de una prueba (test_fields) y sus valores constantes
    |----------------------------------------------------------------------
    | Van ANIDADAS bajo la prueba y no como módulo suelto. En el sistema Rails
    | viejo había dos pantallas que editaban lo mismo —el editor dentro de la
    | prueba y un CRUD aparte de columnas— y se desincronizaron: la de alta no
    | mostraba el orden ni la acreditación de las opciones y la de edición sí.
    | Una columna no significa nada fuera de su prueba: su fórmula referencia a
    | las otras columnas de esa misma prueba.
    |
    | OJO CON EL ORDEN: `fields/reorder` y `fields/check_formula` tienen que ir
    | ANTES de `fields/{field}`, o Laravel toma "reorder" como el id del campo.
    */
    Route::middleware('permission:test_definitions.view')->group(function () {
        Route::get('test_definitions/{test_definition}/fields',    [TestFieldController::class, 'index'])->name('test_definitions.fields.index');
        Route::get('test_definitions/{test_definition}/constants', [TestFieldController::class, 'constants'])->name('test_definitions.constants.index');
    });

    Route::middleware('permission:test_definitions.edit')->group(function () {
        Route::post('test_definitions/{test_definition}/fields/reorder',        [TestFieldController::class, 'reorder'])->name('test_definitions.fields.reorder');
        Route::post('test_definitions/{test_definition}/fields/check_formula',  [TestFieldController::class, 'checkFormula'])->name('test_definitions.fields.check_formula');
        Route::post('test_definitions/{test_definition}/constants',             [TestFieldController::class, 'updateConstants'])->name('test_definitions.constants.update');
        Route::post('test_definitions/{test_definition}/fields',                [TestFieldController::class, 'store'])->name('test_definitions.fields.store');
        Route::put('test_definitions/{test_definition}/fields/{field}',         [TestFieldController::class, 'update'])->name('test_definitions.fields.update');
        Route::delete('test_definitions/{test_definition}/fields/{field}',      [TestFieldController::class, 'destroy'])->name('test_definitions.fields.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Hojas de trabajo (la bancada)
    |----------------------------------------------------------------------
    | No es un catálogo: no tiene alta masiva, ni edición en lote, ni
    | duplicado, ni papelera. Tiene un flujo, y el flujo está gobernado por
    | permisos distintos a propósito.
    |
    | `worksheets.validate` es un permiso APARTE de `worksheets.edit`, y esa es
    | la corrección de un agujero real del sistema viejo: allá la pantalla de
    | validar escondía su enlace a los no supervisores pero la acción verificaba
    | el permiso de EDITAR, así que cualquiera que pudiera editar podía validar
    | escribiendo la dirección a mano.
    */
    Route::middleware('permission:worksheets.view')->group(function () {
        Route::get('worksheets',             [WorksheetController::class, 'index'])->name('worksheets.index');
        Route::get('worksheets/create',      [WorksheetController::class, 'create'])->name('worksheets.create');
        Route::get('worksheets/{worksheet}', [WorksheetController::class, 'show'])->name('worksheets.show');
    });

    Route::middleware('permission:worksheets.create')->group(function () {
        Route::post('worksheets', [WorksheetController::class, 'store'])->name('worksheets.store');
    });

    Route::middleware('permission:worksheets.edit')->group(function () {
        Route::post('worksheets/{worksheet}/rows',        [WorksheetController::class, 'saveRow'])->name('worksheets.rows.save');
        Route::delete('worksheets/{worksheet}/rows/{row}', [WorksheetController::class, 'destroyRow'])->name('worksheets.rows.destroy');
        Route::post('worksheets/{worksheet}/close',       [WorksheetController::class, 'close'])->name('worksheets.close');
        // Vista previa del cálculo mientras el analista escribe. NO guarda nada.
        //
        // El límite es 120 por minuto y por usuario. La grilla espera 400 ms de
        // silencio antes de preguntar y cancela la petición anterior si el
        // analista sigue tecleando, así que una carga normal manda del orden de
        // una petición por celda terminada: 120 cubre con holgura la tanda más
        // rápida y deja igual un techo para lo que no sea la pantalla. Va acá y
        // no en el controlador porque el gasto que se quiere evitar es el de
        // atender la petición, no el de calcularla.
        Route::middleware('throttle:120,1')
            ->post('worksheets/{worksheet}/preview',      [WorksheetController::class, 'preview'])->name('worksheets.preview');
        // Lectura del archivo crudo del instrumento. Devuelve lo interpretado
        // para que el analista lo confirme; NO escribe en la hoja por su cuenta.
        Route::post('worksheets/{worksheet}/instrument_file', [InstrumentFileController::class, 'store'])->name('worksheets.instrument_file');
    });

    // Validar es del supervisor. Ver el comentario de arriba.
    Route::middleware('permission:worksheets.validate')->group(function () {
        Route::post('worksheets/{worksheet}/validate', [WorksheetController::class, 'validateSheet'])->name('worksheets.validate');
    });

    Route::middleware('permission:worksheets.delete')->group(function () {
        Route::post('worksheets/{worksheet}/void', [WorksheetController::class, 'void'])->name('worksheets.void');
    });

    /*
    |----------------------------------------------------------------------
    | Recepción de muestras
    |----------------------------------------------------------------------
    | Es la puerta de entrada del laboratorio: acá se emiten los correlativos y
    | acá se declara de qué equipo es cada muestra y qué pruebas le tocan.
    |
    | ORDEN: `receptions/create` va ANTES de `receptions/{reception}` o el
    | resolvedor de modelo intentaría buscar una recepción llamada "create".
    |
    | Nótese que NO hay ruta que recalcule estados. En el sistema anterior eso
    | ocurría solo, dentro del GET de la ficha, desde la propia vista.
    */
    Route::middleware('permission:receptions.create')->group(function () {
        Route::get('receptions/create', [ReceptionController::class, 'create'])->name('receptions.create');
        Route::post('receptions',       [ReceptionController::class, 'store'])->name('receptions.store');
    });

    Route::middleware('permission:receptions.view')->group(function () {
        Route::get('receptions',               [ReceptionController::class, 'index'])->name('receptions.index');
        Route::get('receptions/{reception}',   [ReceptionController::class, 'show'])->name('receptions.show');
    });

    Route::middleware('permission:receptions.edit')->group(function () {
        Route::get('receptions/{reception}/edit', [ReceptionController::class, 'edit'])->name('receptions.edit');
        Route::put('receptions/{reception}',      [ReceptionController::class, 'update'])->name('receptions.update');

        // Emitir los correlativos. Es el acto que convierte el borrador en
        // trabajo del laboratorio, y ocurre UNA vez.
        Route::post('receptions/{reception}/confirm', [ReceptionController::class, 'confirm'])->name('receptions.confirm');

        // De qué equipo se tomó la muestra, y qué pruebas se le piden.
        Route::patch('receptions/{reception}/samples/{sample}/equipment', [ReceptionController::class, 'assignEquipment'])->name('receptions.samples.equipment');
        Route::post('receptions/{reception}/tests', [ReceptionController::class, 'requestTests'])->name('receptions.tests');
    });

    /*
    |----------------------------------------------------------------------
    | El informe de ensayo — lo que recibe el cliente
    |----------------------------------------------------------------------
    | Se gobierna por `receptions.view`: quien puede ver la entrega puede
    | imprimir su informe. Emitirlo NO cambia nada de la muestra; solo deja
    | constancia en el registro de auditoría.
    */
    Route::middleware('permission:receptions.view')->group(function () {
        // La vista previa desde la muestra: el papel tal como saldría, todavía
        // sin correlativo. Sirve para revisar antes de emitir.
        Route::get('samples/{sample}/report', [TestReportController::class, 'pdf'])->name('samples.report');

        // El informe REGISTRADO, con su correlativo y sus ensayos elegidos.
        Route::get('samples/{sample}/reports/new', [SampleReportController::class, 'create'])->name('sample_reports.create');
        Route::get('reports/{report}/edit', [SampleReportController::class, 'edit'])->name('sample_reports.edit');
        Route::get('reports/{report}/pdf', [TestReportController::class, 'reportPdf'])->name('sample_reports.pdf');
    });

    Route::middleware('permission:receptions.edit')->group(function () {
        Route::post('samples/{sample}/reports', [SampleReportController::class, 'store'])->name('sample_reports.store');
        Route::put('reports/{report}', [SampleReportController::class, 'update'])->name('sample_reports.update');
        Route::post('reports/{report}/issue', [SampleReportController::class, 'issue'])->name('sample_reports.issue');
    });

    Route::middleware('permission:receptions.delete')->group(function () {
        Route::delete('receptions/{reception}', [ReceptionController::class, 'destroy'])->name('receptions.destroy');
        Route::delete('reports/{report}', [SampleReportController::class, 'destroy'])->name('sample_reports.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Cartas de control (Límite de Tendencias + Tendencias, unificados)
    |----------------------------------------------------------------------
    | En el sistema viejo eran dos pantallas sin ninguna clave foránea entre
    | ellas: la de tendencias buscaba los límites con
    | `PatronTendence.find(id_de_la_prueba)`, confiando en que los ids de las
    | dos tablas coincidieran. Cualquier desalineación cruzaba los límites de
    | una prueba con los datos de otra, sin que nada lo detectara.
    */
    Route::middleware('permission:qc_charts.view')->group(function () {
        Route::get('qc_charts',            [QcChartController::class, 'index'])->name('qc_charts.index');
        Route::get('qc_charts/create',     [QcChartController::class, 'create'])->name('qc_charts.create');
        Route::get('qc_charts/{qc_chart}', [QcChartController::class, 'show'])->name('qc_charts.show');
    });

    Route::middleware('permission:qc_charts.create')->group(function () {
        Route::post('qc_charts', [QcChartController::class, 'store'])->name('qc_charts.store');
    });

    Route::middleware('permission:qc_charts.edit')->group(function () {
        Route::get('qc_charts/{qc_chart}/edit', [QcChartController::class, 'edit'])->name('qc_charts.edit');
        Route::put('qc_charts/{qc_chart}',      [QcChartController::class, 'update'])->name('qc_charts.update');
        // Excluir una medición del cálculo, con motivo. No se borra.
        Route::patch('qc_charts/{qc_chart}/points/{point}', [QcChartController::class, 'excludePoint'])->name('qc_charts.points.update');
    });

    Route::middleware('permission:qc_charts.delete')->group(function () {
        Route::delete('qc_charts/{qc_chart}', [QcChartController::class, 'destroy'])->name('qc_charts.destroy');
    });
});
