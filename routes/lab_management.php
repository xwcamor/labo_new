<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LabManagement\DiagnosisTemplateController;
use App\Http\Controllers\LabManagement\TestDefinitionController;
use App\Http\Controllers\LabManagement\TestFieldController;
use App\Http\Controllers\LabManagement\TestGroupController;
use App\Http\Controllers\LabManagement\WorksheetController;
use App\Http\Controllers\LabManagement\QcChartController;
use App\Http\Controllers\LabManagement\ReceptionController;
use App\Http\Controllers\LabManagement\InstrumentFileController;
use App\Http\Controllers\LabManagement\TestReportController;
use App\Http\Controllers\LabManagement\SampleReportController;
use App\Http\Controllers\LabManagement\LabReportController;
use App\Http\Controllers\LabManagement\AmbientLogController;
use App\Http\Controllers\LabManagement\TrendController;
use App\Http\Controllers\LabManagement\ReportCatalogController;
use App\Http\Controllers\LabManagement\SampleLabelController;
use App\Http\Controllers\LabManagement\StockItemController;
use App\Http\Controllers\LabManagement\StockLoanController;

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
    // La papelera y la restauración: solo super, como en el resto de los
    // módulos. VAN ANTES de `worksheets/{worksheet}` o "trash" se leería como
    // el identificador de una hoja.
    //
    // NO hay borrado definitivo: una hoja es la constancia de un ensayo que el
    // laboratorio corrió, y sus valores respaldan informes ya firmados. Ver el
    // comentario de `WorksheetController::trash()`.
    Route::middleware('role:super')->group(function () {
        Route::get('worksheets/trash',           [WorksheetController::class, 'trash'])->name('worksheets.trash');
        Route::post('worksheets/{slug}/restore', [WorksheetController::class, 'restore'])->name('worksheets.restore');
    });

    // Exportación del LISTADO (no de los valores medidos: eso es el informe).
    // Cada formato con su tope de plan; el CSV va por lotes y no lleva tope.
    Route::middleware('permission:worksheets.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('worksheets/export_excel', [WorksheetController::class, 'exportExcel'])->name('worksheets.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('worksheets/export_pdf',   [WorksheetController::class, 'exportPdf'])->name('worksheets.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('worksheets/export_word',  [WorksheetController::class, 'exportWord'])->name('worksheets.export_word');
        Route::middleware('throttle:5,1')
            ->post('worksheets/export_csv',   [WorksheetController::class, 'exportCsv'])->name('worksheets.export_csv');
    });

    Route::middleware('permission:worksheets.view')->group(function () {
        Route::get('worksheets',             [WorksheetController::class, 'index'])->name('worksheets.index');
        Route::get('worksheets/create',      [WorksheetController::class, 'create'])->name('worksheets.create');
        Route::get('worksheets/{worksheet}', [WorksheetController::class, 'show'])->name('worksheets.show');
    });

    Route::middleware('permission:worksheets.create')->group(function () {
        Route::post('worksheets', [WorksheetController::class, 'store'])->name('worksheets.store');
    });

    Route::middleware('permission:worksheets.edit')->group(function () {
        // La CABECERA de la hoja (fecha, analista, condiciones, notas). Los
        // valores se editan en la grilla de la ficha, no acá.
        Route::get('worksheets/{worksheet}/edit', [WorksheetController::class, 'edit'])->name('worksheets.edit');
        Route::put('worksheets/{worksheet}',      [WorksheetController::class, 'update'])->name('worksheets.update');
    });

    // Bloquear/desbloquear (Lockable) — solo super|admin, como en el resto de
    // los módulos. Es el mismo candado que el bloqueo automático por antigüedad
    // pone solo; acá se pone y se saca a mano, y queda auditado.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('worksheets/{worksheet}/lock',   [WorksheetController::class, 'lock'])->name('worksheets.lock');
        Route::post('worksheets/{worksheet}/unlock', [WorksheetController::class, 'unlock'])->name('worksheets.unlock');
    });

    Route::middleware('permission:worksheets.edit')->group(function () {
        Route::post('worksheets/{worksheet}/rows',        [WorksheetController::class, 'saveRow'])->name('worksheets.rows.save');
        // "Guardar todo": las filas con cambios, en una sola transacción. Va
        // ANTES de `rows/{row}` para que la palabra "bulk" no se lea como el id
        // de una fila.
        Route::post('worksheets/{worksheet}/rows/bulk',   [WorksheetController::class, 'saveRows'])->name('worksheets.rows.bulk');
        // "Traer las muestras pendientes": las agrega todas de una vez. La
        // lista la resuelve el servidor, no llega del navegador.
        Route::post('worksheets/{worksheet}/rows/fill',   [WorksheetController::class, 'fillPending'])->name('worksheets.rows.fill');
        Route::delete('worksheets/{worksheet}/rows/{row}', [WorksheetController::class, 'destroyRow'])->name('worksheets.rows.destroy');
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

    // Dar de baja la hoja, con su motivo. Se llamaba "anular" y era un estado
    // más que no aportaba nada: hacía lo mismo que un borrado lógico bien
    // hecho, que es lo que tenía el sistema anterior.
    Route::middleware('permission:worksheets.delete')->group(function () {
        Route::get('worksheets/{worksheet}/delete', [WorksheetController::class, 'delete'])->name('worksheets.delete');
        Route::delete('worksheets/{worksheet}',     [WorksheetController::class, 'destroy'])->name('worksheets.destroy');
    });

    // Baja masiva desde el listado. VA DESPUÉS de `worksheets/{worksheet}` en el
    // archivo pero es POST, así que no compite con el GET de la ficha; el
    // `plan_feature` y el throttle son los mismos que en el resto de los índices.
    Route::middleware(['permission:worksheets.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('worksheets/bulk_delete', [WorksheetController::class, 'bulkDelete'])->name('worksheets.bulk_delete');
    });

    // Deshacer la última baja (ventana de 60 s). NO lleva `plan_feature`: es el
    // arrepentimiento de una acción que el plan ya permitió, y cobrarle el plan
    // dejaría al usuario con la baja hecha y sin forma de volver atrás.
    Route::middleware('permission:worksheets.delete')->group(function () {
        Route::post('worksheets/undo_last_delete', [WorksheetController::class, 'undoLastDelete'])->name('worksheets.undo_last_delete');
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

        // Corregir la cantidad después de confirmar («puse 32 y eran 20»),
        // solo mientras los números sigan siendo la cola del año.
        Route::post('receptions/{reception}/adjust', [ReceptionController::class, 'adjustSamples'])->name('receptions.adjust');

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
        // El listado GLOBAL de informes ("Listado de Nº de Reportes" del sistema
        // anterior): lo emitido por el laboratorio entero, no lo de una entrega.
        // Va ANTES de `reports/{report}/...` para que la palabra "reports" no se
        // lea como el slug de un informe.
        Route::get('reports', [SampleReportController::class, 'index'])->name('sample_reports.index');

        // La vista previa desde la muestra: el papel tal como saldría, todavía
        // sin correlativo. Sirve para revisar antes de emitir.
        Route::get('samples/{sample}/report', [TestReportController::class, 'pdf'])->name('samples.report');

        // El informe REGISTRADO, con su correlativo y sus ensayos elegidos.
        Route::get('samples/{sample}/reports/new', [SampleReportController::class, 'create'])->name('sample_reports.create');
        Route::get('reports/{report}/edit', [SampleReportController::class, 'edit'])->name('sample_reports.edit');
        Route::get('reports/{report}/pdf', [TestReportController::class, 'reportPdf'])->name('sample_reports.pdf');
        // El mismo informe con la MAQUETA DEL SISTEMA ANTERIOR (una hoja por
        // prueba, sello ANAB, relaciones de gases). La elección de plantilla
        // es de quien emite; los datos son los mismos.
        Route::get('reports/{report}/pdf-clasico', [TestReportController::class, 'reportPdfLegacy'])->name('sample_reports.pdf_legacy');
        // Los valores detectados y el análisis de resultados del informe.
        Route::get('reports/{report}/analysis', [SampleReportController::class, 'analysis'])->name('sample_reports.analysis');
    });

    Route::middleware('permission:receptions.edit')->group(function () {
        Route::post('samples/{sample}/reports', [SampleReportController::class, 'store'])->name('sample_reports.store');
        Route::put('reports/{report}', [SampleReportController::class, 'update'])->name('sample_reports.update');
        Route::post('reports/{report}/issue', [SampleReportController::class, 'issue'])->name('sample_reports.issue');
        // El candado al revés: el informe emitido vuelve a borrador para
        // corregirlo. La ruta va con `receptions.edit` y el CONTROLADOR exige
        // además admin o super — emitir es el trabajo del día, desbloquear es
        // admitir que salió un papel con un error.
        Route::post('reports/{report}/unissue', [SampleReportController::class, 'unissue'])->name('sample_reports.unissue');
        Route::post('reports/{report}/autodiagnose', [SampleReportController::class, 'autodiagnose'])->name('sample_reports.autodiagnose');
        Route::put('reports/{report}/analysis', [SampleReportController::class, 'saveAnalysis'])->name('sample_reports.analysis.save');
        // Dar por bueno el análisis. Es la condición para emitir: ver
        // `SampleReportController::confirmAnalysis`.
        Route::post('reports/{report}/analysis/confirm', [SampleReportController::class, 'confirmAnalysis'])->name('sample_reports.analysis.confirm');
    });

    // La descarga en Excel va con el permiso de LECTURA: es la misma
    // información de la pantalla, en un archivo.
    Route::middleware('permission:receptions.view')->group(function () {
        Route::get('receptions/{reception}/export', [ReceptionController::class, 'export'])->name('receptions.export');

        // El pliego de etiquetas para los envases. Va con el permiso de
        // LECTURA a propósito: imprimir una etiqueta no cambia la muestra, y
        // quien reparte los envases a la bancada no siempre es quien puede
        // editar la entrega. Lo que sí queda es la constancia de la impresión.
        Route::get('receptions/{reception}/labels', [SampleLabelController::class, 'sheet'])->name('receptions.labels');
    });

    /*
    |----------------------------------------------------------------------
    | Tendencias del equipo del cliente
    |----------------------------------------------------------------------
    | La evolución del aceite de un transformador en el tiempo. Va con el
    | permiso de LECTURA de equipos porque es la historia de ese activo: quien
    | puede ver el equipo puede ver cómo viene.
    */
    Route::middleware('permission:equipment.view')->group(function () {
        Route::get('trends', [TrendController::class, 'index'])->name('trends.index');
    });

    /*
    |----------------------------------------------------------------------
    | Bitácora de condiciones ambientales
    |----------------------------------------------------------------------
    | Una lectura por sala y por día. En el sistema anterior eran DOS módulos
    | gemelos (cromatografía y fisicoquímico) con el mismo acceso 64; acá la
    | sala es un dato y el módulo es uno solo.
    |
    | Un permiso propio y no el de hojas de trabajo: la carga la hace quien
    | abre el laboratorio a la mañana, que no es necesariamente el analista.
    */
    Route::middleware('permission:ambient_logs.view')->group(function () {
        Route::get('ambient_logs', [AmbientLogController::class, 'index'])->name('ambient_logs.index');
    });

    Route::middleware('permission:ambient_logs.create')->group(function () {
        Route::post('ambient_logs', [AmbientLogController::class, 'store'])->name('ambient_logs.store');
    });

    Route::middleware('permission:ambient_logs.edit')->group(function () {
        Route::put('ambient_logs/{ambient_log}', [AmbientLogController::class, 'update'])->name('ambient_logs.update');
    });

    Route::middleware('permission:ambient_logs.delete')->group(function () {
        Route::delete('ambient_logs/{ambient_log}', [AmbientLogController::class, 'destroy'])->name('ambient_logs.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Almacén: artículos y préstamos
    |----------------------------------------------------------------------
    | El «Seguimiento de Equipos» del sistema anterior (accesos 57 y 58), con
    | sus cinco tablas reducidas a cuatro y sus cuatro agujeros tapados — el
    | detalle está en la migración `create_stock_tables`.
    |
    | Dos permisos separados, como allá: mantener el catálogo de artículos es
    | tarea de quien administra el almacén; llevarse un frasco a la bancada lo
    | hace cualquier analista.
    */
    Route::middleware('permission:stock_items.view')->group(function () {
        Route::get('stock_items', [StockItemController::class, 'index'])->name('stock_items.index');
    });

    Route::middleware('permission:stock_items.create')->group(function () {
        Route::post('stock_items', [StockItemController::class, 'store'])->name('stock_items.store');
    });

    Route::middleware('permission:stock_items.edit')->group(function () {
        Route::put('stock_items/{stock_item}', [StockItemController::class, 'update'])->name('stock_items.update');
    });

    Route::middleware('permission:stock_items.delete')->group(function () {
        Route::delete('stock_items/{stock_item}', [StockItemController::class, 'destroy'])->name('stock_items.destroy');
    });

    Route::middleware('permission:stock_loans.view')->group(function () {
        Route::get('stock_loans', [StockLoanController::class, 'index'])->name('stock_loans.index');
        Route::get('stock_loans/{stock_loan}', [StockLoanController::class, 'show'])->name('stock_loans.show');
    });

    Route::middleware('permission:stock_loans.create')->group(function () {
        Route::post('stock_loans', [StockLoanController::class, 'store'])->name('stock_loans.store');
    });

    Route::middleware('permission:stock_loans.edit')->group(function () {
        Route::put('stock_loans/{stock_loan}', [StockLoanController::class, 'update'])->name('stock_loans.update');
        // La devolución va con el permiso de EDICIÓN del préstamo y no con uno
        // propio: registrar que algo volvió es corregir el préstamo, y quien
        // recibe el material en el almacén es quien lo anota.
        Route::post('stock_loans/{stock_loan}/returns', [StockLoanController::class, 'storeReturn'])->name('stock_loans.returns.store');
        Route::delete('stock_loans/{stock_loan}/returns/{stock_return}', [StockLoanController::class, 'destroyReturn'])->name('stock_loans.returns.destroy');
    });

    Route::middleware('permission:stock_loans.delete')->group(function () {
        Route::delete('stock_loans/{stock_loan}', [StockLoanController::class, 'destroy'])->name('stock_loans.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Reportes de Lab. — los 7 Excel del sistema antiguo
    |----------------------------------------------------------------------
    | Una pantalla con el rango de fecha de recepción y una descarga GET
    | síncrona por reporte. Permiso propio (`lab_reports`) porque en el viejo
    | cada reporte tenía su acceso (50-55 y 66) y no todo el que carga
    | recepciones debe ver la planilla completa de resultados de todos los
    | clientes.
    */
    Route::middleware('permission:lab_reports.view')->group(function () {
        Route::get('lab_reports', [LabReportController::class, 'index'])->name('lab_reports.index');
        // El 404 del reporte inexistente lo da el controlador (la lista vive
        // en su constante); un `whereIn` acá dejaba la URL sin ruta y el shell
        // la convertía en redirección, no en 404.
        Route::get('lab_reports/{report}', [LabReportController::class, 'download'])->name('lab_reports.download');
    });

    /*
     * El candado de la recepción. Va con `receptions.edit`: congelar un registro
     * es una decisión de quien lo administra, no una baja.
     */
    Route::middleware('permission:receptions.edit')->group(function () {
        Route::post('receptions/{reception}/lock',   [ReceptionController::class, 'lock'])->name('receptions.lock');
        Route::post('receptions/{reception}/unlock', [ReceptionController::class, 'unlock'])->name('receptions.unlock');
    });

    Route::middleware('permission:receptions.delete')->group(function () {
        // La pantalla de confirmación va con el mismo permiso que la baja.
        Route::get('receptions/{reception}/delete', [ReceptionController::class, 'delete'])->name('receptions.delete');
        Route::delete('receptions/{reception}', [ReceptionController::class, 'destroy'])->name('receptions.destroy');
        Route::delete('reports/{report}', [SampleReportController::class, 'destroy'])->name('sample_reports.destroy');
    });

    // Baja masiva desde el listado — mismo gating que el resto de los bulk
    // (plan + throttle). Cada entrega pasa por las MISMAS reglas que la baja
    // individual: candado e informes emitidos la saltan, no la fuerzan.
    Route::middleware(['permission:receptions.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('receptions/bulk_delete', [ReceptionController::class, 'bulkDelete'])->name('receptions.bulk_delete');
    });

    /*
     * Dar de baja UNA MUESTRA: solo admin y super.
     *
     * No alcanza con el permiso `receptions.delete`. Una muestra dada de baja se
     * lleva sus resultados y QUEMA su correlativo —ese número no se reasigna
     * nunca—, así que no es una corrección de carga: es una decisión sobre el
     * registro del laboratorio. El permiso de módulo lo tiene quien recibe las
     * muestras, que es justamente quien no debería poder borrarlas.
     *
     * Y una muestra con informe EMITIDO no se borra ni siquiera acá: el cliente
     * tiene un papel que cita ese número (el candado está en el controlador y
     * alcanza también al super).
     */
    Route::middleware('role:super|admin')->group(function () {
        Route::delete('receptions/{reception}/samples/{sample}', [ReceptionController::class, 'destroySample'])->name('receptions.samples.destroy');
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
    /*
    |----------------------------------------------------------------------
    | Plantillas del análisis de resultados
    |----------------------------------------------------------------------
    | El párrafo que el informe imprime por familia de ensayo. Va acá y no en
    | un módulo de catálogo porque no es un dato de negocio: es la REDACCIÓN
    | que el laboratorio firma, y su edición es del super (el estándar) o del
    | admin del workspace (su personalización, por copia al escribir).
    |
    | `role:super|admin` y no un permiso de módulo: el mismo criterio que el
    | editor de reglas de diagnóstico, porque no se delega a un perfil.
    */
    Route::middleware('role:super|admin')->group(function () {
        Route::get('diagnosis_templates', [DiagnosisTemplateController::class, 'index'])->name('diagnosis_templates.index');
        Route::put('diagnosis_templates/{diagnosis_template}', [DiagnosisTemplateController::class, 'update'])->name('diagnosis_templates.update');
        Route::post('diagnosis_templates/{diagnosis_template}/restore', [DiagnosisTemplateController::class, 'restore'])->name('diagnosis_templates.restore');
    });

    /*
    |----------------------------------------------------------------------
    | Listas del informe
    |----------------------------------------------------------------------
    | Las cuatro listas chicas del formulario del informe —motivo del análisis,
    | punto de muestreo, marca de aceite, unidad de volumen— en una sola
    | pantalla con solapas. Ver `ReportCatalogController`.
    |
    | Un permiso de módulo y no `role:super|admin`: son datos del laboratorio,
    | no configuración del sistema, y quien los corrige es el mismo que carga
    | recepciones.
    */
    Route::middleware('permission:report_catalogs.view')->group(function () {
        Route::get('report_catalogs', [ReportCatalogController::class, 'index'])->name('report_catalogs.index');
    });

    Route::middleware('permission:report_catalogs.create')->group(function () {
        Route::post('report_catalogs', [ReportCatalogController::class, 'store'])->name('report_catalogs.store');
    });

    Route::middleware('permission:report_catalogs.edit')->group(function () {
        Route::put('report_catalogs/{report_catalog}', [ReportCatalogController::class, 'update'])->name('report_catalogs.update');
    });

    Route::middleware('permission:report_catalogs.delete')->group(function () {
        Route::delete('report_catalogs/{report_catalog}', [ReportCatalogController::class, 'destroy'])->name('report_catalogs.destroy');
    });
});
