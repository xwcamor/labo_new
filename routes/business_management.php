<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusinessManagement\EntryAuthorizerController;
use App\Http\Controllers\BusinessManagement\SignatureController;
use App\Http\Controllers\BusinessManagement\SamplerController;
use App\Http\Controllers\BusinessManagement\InstrumentController;
use App\Http\Controllers\BusinessManagement\EquipmentController;
use App\Http\Controllers\BusinessManagement\AnalyteController;
use App\Http\Controllers\BusinessManagement\BrandController;
use App\Http\Controllers\BusinessManagement\TapChangerTechnologyController;
use App\Http\Controllers\BusinessManagement\TapChangerModelController;
use App\Http\Controllers\BusinessManagement\TapChangerBrandController;
use App\Http\Controllers\BusinessManagement\LaboratoryController;
use App\Http\Controllers\BusinessManagement\TapChangerTypeController;
use App\Http\Controllers\BusinessManagement\EquipmentTypeController;
use App\Http\Controllers\BusinessManagement\ReportShareController;
use App\Http\Controllers\BusinessManagement\ReportShareLogController;
use App\Http\Controllers\BusinessManagement\OilTypeController;
use App\Http\Controllers\BusinessManagement\CustomerController;
use App\Http\Controllers\BusinessManagement\CustomerHierarchyController;
use App\Http\Controllers\BusinessManagement\CommentController;

/*
|--------------------------------------------------------------------------
| Business Management
|--------------------------------------------------------------------------
| Modulos de negocio del SaaS (no del core). Cada modulo se gobierna por
| permisos Spatie: customers.view, customers.create, etc. El admin del
| workspace asigna esos permisos a roles desde el modulo de Perfiles.
|
| Customers es el primer modulo real del SaaS, generado con make:module.
|
| ORDEN DE RUTAS CRITICO: las rutas con paths estaticos (customers/create,
| customers/trash, customers/export_*) DEBEN ir ANTES que customers/{customer}.
| Sin esto, Laravel hace route model binding con customer='create' y 404.
*/

Route::prefix('business_management')->name('business_management.')->group(function () {

    // ── Customers ──

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('customers/trash',                  [CustomerController::class, 'trash'])->name('customers.trash');
        Route::post('customers/bulk_restore',          [CustomerController::class, 'bulkRestore'])->name('customers.bulk_restore');
        Route::post('customers/{slug}/restore',        [CustomerController::class, 'restore'])->name('customers.restore');
        Route::get('customers/{slug}/restore',         fn () => redirect()->route('business_management.customers.trash'));
        Route::delete('customers/{slug}/force_delete', [CustomerController::class, 'forceDelete'])->name('customers.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:customers.view')->group(function () {
        Route::middleware(['throttle:5,1', 'permission:customers.export', 'plan_feature:export_excel'])
            ->post('customers/export_excel', [CustomerController::class, 'exportExcel'])->name('customers.export_excel');
        Route::middleware(['throttle:5,1', 'permission:customers.export', 'plan_feature:export_pdf'])
            ->post('customers/export_pdf',   [CustomerController::class, 'exportPdf'])->name('customers.export_pdf');
        Route::middleware(['throttle:5,1', 'permission:customers.export', 'plan_feature:export_word'])
            ->post('customers/export_word',  [CustomerController::class, 'exportWord'])->name('customers.export_word');
        Route::middleware(['throttle:5,1', 'permission:customers.export']) // export_csv libre en todos los planes
            ->post('customers/export_csv',   [CustomerController::class, 'exportCsv'])->name('customers.export_csv');
    });

    // 3) Imports (gated por plan_feature:bulk_operations)
    Route::middleware(['permission:customers.create', 'permission:customers.import', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('customers/import',          [CustomerController::class, 'import'])->name('customers.import');
        Route::get('customers/import_template',  [CustomerController::class, 'importTemplate'])->name('customers.import_template');
    });

    // 4) Bulk operations (gated por plan_feature:bulk_operations)
    Route::middleware(['permission:customers.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('customers/bulk_delete',     [CustomerController::class, 'bulkDelete'])->name('customers.bulk_delete');
        Route::post('customers/bulk_set_active', [CustomerController::class, 'bulkSetActive'])->name('customers.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window) — gated por permiso de delete.
    Route::middleware('permission:customers.delete')->group(function () {
        Route::post('customers/undo_last_delete', [CustomerController::class, 'undoLastDelete'])->name('customers.undo_last_delete');
    });

    // Edit All — batch edit de name + is_active (gated por permiso de edit).
    Route::middleware('permission:customers.edit')->group(function () {
        Route::get('customers/edit_all',         [CustomerController::class, 'editAll'])->name('customers.edit_all');
        Route::post('customers/edit_all/update', [CustomerController::class, 'editAllUpdate'])->name('customers.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO (create), despues los con {customer}.
    Route::middleware('permission:customers.create')->group(function () {
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers',       [CustomerController::class, 'store'])->name('customers.store');
        // Alta rápida JSON desde otros módulos (ej. select de cliente en el form de trafos).
        Route::post('customers/quick_store', [CustomerController::class, 'quickStore'])->name('customers.quick_store');
        Route::post('customers/{customer}/duplicate', [CustomerController::class, 'duplicate'])->name('customers.duplicate');
    });

    // Acciones con slug — DESPUES de los paths estaticos.
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('customers',            [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });
    Route::middleware('permission:customers.edit')->group(function () {
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}',      [CustomerController::class, 'update'])->name('customers.update');

        // Jerarquía del cliente (Ubicación/Área/Subestación) desde el árbol.
        Route::post('customers/{customer}/hierarchy', [CustomerHierarchyController::class, 'store'])->name('customers.hierarchy.store');
        Route::put('customers/{customer}/hierarchy/{level}/{id}', [CustomerHierarchyController::class, 'update'])->name('customers.hierarchy.update');
        Route::delete('customers/{customer}/hierarchy/{level}/{id}', [CustomerHierarchyController::class, 'destroy'])->name('customers.hierarchy.destroy');
        Route::post('customers/{customer}/hierarchy/{level}/{id}/restore', [CustomerHierarchyController::class, 'restore'])->name('customers.hierarchy.restore');
    });
    Route::middleware('permission:customers.delete')->group(function () {
        Route::get('customers/{customer}/delete',        [CustomerController::class, 'delete'])->name('customers.delete');
        Route::delete('customers/{customer}/deleteSave', [CustomerController::class, 'deleteSave'])->name('customers.deleteSave');
    });

    // Bloquear/desbloquear registro (Lockable) — solo super|admin. El nivel del
    // candado y quién puede sacarlo se resuelve en el controller (HandlesRecordLocking).
    Route::middleware('role:super|admin')->group(function () {
        Route::post('customers/{customer}/lock',   [CustomerController::class, 'lock'])->name('customers.lock');
        Route::post('customers/{customer}/unlock', [CustomerController::class, 'unlock'])->name('customers.unlock');
    });


    // ── OilTypes ── (catálogo interno del motor: SOLO super)
    // Todo el módulo va dentro de un grupo role:super: el admin del workspace no
    // lo ve en el sidebar ni puede navegar por URL directa. Las reglas del motor
    // viven en datos, no se ajustan por tenant.
    Route::middleware('role:super')->group(function () {

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('oil_types/trash',                  [OilTypeController::class, 'trash'])->name('oil_types.trash');
        Route::post('oil_types/bulk_restore',          [OilTypeController::class, 'bulkRestore'])->name('oil_types.bulk_restore');
        Route::post('oil_types/{slug}/restore',        [OilTypeController::class, 'restore'])->name('oil_types.restore');
        Route::get('oil_types/{slug}/restore',         fn () => redirect()->route('business_management.oil_types.trash'));
        Route::delete('oil_types/{slug}/force_delete', [OilTypeController::class, 'forceDelete'])->name('oil_types.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:oil_types.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('oil_types/export_excel', [OilTypeController::class, 'exportExcel'])->name('oil_types.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('oil_types/export_pdf',   [OilTypeController::class, 'exportPdf'])->name('oil_types.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('oil_types/export_word',  [OilTypeController::class, 'exportWord'])->name('oil_types.export_word');
        Route::middleware('throttle:5,1')
            ->post('oil_types/export_csv',   [OilTypeController::class, 'exportCsv'])->name('oil_types.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:oil_types.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('oil_types/import',          [OilTypeController::class, 'import'])->name('oil_types.import');
        Route::get('oil_types/import_template',  [OilTypeController::class, 'importTemplate'])->name('oil_types.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:oil_types.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('oil_types/bulk_delete',     [OilTypeController::class, 'bulkDelete'])->name('oil_types.bulk_delete');
        Route::post('oil_types/bulk_set_active', [OilTypeController::class, 'bulkSetActive'])->name('oil_types.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:oil_types.delete')->group(function () {
        Route::post('oil_types/undo_last_delete', [OilTypeController::class, 'undoLastDelete'])->name('oil_types.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:oil_types.edit')->group(function () {
        Route::get('oil_types/edit_all',         [OilTypeController::class, 'editAll'])->name('oil_types.edit_all');
        Route::post('oil_types/edit_all/update', [OilTypeController::class, 'editAllUpdate'])->name('oil_types.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:oil_types.create')->group(function () {
        Route::get('oil_types/create', [OilTypeController::class, 'create'])->name('oil_types.create');
        Route::post('oil_types',       [OilTypeController::class, 'store'])->name('oil_types.store');
        Route::post('oil_types/{oilType}/duplicate', [OilTypeController::class, 'duplicate'])->name('oil_types.duplicate');
    });

    Route::middleware('permission:oil_types.view')->group(function () {
        Route::get('oil_types',                [OilTypeController::class, 'index'])->name('oil_types.index');
        Route::get('oil_types/{oilType}',  [OilTypeController::class, 'show'])->name('oil_types.show');
    });
    Route::middleware('permission:oil_types.edit')->group(function () {
        Route::get('oil_types/{oilType}/edit', [OilTypeController::class, 'edit'])->name('oil_types.edit');
        Route::put('oil_types/{oilType}',      [OilTypeController::class, 'update'])->name('oil_types.update');
    });
    Route::middleware('permission:oil_types.delete')->group(function () {
        Route::get('oil_types/{oilType}/delete',        [OilTypeController::class, 'delete'])->name('oil_types.delete');
        Route::delete('oil_types/{oilType}/deleteSave', [OilTypeController::class, 'deleteSave'])->name('oil_types.deleteSave');
    });
    }); // fin OilTypes (role:super)


    // ── Equipment + Samples ──
    // Fase 1 (equipos) y fase 3 (muestras). El bloque de Transformers de
    // TrafoDex se eliminó completo: su modelo y su controlador eran del
    // dominio de diagnóstico, no del laboratorio.

    // ── Comentarios (polimórfico: transformer + muestras de cada prueba) ──
    // Texto libre del usuario, con autor + fecha. Ver/crear/borrar se gatean por
    // permiso (comments.*) para que el admin decida qué perfiles comentan.
    Route::middleware('permission:comments.view')->group(function () {
        // POST (no GET) para esquivar el redirect de localización en peticiones GET.
        Route::post('comments/list', [CommentController::class, 'index'])->name('comments.index');
    });
    // Crear: la "Nota del diagnosticador" (commentable = transformer) exige
    // diagnosis_notes.create; los comentarios POR MUESTRA exigen comments.create.
    // El middleware deja pasar a quien tenga CUALQUIERA de los dos; el controller
    // (store) hace valer el permiso correcto según el tipo de objeto comentado.
    Route::middleware('permission:comments.create|diagnosis_notes.create')->group(function () {
        Route::post('comments', [CommentController::class, 'store'])->name('comments.store');
    });
    Route::middleware('permission:comments.delete')->group(function () {
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    });


    // ── Brands ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('brands/trash',                  [BrandController::class, 'trash'])->name('brands.trash');
        Route::post('brands/bulk_restore',          [BrandController::class, 'bulkRestore'])->name('brands.bulk_restore');
        Route::post('brands/{slug}/restore',        [BrandController::class, 'restore'])->name('brands.restore');
        Route::get('brands/{slug}/restore',         fn () => redirect()->route('business_management.brands.trash'));
        Route::delete('brands/{slug}/force_delete', [BrandController::class, 'forceDelete'])->name('brands.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:brands.view')->group(function () {
        Route::middleware(['throttle:5,1', 'permission:brands.export', 'plan_feature:export_excel'])
            ->post('brands/export_excel', [BrandController::class, 'exportExcel'])->name('brands.export_excel');
        Route::middleware(['throttle:5,1', 'permission:brands.export', 'plan_feature:export_pdf'])
            ->post('brands/export_pdf',   [BrandController::class, 'exportPdf'])->name('brands.export_pdf');
        Route::middleware(['throttle:5,1', 'permission:brands.export', 'plan_feature:export_word'])
            ->post('brands/export_word',  [BrandController::class, 'exportWord'])->name('brands.export_word');
        Route::middleware(['throttle:5,1', 'permission:brands.export'])
            ->post('brands/export_csv',   [BrandController::class, 'exportCsv'])->name('brands.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:brands.create', 'permission:brands.import', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('brands/import',          [BrandController::class, 'import'])->name('brands.import');
        Route::get('brands/import_template',  [BrandController::class, 'importTemplate'])->name('brands.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:brands.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('brands/bulk_delete',     [BrandController::class, 'bulkDelete'])->name('brands.bulk_delete');
        Route::post('brands/bulk_set_active', [BrandController::class, 'bulkSetActive'])->name('brands.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:brands.delete')->group(function () {
        Route::post('brands/undo_last_delete', [BrandController::class, 'undoLastDelete'])->name('brands.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:brands.edit')->group(function () {
        Route::get('brands/edit_all',         [BrandController::class, 'editAll'])->name('brands.edit_all');
        Route::post('brands/edit_all/update', [BrandController::class, 'editAllUpdate'])->name('brands.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:brands.create')->group(function () {
        Route::get('brands/create', [BrandController::class, 'create'])->name('brands.create');
        Route::post('brands',       [BrandController::class, 'store'])->name('brands.store');
        // Alta rápida JSON desde otros módulos (ej. select de marca en el form de trafos).
        Route::post('brands/quick_store', [BrandController::class, 'quickStore'])->name('brands.quick_store');
        Route::post('brands/{brand}/duplicate', [BrandController::class, 'duplicate'])->name('brands.duplicate');
    });

    Route::middleware('permission:brands.view')->group(function () {
        Route::get('brands',                [BrandController::class, 'index'])->name('brands.index');
        Route::get('brands/{brand}',  [BrandController::class, 'show'])->name('brands.show');
    });
    Route::middleware('permission:brands.edit')->group(function () {
        Route::get('brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
        Route::put('brands/{brand}',      [BrandController::class, 'update'])->name('brands.update');
    });
    Route::middleware('permission:brands.delete')->group(function () {
        Route::get('brands/{brand}/delete',        [BrandController::class, 'delete'])->name('brands.delete');
        Route::delete('brands/{brand}/deleteSave', [BrandController::class, 'deleteSave'])->name('brands.deleteSave');
    });
    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('brands/{brand}/lock',   [BrandController::class, 'lock'])->name('brands.lock');
        Route::post('brands/{brand}/unlock', [BrandController::class, 'unlock'])->name('brands.unlock');
    });
    // ── Laboratories ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('laboratories/trash',                  [LaboratoryController::class, 'trash'])->name('laboratories.trash');
        Route::post('laboratories/bulk_restore',          [LaboratoryController::class, 'bulkRestore'])->name('laboratories.bulk_restore');
        Route::post('laboratories/{slug}/restore',        [LaboratoryController::class, 'restore'])->name('laboratories.restore');
        Route::get('laboratories/{slug}/restore',         fn () => redirect()->route('business_management.laboratories.trash'));
        Route::delete('laboratories/{slug}/force_delete', [LaboratoryController::class, 'forceDelete'])->name('laboratories.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:laboratories.view')->group(function () {
        Route::middleware(['throttle:5,1', 'permission:laboratories.export', 'plan_feature:export_excel'])
            ->post('laboratories/export_excel', [LaboratoryController::class, 'exportExcel'])->name('laboratories.export_excel');
        Route::middleware(['throttle:5,1', 'permission:laboratories.export', 'plan_feature:export_pdf'])
            ->post('laboratories/export_pdf',   [LaboratoryController::class, 'exportPdf'])->name('laboratories.export_pdf');
        Route::middleware(['throttle:5,1', 'permission:laboratories.export', 'plan_feature:export_word'])
            ->post('laboratories/export_word',  [LaboratoryController::class, 'exportWord'])->name('laboratories.export_word');
        Route::middleware(['throttle:5,1', 'permission:laboratories.export'])
            ->post('laboratories/export_csv',   [LaboratoryController::class, 'exportCsv'])->name('laboratories.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:laboratories.create', 'permission:laboratories.import', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('laboratories/import',          [LaboratoryController::class, 'import'])->name('laboratories.import');
        Route::get('laboratories/import_template',  [LaboratoryController::class, 'importTemplate'])->name('laboratories.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:laboratories.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('laboratories/bulk_delete',     [LaboratoryController::class, 'bulkDelete'])->name('laboratories.bulk_delete');
        Route::post('laboratories/bulk_set_active', [LaboratoryController::class, 'bulkSetActive'])->name('laboratories.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:laboratories.delete')->group(function () {
        Route::post('laboratories/undo_last_delete', [LaboratoryController::class, 'undoLastDelete'])->name('laboratories.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:laboratories.edit')->group(function () {
        Route::get('laboratories/edit_all',         [LaboratoryController::class, 'editAll'])->name('laboratories.edit_all');
        Route::post('laboratories/edit_all/update', [LaboratoryController::class, 'editAllUpdate'])->name('laboratories.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:laboratories.create')->group(function () {
        Route::get('laboratories/create', [LaboratoryController::class, 'create'])->name('laboratories.create');
        Route::post('laboratories',       [LaboratoryController::class, 'store'])->name('laboratories.store');
        // Alta rápida JSON desde otros módulos (ej. alta rapida de laboratorio desde el form de trafos).
        Route::post('laboratories/quick_store', [LaboratoryController::class, 'quickStore'])->name('laboratories.quick_store');
        Route::post('laboratories/{laboratory}/duplicate', [LaboratoryController::class, 'duplicate'])->name('laboratories.duplicate');
    });

    Route::middleware('permission:laboratories.view')->group(function () {
        Route::get('laboratories',                [LaboratoryController::class, 'index'])->name('laboratories.index');
        Route::get('laboratories/{laboratory}',  [LaboratoryController::class, 'show'])->name('laboratories.show');
    });
    Route::middleware('permission:laboratories.edit')->group(function () {
        Route::get('laboratories/{laboratory}/edit', [LaboratoryController::class, 'edit'])->name('laboratories.edit');
        Route::put('laboratories/{laboratory}',      [LaboratoryController::class, 'update'])->name('laboratories.update');
    });
    Route::middleware('permission:laboratories.delete')->group(function () {
        Route::get('laboratories/{laboratory}/delete',        [LaboratoryController::class, 'delete'])->name('laboratories.delete');
        Route::delete('laboratories/{laboratory}/deleteSave', [LaboratoryController::class, 'deleteSave'])->name('laboratories.deleteSave');
    });
    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('laboratories/{laboratory}/lock',   [LaboratoryController::class, 'lock'])->name('laboratories.lock');
        Route::post('laboratories/{laboratory}/unlock', [LaboratoryController::class, 'unlock'])->name('laboratories.unlock');
    });
    // ── TapChangerBrands ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('tap_changer_brands/trash',                  [TapChangerBrandController::class, 'trash'])->name('tap_changer_brands.trash');
        Route::post('tap_changer_brands/bulk_restore',          [TapChangerBrandController::class, 'bulkRestore'])->name('tap_changer_brands.bulk_restore');
        Route::post('tap_changer_brands/{slug}/restore',        [TapChangerBrandController::class, 'restore'])->name('tap_changer_brands.restore');
        Route::get('tap_changer_brands/{slug}/restore',         fn () => redirect()->route('business_management.tap_changer_brands.trash'));
        Route::delete('tap_changer_brands/{slug}/force_delete', [TapChangerBrandController::class, 'forceDelete'])->name('tap_changer_brands.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:tap_changer_brands.view')->group(function () {
        Route::middleware(['throttle:5,1', 'permission:tap_changer_brands.export', 'plan_feature:export_excel'])
            ->post('tap_changer_brands/export_excel', [TapChangerBrandController::class, 'exportExcel'])->name('tap_changer_brands.export_excel');
        Route::middleware(['throttle:5,1', 'permission:tap_changer_brands.export', 'plan_feature:export_pdf'])
            ->post('tap_changer_brands/export_pdf',   [TapChangerBrandController::class, 'exportPdf'])->name('tap_changer_brands.export_pdf');
        Route::middleware(['throttle:5,1', 'permission:tap_changer_brands.export', 'plan_feature:export_word'])
            ->post('tap_changer_brands/export_word',  [TapChangerBrandController::class, 'exportWord'])->name('tap_changer_brands.export_word');
        Route::middleware(['throttle:5,1', 'permission:tap_changer_brands.export'])
            ->post('tap_changer_brands/export_csv',   [TapChangerBrandController::class, 'exportCsv'])->name('tap_changer_brands.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:tap_changer_brands.create', 'permission:tap_changer_brands.import', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('tap_changer_brands/import',          [TapChangerBrandController::class, 'import'])->name('tap_changer_brands.import');
        Route::get('tap_changer_brands/import_template',  [TapChangerBrandController::class, 'importTemplate'])->name('tap_changer_brands.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:tap_changer_brands.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('tap_changer_brands/bulk_delete',     [TapChangerBrandController::class, 'bulkDelete'])->name('tap_changer_brands.bulk_delete');
        Route::post('tap_changer_brands/bulk_set_active', [TapChangerBrandController::class, 'bulkSetActive'])->name('tap_changer_brands.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:tap_changer_brands.delete')->group(function () {
        Route::post('tap_changer_brands/undo_last_delete', [TapChangerBrandController::class, 'undoLastDelete'])->name('tap_changer_brands.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:tap_changer_brands.edit')->group(function () {
        Route::get('tap_changer_brands/edit_all',         [TapChangerBrandController::class, 'editAll'])->name('tap_changer_brands.edit_all');
        Route::post('tap_changer_brands/edit_all/update', [TapChangerBrandController::class, 'editAllUpdate'])->name('tap_changer_brands.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:tap_changer_brands.create')->group(function () {
        Route::get('tap_changer_brands/create', [TapChangerBrandController::class, 'create'])->name('tap_changer_brands.create');
        Route::post('tap_changer_brands',       [TapChangerBrandController::class, 'store'])->name('tap_changer_brands.store');
        // Alta rápida JSON desde otros módulos (ej. alta rapida de tap_changer_brand desde el form de trafos).
        Route::post('tap_changer_brands/quick_store', [TapChangerBrandController::class, 'quickStore'])->name('tap_changer_brands.quick_store');
        Route::post('tap_changer_brands/{tap_changer_brand}/duplicate', [TapChangerBrandController::class, 'duplicate'])->name('tap_changer_brands.duplicate');
    });

    Route::middleware('permission:tap_changer_brands.view')->group(function () {
        Route::get('tap_changer_brands',                [TapChangerBrandController::class, 'index'])->name('tap_changer_brands.index');
        Route::get('tap_changer_brands/{tap_changer_brand}',  [TapChangerBrandController::class, 'show'])->name('tap_changer_brands.show');
    });
    Route::middleware('permission:tap_changer_brands.edit')->group(function () {
        Route::get('tap_changer_brands/{tap_changer_brand}/edit', [TapChangerBrandController::class, 'edit'])->name('tap_changer_brands.edit');
        Route::put('tap_changer_brands/{tap_changer_brand}',      [TapChangerBrandController::class, 'update'])->name('tap_changer_brands.update');
    });
    Route::middleware('permission:tap_changer_brands.delete')->group(function () {
        Route::get('tap_changer_brands/{tap_changer_brand}/delete',        [TapChangerBrandController::class, 'delete'])->name('tap_changer_brands.delete');
        Route::delete('tap_changer_brands/{tap_changer_brand}/deleteSave', [TapChangerBrandController::class, 'deleteSave'])->name('tap_changer_brands.deleteSave');
    });
    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('tap_changer_brands/{tap_changer_brand}/lock',   [TapChangerBrandController::class, 'lock'])->name('tap_changer_brands.lock');
        Route::post('tap_changer_brands/{tap_changer_brand}/unlock', [TapChangerBrandController::class, 'unlock'])->name('tap_changer_brands.unlock');
    });
    // ── TapChangerModels ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('tap_changer_models/trash',                  [TapChangerModelController::class, 'trash'])->name('tap_changer_models.trash');
        Route::post('tap_changer_models/bulk_restore',          [TapChangerModelController::class, 'bulkRestore'])->name('tap_changer_models.bulk_restore');
        Route::post('tap_changer_models/{slug}/restore',        [TapChangerModelController::class, 'restore'])->name('tap_changer_models.restore');
        Route::get('tap_changer_models/{slug}/restore',         fn () => redirect()->route('business_management.tap_changer_models.trash'));
        Route::delete('tap_changer_models/{slug}/force_delete', [TapChangerModelController::class, 'forceDelete'])->name('tap_changer_models.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:tap_changer_models.view')->group(function () {
        Route::middleware(['throttle:5,1', 'permission:tap_changer_models.export', 'plan_feature:export_excel'])
            ->post('tap_changer_models/export_excel', [TapChangerModelController::class, 'exportExcel'])->name('tap_changer_models.export_excel');
        Route::middleware(['throttle:5,1', 'permission:tap_changer_models.export', 'plan_feature:export_pdf'])
            ->post('tap_changer_models/export_pdf',   [TapChangerModelController::class, 'exportPdf'])->name('tap_changer_models.export_pdf');
        Route::middleware(['throttle:5,1', 'permission:tap_changer_models.export', 'plan_feature:export_word'])
            ->post('tap_changer_models/export_word',  [TapChangerModelController::class, 'exportWord'])->name('tap_changer_models.export_word');
        Route::middleware(['throttle:5,1', 'permission:tap_changer_models.export'])
            ->post('tap_changer_models/export_csv',   [TapChangerModelController::class, 'exportCsv'])->name('tap_changer_models.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:tap_changer_models.create', 'permission:tap_changer_models.import', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('tap_changer_models/import',          [TapChangerModelController::class, 'import'])->name('tap_changer_models.import');
        Route::get('tap_changer_models/import_template',  [TapChangerModelController::class, 'importTemplate'])->name('tap_changer_models.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:tap_changer_models.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('tap_changer_models/bulk_delete',     [TapChangerModelController::class, 'bulkDelete'])->name('tap_changer_models.bulk_delete');
        Route::post('tap_changer_models/bulk_set_active', [TapChangerModelController::class, 'bulkSetActive'])->name('tap_changer_models.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:tap_changer_models.delete')->group(function () {
        Route::post('tap_changer_models/undo_last_delete', [TapChangerModelController::class, 'undoLastDelete'])->name('tap_changer_models.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:tap_changer_models.edit')->group(function () {
        Route::get('tap_changer_models/edit_all',         [TapChangerModelController::class, 'editAll'])->name('tap_changer_models.edit_all');
        Route::post('tap_changer_models/edit_all/update', [TapChangerModelController::class, 'editAllUpdate'])->name('tap_changer_models.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:tap_changer_models.create')->group(function () {
        Route::get('tap_changer_models/create', [TapChangerModelController::class, 'create'])->name('tap_changer_models.create');
        Route::post('tap_changer_models',       [TapChangerModelController::class, 'store'])->name('tap_changer_models.store');
        // Alta rápida JSON desde otros módulos (ej. alta rapida de tap_changer_model desde el form de trafos).
        Route::post('tap_changer_models/quick_store', [TapChangerModelController::class, 'quickStore'])->name('tap_changer_models.quick_store');
        Route::post('tap_changer_models/{tap_changer_model}/duplicate', [TapChangerModelController::class, 'duplicate'])->name('tap_changer_models.duplicate');
    });

    Route::middleware('permission:tap_changer_models.view')->group(function () {
        Route::get('tap_changer_models',                [TapChangerModelController::class, 'index'])->name('tap_changer_models.index');
        Route::get('tap_changer_models/{tap_changer_model}',  [TapChangerModelController::class, 'show'])->name('tap_changer_models.show');
    });
    Route::middleware('permission:tap_changer_models.edit')->group(function () {
        Route::get('tap_changer_models/{tap_changer_model}/edit', [TapChangerModelController::class, 'edit'])->name('tap_changer_models.edit');
        Route::put('tap_changer_models/{tap_changer_model}',      [TapChangerModelController::class, 'update'])->name('tap_changer_models.update');
    });
    Route::middleware('permission:tap_changer_models.delete')->group(function () {
        Route::get('tap_changer_models/{tap_changer_model}/delete',        [TapChangerModelController::class, 'delete'])->name('tap_changer_models.delete');
        Route::delete('tap_changer_models/{tap_changer_model}/deleteSave', [TapChangerModelController::class, 'deleteSave'])->name('tap_changer_models.deleteSave');
    });
    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('tap_changer_models/{tap_changer_model}/lock',   [TapChangerModelController::class, 'lock'])->name('tap_changer_models.lock');
        Route::post('tap_changer_models/{tap_changer_model}/unlock', [TapChangerModelController::class, 'unlock'])->name('tap_changer_models.unlock');
    });
    // ── TapChangerTechnologies ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('tap_changer_technologies/trash',                  [TapChangerTechnologyController::class, 'trash'])->name('tap_changer_technologies.trash');
        Route::post('tap_changer_technologies/bulk_restore',          [TapChangerTechnologyController::class, 'bulkRestore'])->name('tap_changer_technologies.bulk_restore');
        Route::post('tap_changer_technologies/{slug}/restore',        [TapChangerTechnologyController::class, 'restore'])->name('tap_changer_technologies.restore');
        Route::get('tap_changer_technologies/{slug}/restore',         fn () => redirect()->route('business_management.tap_changer_technologies.trash'));
        Route::delete('tap_changer_technologies/{slug}/force_delete', [TapChangerTechnologyController::class, 'forceDelete'])->name('tap_changer_technologies.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:tap_changer_technologies.view')->group(function () {
        Route::middleware(['throttle:5,1', 'permission:tap_changer_technologies.export', 'plan_feature:export_excel'])
            ->post('tap_changer_technologies/export_excel', [TapChangerTechnologyController::class, 'exportExcel'])->name('tap_changer_technologies.export_excel');
        Route::middleware(['throttle:5,1', 'permission:tap_changer_technologies.export', 'plan_feature:export_pdf'])
            ->post('tap_changer_technologies/export_pdf',   [TapChangerTechnologyController::class, 'exportPdf'])->name('tap_changer_technologies.export_pdf');
        Route::middleware(['throttle:5,1', 'permission:tap_changer_technologies.export', 'plan_feature:export_word'])
            ->post('tap_changer_technologies/export_word',  [TapChangerTechnologyController::class, 'exportWord'])->name('tap_changer_technologies.export_word');
        Route::middleware(['throttle:5,1', 'permission:tap_changer_technologies.export'])
            ->post('tap_changer_technologies/export_csv',   [TapChangerTechnologyController::class, 'exportCsv'])->name('tap_changer_technologies.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:tap_changer_technologies.create', 'permission:tap_changer_technologies.import', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('tap_changer_technologies/import',          [TapChangerTechnologyController::class, 'import'])->name('tap_changer_technologies.import');
        Route::get('tap_changer_technologies/import_template',  [TapChangerTechnologyController::class, 'importTemplate'])->name('tap_changer_technologies.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:tap_changer_technologies.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('tap_changer_technologies/bulk_delete',     [TapChangerTechnologyController::class, 'bulkDelete'])->name('tap_changer_technologies.bulk_delete');
        Route::post('tap_changer_technologies/bulk_set_active', [TapChangerTechnologyController::class, 'bulkSetActive'])->name('tap_changer_technologies.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:tap_changer_technologies.delete')->group(function () {
        Route::post('tap_changer_technologies/undo_last_delete', [TapChangerTechnologyController::class, 'undoLastDelete'])->name('tap_changer_technologies.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:tap_changer_technologies.edit')->group(function () {
        Route::get('tap_changer_technologies/edit_all',         [TapChangerTechnologyController::class, 'editAll'])->name('tap_changer_technologies.edit_all');
        Route::post('tap_changer_technologies/edit_all/update', [TapChangerTechnologyController::class, 'editAllUpdate'])->name('tap_changer_technologies.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:tap_changer_technologies.create')->group(function () {
        Route::get('tap_changer_technologies/create', [TapChangerTechnologyController::class, 'create'])->name('tap_changer_technologies.create');
        Route::post('tap_changer_technologies',       [TapChangerTechnologyController::class, 'store'])->name('tap_changer_technologies.store');
        // Alta rápida JSON desde otros módulos (ej. alta rapida de tap_changer_technology desde el form de trafos).
        Route::post('tap_changer_technologies/quick_store', [TapChangerTechnologyController::class, 'quickStore'])->name('tap_changer_technologies.quick_store');
        Route::post('tap_changer_technologies/{tap_changer_technology}/duplicate', [TapChangerTechnologyController::class, 'duplicate'])->name('tap_changer_technologies.duplicate');
    });

    Route::middleware('permission:tap_changer_technologies.view')->group(function () {
        Route::get('tap_changer_technologies',                [TapChangerTechnologyController::class, 'index'])->name('tap_changer_technologies.index');
        Route::get('tap_changer_technologies/{tap_changer_technology}',  [TapChangerTechnologyController::class, 'show'])->name('tap_changer_technologies.show');
    });
    Route::middleware('permission:tap_changer_technologies.edit')->group(function () {
        Route::get('tap_changer_technologies/{tap_changer_technology}/edit', [TapChangerTechnologyController::class, 'edit'])->name('tap_changer_technologies.edit');
        Route::put('tap_changer_technologies/{tap_changer_technology}',      [TapChangerTechnologyController::class, 'update'])->name('tap_changer_technologies.update');
    });
    Route::middleware('permission:tap_changer_technologies.delete')->group(function () {
        Route::get('tap_changer_technologies/{tap_changer_technology}/delete',        [TapChangerTechnologyController::class, 'delete'])->name('tap_changer_technologies.delete');
        Route::delete('tap_changer_technologies/{tap_changer_technology}/deleteSave', [TapChangerTechnologyController::class, 'deleteSave'])->name('tap_changer_technologies.deleteSave');
    });
    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('tap_changer_technologies/{tap_changer_technology}/lock',   [TapChangerTechnologyController::class, 'lock'])->name('tap_changer_technologies.lock');
        Route::post('tap_changer_technologies/{tap_changer_technology}/unlock', [TapChangerTechnologyController::class, 'unlock'])->name('tap_changer_technologies.unlock');
    });
    // ── TapChangerTypes ── (conmutador: catálogo interno del motor, SOLO super)
    // Todo el módulo va dentro de un grupo role:super: el admin del workspace no
    // lo ve en el sidebar ni puede navegar por URL directa.
    Route::middleware('role:super')->group(function () {

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('tap_changer_types/trash',                  [TapChangerTypeController::class, 'trash'])->name('tap_changer_types.trash');
        Route::post('tap_changer_types/bulk_restore',          [TapChangerTypeController::class, 'bulkRestore'])->name('tap_changer_types.bulk_restore');
        Route::post('tap_changer_types/{slug}/restore',        [TapChangerTypeController::class, 'restore'])->name('tap_changer_types.restore');
        Route::get('tap_changer_types/{slug}/restore',         fn () => redirect()->route('business_management.tap_changer_types.trash'));
        Route::delete('tap_changer_types/{slug}/force_delete', [TapChangerTypeController::class, 'forceDelete'])->name('tap_changer_types.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:tap_changer_types.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('tap_changer_types/export_excel', [TapChangerTypeController::class, 'exportExcel'])->name('tap_changer_types.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('tap_changer_types/export_pdf',   [TapChangerTypeController::class, 'exportPdf'])->name('tap_changer_types.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('tap_changer_types/export_word',  [TapChangerTypeController::class, 'exportWord'])->name('tap_changer_types.export_word');
        Route::middleware('throttle:5,1')
            ->post('tap_changer_types/export_csv',   [TapChangerTypeController::class, 'exportCsv'])->name('tap_changer_types.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:tap_changer_types.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('tap_changer_types/import',          [TapChangerTypeController::class, 'import'])->name('tap_changer_types.import');
        Route::get('tap_changer_types/import_template',  [TapChangerTypeController::class, 'importTemplate'])->name('tap_changer_types.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:tap_changer_types.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('tap_changer_types/bulk_delete',     [TapChangerTypeController::class, 'bulkDelete'])->name('tap_changer_types.bulk_delete');
        Route::post('tap_changer_types/bulk_set_active', [TapChangerTypeController::class, 'bulkSetActive'])->name('tap_changer_types.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:tap_changer_types.delete')->group(function () {
        Route::post('tap_changer_types/undo_last_delete', [TapChangerTypeController::class, 'undoLastDelete'])->name('tap_changer_types.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:tap_changer_types.edit')->group(function () {
        Route::get('tap_changer_types/edit_all',         [TapChangerTypeController::class, 'editAll'])->name('tap_changer_types.edit_all');
        Route::post('tap_changer_types/edit_all/update', [TapChangerTypeController::class, 'editAllUpdate'])->name('tap_changer_types.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:tap_changer_types.create')->group(function () {
        Route::get('tap_changer_types/create', [TapChangerTypeController::class, 'create'])->name('tap_changer_types.create');
        Route::post('tap_changer_types',       [TapChangerTypeController::class, 'store'])->name('tap_changer_types.store');
        Route::post('tap_changer_types/{tapChangerType}/duplicate', [TapChangerTypeController::class, 'duplicate'])->name('tap_changer_types.duplicate');
    });

    Route::middleware('permission:tap_changer_types.view')->group(function () {
        Route::get('tap_changer_types',                [TapChangerTypeController::class, 'index'])->name('tap_changer_types.index');
        Route::get('tap_changer_types/{tapChangerType}',  [TapChangerTypeController::class, 'show'])->name('tap_changer_types.show');
    });
    Route::middleware('permission:tap_changer_types.edit')->group(function () {
        Route::get('tap_changer_types/{tapChangerType}/edit', [TapChangerTypeController::class, 'edit'])->name('tap_changer_types.edit');
        Route::put('tap_changer_types/{tapChangerType}',      [TapChangerTypeController::class, 'update'])->name('tap_changer_types.update');
    });
    Route::middleware('permission:tap_changer_types.delete')->group(function () {
        Route::get('tap_changer_types/{tapChangerType}/delete',        [TapChangerTypeController::class, 'delete'])->name('tap_changer_types.delete');
        Route::delete('tap_changer_types/{tapChangerType}/deleteSave', [TapChangerTypeController::class, 'deleteSave'])->name('tap_changer_types.deleteSave');
    });
    }); // fin TapChangerTypes (role:super)

    // ── EquipmentTypes ── (tipo de trafo: catálogo interno del motor, SOLO super)
    // Todo el módulo va dentro de un grupo role:super: el admin del workspace no
    // lo ve en el sidebar ni puede navegar por URL directa.
    Route::middleware('role:super')->group(function () {

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('equipment_types/trash',                  [EquipmentTypeController::class, 'trash'])->name('equipment_types.trash');
        Route::post('equipment_types/bulk_restore',          [EquipmentTypeController::class, 'bulkRestore'])->name('equipment_types.bulk_restore');
        Route::post('equipment_types/{slug}/restore',        [EquipmentTypeController::class, 'restore'])->name('equipment_types.restore');
        Route::get('equipment_types/{slug}/restore',         fn () => redirect()->route('business_management.equipment_types.trash'));
        Route::delete('equipment_types/{slug}/force_delete', [EquipmentTypeController::class, 'forceDelete'])->name('equipment_types.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:equipment_types.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('equipment_types/export_excel', [EquipmentTypeController::class, 'exportExcel'])->name('equipment_types.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('equipment_types/export_pdf',   [EquipmentTypeController::class, 'exportPdf'])->name('equipment_types.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('equipment_types/export_word',  [EquipmentTypeController::class, 'exportWord'])->name('equipment_types.export_word');
        Route::middleware('throttle:5,1')
            ->post('equipment_types/export_csv',   [EquipmentTypeController::class, 'exportCsv'])->name('equipment_types.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:equipment_types.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('equipment_types/import',          [EquipmentTypeController::class, 'import'])->name('equipment_types.import');
        Route::get('equipment_types/import_template',  [EquipmentTypeController::class, 'importTemplate'])->name('equipment_types.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:equipment_types.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('equipment_types/bulk_delete',     [EquipmentTypeController::class, 'bulkDelete'])->name('equipment_types.bulk_delete');
        Route::post('equipment_types/bulk_set_active', [EquipmentTypeController::class, 'bulkSetActive'])->name('equipment_types.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:equipment_types.delete')->group(function () {
        Route::post('equipment_types/undo_last_delete', [EquipmentTypeController::class, 'undoLastDelete'])->name('equipment_types.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:equipment_types.edit')->group(function () {
        Route::get('equipment_types/edit_all',         [EquipmentTypeController::class, 'editAll'])->name('equipment_types.edit_all');
        Route::post('equipment_types/edit_all/update', [EquipmentTypeController::class, 'editAllUpdate'])->name('equipment_types.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:equipment_types.create')->group(function () {
        Route::get('equipment_types/create', [EquipmentTypeController::class, 'create'])->name('equipment_types.create');
        Route::post('equipment_types',       [EquipmentTypeController::class, 'store'])->name('equipment_types.store');
        Route::post('equipment_types/{equipmentType}/duplicate', [EquipmentTypeController::class, 'duplicate'])->name('equipment_types.duplicate');
    });

    Route::middleware('permission:equipment_types.view')->group(function () {
        Route::get('equipment_types',                [EquipmentTypeController::class, 'index'])->name('equipment_types.index');
        Route::get('equipment_types/{equipmentType}',  [EquipmentTypeController::class, 'show'])->name('equipment_types.show');
    });
    Route::middleware('permission:equipment_types.edit')->group(function () {
        Route::get('equipment_types/{equipmentType}/edit', [EquipmentTypeController::class, 'edit'])->name('equipment_types.edit');
        Route::put('equipment_types/{equipmentType}',      [EquipmentTypeController::class, 'update'])->name('equipment_types.update');
    });
    Route::middleware('permission:equipment_types.delete')->group(function () {
        Route::get('equipment_types/{equipmentType}/delete',        [EquipmentTypeController::class, 'delete'])->name('equipment_types.delete');
        Route::delete('equipment_types/{equipmentType}/deleteSave', [EquipmentTypeController::class, 'deleteSave'])->name('equipment_types.deleteSave');
    });
    }); // fin EquipmentTypes (role:super)


    // ── Analytes ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('analytes/trash',                  [AnalyteController::class, 'trash'])->name('analytes.trash');
        Route::post('analytes/bulk_restore',          [AnalyteController::class, 'bulkRestore'])->name('analytes.bulk_restore');
        Route::post('analytes/{slug}/restore',        [AnalyteController::class, 'restore'])->name('analytes.restore');
        Route::get('analytes/{slug}/restore',         fn () => redirect()->route('business_management.analytes.trash'));
        Route::delete('analytes/{slug}/force_delete', [AnalyteController::class, 'forceDelete'])->name('analytes.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:analytes.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('analytes/export_excel', [AnalyteController::class, 'exportExcel'])->name('analytes.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('analytes/export_pdf',   [AnalyteController::class, 'exportPdf'])->name('analytes.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('analytes/export_word',  [AnalyteController::class, 'exportWord'])->name('analytes.export_word');
        Route::middleware('throttle:5,1')
            ->post('analytes/export_csv',   [AnalyteController::class, 'exportCsv'])->name('analytes.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:analytes.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('analytes/import',          [AnalyteController::class, 'import'])->name('analytes.import');
        Route::get('analytes/import_template',  [AnalyteController::class, 'importTemplate'])->name('analytes.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:analytes.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('analytes/bulk_delete',     [AnalyteController::class, 'bulkDelete'])->name('analytes.bulk_delete');
        Route::post('analytes/bulk_set_active', [AnalyteController::class, 'bulkSetActive'])->name('analytes.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:analytes.delete')->group(function () {
        Route::post('analytes/undo_last_delete', [AnalyteController::class, 'undoLastDelete'])->name('analytes.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:analytes.edit')->group(function () {
        Route::get('analytes/edit_all',         [AnalyteController::class, 'editAll'])->name('analytes.edit_all');
        Route::post('analytes/edit_all/update', [AnalyteController::class, 'editAllUpdate'])->name('analytes.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:analytes.create')->group(function () {
        Route::get('analytes/create', [AnalyteController::class, 'create'])->name('analytes.create');
        Route::post('analytes',       [AnalyteController::class, 'store'])->name('analytes.store');
        Route::post('analytes/{analyte}/duplicate', [AnalyteController::class, 'duplicate'])->name('analytes.duplicate');
    });

    Route::middleware('permission:analytes.view')->group(function () {
        Route::get('analytes',                [AnalyteController::class, 'index'])->name('analytes.index');
        Route::get('analytes/{analyte}',  [AnalyteController::class, 'show'])->name('analytes.show');
    });
    Route::middleware('permission:analytes.edit')->group(function () {
        Route::get('analytes/{analyte}/edit', [AnalyteController::class, 'edit'])->name('analytes.edit');
        Route::put('analytes/{analyte}',      [AnalyteController::class, 'update'])->name('analytes.update');
    });
    Route::middleware('permission:analytes.delete')->group(function () {
        Route::get('analytes/{analyte}/delete',        [AnalyteController::class, 'delete'])->name('analytes.delete');
        Route::delete('analytes/{analyte}/deleteSave', [AnalyteController::class, 'deleteSave'])->name('analytes.deleteSave');
    });

    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('analytes/{analyte}/lock',   [AnalyteController::class, 'lock'])->name('analytes.lock');
        Route::post('analytes/{analyte}/unlock', [AnalyteController::class, 'unlock'])->name('analytes.unlock');
    });


    // ── Equipment ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('equipment/trash',                  [EquipmentController::class, 'trash'])->name('equipment.trash');
        Route::post('equipment/bulk_restore',          [EquipmentController::class, 'bulkRestore'])->name('equipment.bulk_restore');
        Route::post('equipment/{slug}/restore',        [EquipmentController::class, 'restore'])->name('equipment.restore');
        Route::get('equipment/{slug}/restore',         fn () => redirect()->route('business_management.equipment.trash'));
        Route::delete('equipment/{slug}/force_delete', [EquipmentController::class, 'forceDelete'])->name('equipment.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:equipment.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('equipment/export_excel', [EquipmentController::class, 'exportExcel'])->name('equipment.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('equipment/export_pdf',   [EquipmentController::class, 'exportPdf'])->name('equipment.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('equipment/export_word',  [EquipmentController::class, 'exportWord'])->name('equipment.export_word');
        Route::middleware('throttle:5,1')
            ->post('equipment/export_csv',   [EquipmentController::class, 'exportCsv'])->name('equipment.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:equipment.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('equipment/import',          [EquipmentController::class, 'import'])->name('equipment.import');
        Route::get('equipment/import_template',  [EquipmentController::class, 'importTemplate'])->name('equipment.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:equipment.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('equipment/bulk_delete',     [EquipmentController::class, 'bulkDelete'])->name('equipment.bulk_delete');
        Route::post('equipment/bulk_set_active', [EquipmentController::class, 'bulkSetActive'])->name('equipment.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:equipment.delete')->group(function () {
        Route::post('equipment/undo_last_delete', [EquipmentController::class, 'undoLastDelete'])->name('equipment.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:equipment.edit')->group(function () {
        Route::get('equipment/edit_all',         [EquipmentController::class, 'editAll'])->name('equipment.edit_all');
        Route::post('equipment/edit_all/update', [EquipmentController::class, 'editAllUpdate'])->name('equipment.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:equipment.create')->group(function () {
        Route::get('equipment/create', [EquipmentController::class, 'create'])->name('equipment.create');
        Route::post('equipment',       [EquipmentController::class, 'store'])->name('equipment.store');
        Route::post('equipment/{equipment}/duplicate', [EquipmentController::class, 'duplicate'])->name('equipment.duplicate');
    });

    Route::middleware('permission:equipment.view')->group(function () {
        Route::get('equipment',                [EquipmentController::class, 'index'])->name('equipment.index');
        // La jerarquía del cliente para los desplegables encadenados del
        // formulario. Va ANTES de `equipment/{equipment}` o el comodín se la
        // come. Devuelve JSON, no una página de Inertia.
        // `{customer:id}` explícito: Customer se resuelve por slug en el resto
        // del sistema, pero el desplegable del formulario maneja ids (es lo que
        // se guarda en `customer_id`). Sin el `:id` la ruta devolvía 404.
        Route::get('equipment/hierarchy/{customer:id}', [EquipmentController::class, 'hierarchy'])->name('equipment.hierarchy');
        Route::get('equipment/{equipment}',  [EquipmentController::class, 'show'])->name('equipment.show');
    });
    Route::middleware('permission:equipment.edit')->group(function () {
        Route::get('equipment/{equipment}/edit', [EquipmentController::class, 'edit'])->name('equipment.edit');
        Route::put('equipment/{equipment}',      [EquipmentController::class, 'update'])->name('equipment.update');
    });
    Route::middleware('permission:equipment.delete')->group(function () {
        Route::get('equipment/{equipment}/delete',        [EquipmentController::class, 'delete'])->name('equipment.delete');
        Route::delete('equipment/{equipment}/deleteSave', [EquipmentController::class, 'deleteSave'])->name('equipment.deleteSave');
    });

    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('equipment/{equipment}/lock',   [EquipmentController::class, 'lock'])->name('equipment.lock');
        Route::post('equipment/{equipment}/unlock', [EquipmentController::class, 'unlock'])->name('equipment.unlock');
    });


    // ── Instruments ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('instruments/trash',                  [InstrumentController::class, 'trash'])->name('instruments.trash');
        Route::post('instruments/bulk_restore',          [InstrumentController::class, 'bulkRestore'])->name('instruments.bulk_restore');
        Route::post('instruments/{slug}/restore',        [InstrumentController::class, 'restore'])->name('instruments.restore');
        Route::get('instruments/{slug}/restore',         fn () => redirect()->route('business_management.instruments.trash'));
        Route::delete('instruments/{slug}/force_delete', [InstrumentController::class, 'forceDelete'])->name('instruments.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:instruments.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('instruments/export_excel', [InstrumentController::class, 'exportExcel'])->name('instruments.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('instruments/export_pdf',   [InstrumentController::class, 'exportPdf'])->name('instruments.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('instruments/export_word',  [InstrumentController::class, 'exportWord'])->name('instruments.export_word');
        Route::middleware('throttle:5,1')
            ->post('instruments/export_csv',   [InstrumentController::class, 'exportCsv'])->name('instruments.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:instruments.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('instruments/import',          [InstrumentController::class, 'import'])->name('instruments.import');
        Route::get('instruments/import_template',  [InstrumentController::class, 'importTemplate'])->name('instruments.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:instruments.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('instruments/bulk_delete',     [InstrumentController::class, 'bulkDelete'])->name('instruments.bulk_delete');
        Route::post('instruments/bulk_set_active', [InstrumentController::class, 'bulkSetActive'])->name('instruments.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:instruments.delete')->group(function () {
        Route::post('instruments/undo_last_delete', [InstrumentController::class, 'undoLastDelete'])->name('instruments.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:instruments.edit')->group(function () {
        Route::get('instruments/edit_all',         [InstrumentController::class, 'editAll'])->name('instruments.edit_all');
        Route::post('instruments/edit_all/update', [InstrumentController::class, 'editAllUpdate'])->name('instruments.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:instruments.create')->group(function () {
        Route::get('instruments/create', [InstrumentController::class, 'create'])->name('instruments.create');
        Route::post('instruments',       [InstrumentController::class, 'store'])->name('instruments.store');
        Route::post('instruments/{instrument}/duplicate', [InstrumentController::class, 'duplicate'])->name('instruments.duplicate');
    });

    Route::middleware('permission:instruments.view')->group(function () {
        Route::get('instruments',                [InstrumentController::class, 'index'])->name('instruments.index');
        Route::get('instruments/{instrument}',  [InstrumentController::class, 'show'])->name('instruments.show');
    });
    Route::middleware('permission:instruments.edit')->group(function () {
        Route::get('instruments/{instrument}/edit', [InstrumentController::class, 'edit'])->name('instruments.edit');
        Route::put('instruments/{instrument}',      [InstrumentController::class, 'update'])->name('instruments.update');
    });
    Route::middleware('permission:instruments.delete')->group(function () {
        Route::get('instruments/{instrument}/delete',        [InstrumentController::class, 'delete'])->name('instruments.delete');
        Route::delete('instruments/{instrument}/deleteSave', [InstrumentController::class, 'deleteSave'])->name('instruments.deleteSave');
    });

    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('instruments/{instrument}/lock',   [InstrumentController::class, 'lock'])->name('instruments.lock');
        Route::post('instruments/{instrument}/unlock', [InstrumentController::class, 'unlock'])->name('instruments.unlock');
    });


    // ── Samplers ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('samplers/trash',                  [SamplerController::class, 'trash'])->name('samplers.trash');
        Route::post('samplers/bulk_restore',          [SamplerController::class, 'bulkRestore'])->name('samplers.bulk_restore');
        Route::post('samplers/{slug}/restore',        [SamplerController::class, 'restore'])->name('samplers.restore');
        Route::get('samplers/{slug}/restore',         fn () => redirect()->route('business_management.samplers.trash'));
        Route::delete('samplers/{slug}/force_delete', [SamplerController::class, 'forceDelete'])->name('samplers.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:samplers.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('samplers/export_excel', [SamplerController::class, 'exportExcel'])->name('samplers.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('samplers/export_pdf',   [SamplerController::class, 'exportPdf'])->name('samplers.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('samplers/export_word',  [SamplerController::class, 'exportWord'])->name('samplers.export_word');
        Route::middleware('throttle:5,1')
            ->post('samplers/export_csv',   [SamplerController::class, 'exportCsv'])->name('samplers.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:samplers.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('samplers/import',          [SamplerController::class, 'import'])->name('samplers.import');
        Route::get('samplers/import_template',  [SamplerController::class, 'importTemplate'])->name('samplers.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:samplers.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('samplers/bulk_delete',     [SamplerController::class, 'bulkDelete'])->name('samplers.bulk_delete');
        Route::post('samplers/bulk_set_active', [SamplerController::class, 'bulkSetActive'])->name('samplers.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:samplers.delete')->group(function () {
        Route::post('samplers/undo_last_delete', [SamplerController::class, 'undoLastDelete'])->name('samplers.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:samplers.edit')->group(function () {
        Route::get('samplers/edit_all',         [SamplerController::class, 'editAll'])->name('samplers.edit_all');
        Route::post('samplers/edit_all/update', [SamplerController::class, 'editAllUpdate'])->name('samplers.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:samplers.create')->group(function () {
        Route::get('samplers/create', [SamplerController::class, 'create'])->name('samplers.create');
        Route::post('samplers',       [SamplerController::class, 'store'])->name('samplers.store');
        Route::post('samplers/{sampler}/duplicate', [SamplerController::class, 'duplicate'])->name('samplers.duplicate');
    });

    Route::middleware('permission:samplers.view')->group(function () {
        Route::get('samplers',                [SamplerController::class, 'index'])->name('samplers.index');
        Route::get('samplers/{sampler}',  [SamplerController::class, 'show'])->name('samplers.show');
    });
    Route::middleware('permission:samplers.edit')->group(function () {
        Route::get('samplers/{sampler}/edit', [SamplerController::class, 'edit'])->name('samplers.edit');
        Route::put('samplers/{sampler}',      [SamplerController::class, 'update'])->name('samplers.update');
    });
    Route::middleware('permission:samplers.delete')->group(function () {
        Route::get('samplers/{sampler}/delete',        [SamplerController::class, 'delete'])->name('samplers.delete');
        Route::delete('samplers/{sampler}/deleteSave', [SamplerController::class, 'deleteSave'])->name('samplers.deleteSave');
    });

    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('samplers/{sampler}/lock',   [SamplerController::class, 'lock'])->name('samplers.lock');
        Route::post('samplers/{sampler}/unlock', [SamplerController::class, 'unlock'])->name('samplers.unlock');
    });


    // ── Signatures ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('signatures/trash',                  [SignatureController::class, 'trash'])->name('signatures.trash');
        Route::post('signatures/bulk_restore',          [SignatureController::class, 'bulkRestore'])->name('signatures.bulk_restore');
        Route::post('signatures/{slug}/restore',        [SignatureController::class, 'restore'])->name('signatures.restore');
        Route::get('signatures/{slug}/restore',         fn () => redirect()->route('business_management.signatures.trash'));
        Route::delete('signatures/{slug}/force_delete', [SignatureController::class, 'forceDelete'])->name('signatures.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:signatures.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('signatures/export_excel', [SignatureController::class, 'exportExcel'])->name('signatures.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('signatures/export_pdf',   [SignatureController::class, 'exportPdf'])->name('signatures.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('signatures/export_word',  [SignatureController::class, 'exportWord'])->name('signatures.export_word');
        Route::middleware('throttle:5,1')
            ->post('signatures/export_csv',   [SignatureController::class, 'exportCsv'])->name('signatures.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:signatures.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('signatures/import',          [SignatureController::class, 'import'])->name('signatures.import');
        Route::get('signatures/import_template',  [SignatureController::class, 'importTemplate'])->name('signatures.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:signatures.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('signatures/bulk_delete',     [SignatureController::class, 'bulkDelete'])->name('signatures.bulk_delete');
        Route::post('signatures/bulk_set_active', [SignatureController::class, 'bulkSetActive'])->name('signatures.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:signatures.delete')->group(function () {
        Route::post('signatures/undo_last_delete', [SignatureController::class, 'undoLastDelete'])->name('signatures.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:signatures.edit')->group(function () {
        Route::get('signatures/edit_all',         [SignatureController::class, 'editAll'])->name('signatures.edit_all');
        Route::post('signatures/edit_all/update', [SignatureController::class, 'editAllUpdate'])->name('signatures.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:signatures.create')->group(function () {
        Route::get('signatures/create', [SignatureController::class, 'create'])->name('signatures.create');
        Route::post('signatures',       [SignatureController::class, 'store'])->name('signatures.store');
        Route::post('signatures/{signature}/duplicate', [SignatureController::class, 'duplicate'])->name('signatures.duplicate');
    });

    Route::middleware('permission:signatures.view')->group(function () {
        Route::get('signatures',                [SignatureController::class, 'index'])->name('signatures.index');
        Route::get('signatures/{signature}',  [SignatureController::class, 'show'])->name('signatures.show');
    });
    Route::middleware('permission:signatures.edit')->group(function () {
        Route::get('signatures/{signature}/edit', [SignatureController::class, 'edit'])->name('signatures.edit');
        Route::put('signatures/{signature}',      [SignatureController::class, 'update'])->name('signatures.update');
    });
    Route::middleware('permission:signatures.delete')->group(function () {
        Route::get('signatures/{signature}/delete',        [SignatureController::class, 'delete'])->name('signatures.delete');
        Route::delete('signatures/{signature}/deleteSave', [SignatureController::class, 'deleteSave'])->name('signatures.deleteSave');
    });

    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('signatures/{signature}/lock',   [SignatureController::class, 'lock'])->name('signatures.lock');
        Route::post('signatures/{signature}/unlock', [SignatureController::class, 'unlock'])->name('signatures.unlock');
    });


    // ── EntryAuthorizers ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('entry_authorizers/trash',                  [EntryAuthorizerController::class, 'trash'])->name('entry_authorizers.trash');
        Route::post('entry_authorizers/bulk_restore',          [EntryAuthorizerController::class, 'bulkRestore'])->name('entry_authorizers.bulk_restore');
        Route::post('entry_authorizers/{slug}/restore',        [EntryAuthorizerController::class, 'restore'])->name('entry_authorizers.restore');
        Route::get('entry_authorizers/{slug}/restore',         fn () => redirect()->route('business_management.entry_authorizers.trash'));
        Route::delete('entry_authorizers/{slug}/force_delete', [EntryAuthorizerController::class, 'forceDelete'])->name('entry_authorizers.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:entry_authorizers.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('entry_authorizers/export_excel', [EntryAuthorizerController::class, 'exportExcel'])->name('entry_authorizers.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('entry_authorizers/export_pdf',   [EntryAuthorizerController::class, 'exportPdf'])->name('entry_authorizers.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('entry_authorizers/export_word',  [EntryAuthorizerController::class, 'exportWord'])->name('entry_authorizers.export_word');
        Route::middleware('throttle:5,1')
            ->post('entry_authorizers/export_csv',   [EntryAuthorizerController::class, 'exportCsv'])->name('entry_authorizers.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:entry_authorizers.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('entry_authorizers/import',          [EntryAuthorizerController::class, 'import'])->name('entry_authorizers.import');
        Route::get('entry_authorizers/import_template',  [EntryAuthorizerController::class, 'importTemplate'])->name('entry_authorizers.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:entry_authorizers.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('entry_authorizers/bulk_delete',     [EntryAuthorizerController::class, 'bulkDelete'])->name('entry_authorizers.bulk_delete');
        Route::post('entry_authorizers/bulk_set_active', [EntryAuthorizerController::class, 'bulkSetActive'])->name('entry_authorizers.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:entry_authorizers.delete')->group(function () {
        Route::post('entry_authorizers/undo_last_delete', [EntryAuthorizerController::class, 'undoLastDelete'])->name('entry_authorizers.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:entry_authorizers.edit')->group(function () {
        Route::get('entry_authorizers/edit_all',         [EntryAuthorizerController::class, 'editAll'])->name('entry_authorizers.edit_all');
        Route::post('entry_authorizers/edit_all/update', [EntryAuthorizerController::class, 'editAllUpdate'])->name('entry_authorizers.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:entry_authorizers.create')->group(function () {
        Route::get('entry_authorizers/create', [EntryAuthorizerController::class, 'create'])->name('entry_authorizers.create');
        Route::post('entry_authorizers',       [EntryAuthorizerController::class, 'store'])->name('entry_authorizers.store');
        Route::post('entry_authorizers/{entryAuthorizer}/duplicate', [EntryAuthorizerController::class, 'duplicate'])->name('entry_authorizers.duplicate');
    });

    Route::middleware('permission:entry_authorizers.view')->group(function () {
        Route::get('entry_authorizers',                [EntryAuthorizerController::class, 'index'])->name('entry_authorizers.index');
        Route::get('entry_authorizers/{entryAuthorizer}',  [EntryAuthorizerController::class, 'show'])->name('entry_authorizers.show');
    });
    Route::middleware('permission:entry_authorizers.edit')->group(function () {
        Route::get('entry_authorizers/{entryAuthorizer}/edit', [EntryAuthorizerController::class, 'edit'])->name('entry_authorizers.edit');
        Route::put('entry_authorizers/{entryAuthorizer}',      [EntryAuthorizerController::class, 'update'])->name('entry_authorizers.update');
    });
    Route::middleware('permission:entry_authorizers.delete')->group(function () {
        Route::get('entry_authorizers/{entryAuthorizer}/delete',        [EntryAuthorizerController::class, 'delete'])->name('entry_authorizers.delete');
        Route::delete('entry_authorizers/{entryAuthorizer}/deleteSave', [EntryAuthorizerController::class, 'deleteSave'])->name('entry_authorizers.deleteSave');
    });

    // Bloquear/desbloquear (Lockable) — solo super|admin.
    Route::middleware('role:super|admin')->group(function () {
        Route::post('entry_authorizers/{entryAuthorizer}/lock',   [EntryAuthorizerController::class, 'lock'])->name('entry_authorizers.lock');
        Route::post('entry_authorizers/{entryAuthorizer}/unlock', [EntryAuthorizerController::class, 'unlock'])->name('entry_authorizers.unlock');
    });
});
