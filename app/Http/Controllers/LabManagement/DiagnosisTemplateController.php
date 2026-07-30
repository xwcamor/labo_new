<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * El editor de las plantillas del análisis de resultados.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL TEXTO QUE FIRMA EL LABORATORIO NO PUEDE EXIGIR UN DESPLIEGUE          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El párrafo que el informe imprime por familia de ensayo es la redacción del
 * laboratorio y sale en el papel del cliente. En el sistema anterior cada frase
 * era un `if` en una vista ERB —cuatro variantes por familia, repetidas en tres
 * archivos— y cambiar una palabra era cambiar código. Después pasó a un JSON del
 * repositorio, que seguía necesitando un despliegue. Acá es una fila.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ COPIA AL ESCRIBIR                                                        │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El SUPER edita la plantilla de fábrica (la global): es el estándar que ven
 * todos los laboratorios. El ADMIN de un workspace que edita una global NO la
 * modifica: se le crea una copia propia con su cambio, y desde ese momento su
 * informe usa la suya. "Restaurar" borra esa copia y vuelve a la de fábrica.
 *
 * Es el mismo criterio que el editor de reglas de diagnóstico de TrafoDex, y por
 * la misma razón: un laboratorio no puede reescribirle la redacción a otro.
 */
class DiagnosisTemplateController extends Controller
{
    public function index(Request $request)
    {
        $esSuper  = $request->user()?->hasRole('super') ?? false;
        $tenantId = $request->user()?->tenant_id;

        $filas = DiagnosisTemplate::withoutGlobalScopes()
            ->where(fn ($q) => $q->whereNull('tenant_id')->when(
                $tenantId !== null,
                fn ($w) => $w->orWhere('tenant_id', $tenantId),
            ))
            ->orderBy('family')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Qué familias tienen una copia propia: la pantalla marca cada plantilla
        // como "de fábrica" o "personalizada por este laboratorio", porque no es
        // lo mismo editar el estándar que editar lo propio y hay que verlo antes
        // de tocar nada.
        $propias = $filas->whereNotNull('tenant_id')
            ->keyBy(fn ($f) => $f->family . '|' . $f->case . '|' . ($f->analyte ?? ''));

        // Cuando el laboratorio tiene su copia, la de fábrica no se lista: se
        // mostrarían dos plantillas para el mismo caso y no se sabría cuál manda.
        $visibles = $filas->reject(
            fn ($f) => $f->tenant_id === null
                && $propias->has($f->family . '|' . $f->case . '|' . ($f->analyte ?? ''))
        )->values();

        return Inertia::render('DiagnosisTemplates/Index', [
            'templates' => $visibles->map(fn ($f) => [
                'slug'       => $f->slug,
                'family'     => $f->family,
                'case'       => $f->case,
                'analyte'    => $f->analyte,
                'threshold'  => $f->threshold !== null ? (float) $f->threshold : null,
                'bands'      => $f->bands ?? [],
                'body'       => $f->body,
                'origin'     => $f->origin,
                'notes'      => $f->notes,
                'is_active'  => $f->is_active,
                // Lo que la pantalla necesita para decidir qué ofrece: quién es
                // la dueña de esta fila y si se puede restaurar.
                'is_factory'   => $f->tenant_id === null,
                'is_overridden'=> $f->tenant_id !== null,
            ]),
            // Las familias salen de las PLANTILLAS y no de `lab.report_families`:
            // esa configuración mapea código de grupo a familia (un solo par
            // hoy), mientras las plantillas cubren las quince familias que el
            // informe puede imprimir. Tomarlas de la configuración dejaría la
            // pantalla mostrando una sola.
            'families'  => $visibles->pluck('family')->unique()->sort()->values(),
            'cases'     => DiagnosisTemplate::CASES,
            'can'       => [
                // El super edita el estándar; el admin personaliza lo suyo. Los
                // dos pueden guardar: lo que cambia es SOBRE QUÉ FILA escriben.
                'edit'          => $esSuper || ($request->user()?->hasRole('admin') ?? false),
                'edits_factory' => $esSuper,
            ],
        ]);
    }

    /**
     * Guarda el texto de una plantilla.
     *
     * El super escribe sobre la fila que está mirando. El admin, si está mirando
     * una de fábrica, obtiene su propia copia (copia al escribir) y el estándar
     * queda intacto.
     */
    public function update(Request $request, DiagnosisTemplate $diagnosis_template): RedirectResponse
    {
        $datos = $request->validate([
            'body'   => ['nullable', 'string', 'max:4000'],
            'bands'  => ['nullable', 'array'],
            'bands.*.min'  => ['nullable', 'numeric'],
            'bands.*.max'  => ['nullable', 'numeric'],
            'bands.*.body' => ['required_with:bands', 'string', 'max:2000'],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ]);

        // Una plantilla sin texto y sin bandas no redacta nada: la familia
        // quedaría sin párrafo en el informe y nadie se enteraría hasta que el
        // cliente reciba el papel.
        if (blank($datos['body'] ?? null) && empty($datos['bands'])) {
            return back()->withErrors(['body' => __('diagnosis_templates.errors.empty')]);
        }

        $esSuper  = $request->user()?->hasRole('super') ?? false;
        $tenantId = $request->user()?->tenant_id;

        $destino = $diagnosis_template;

        if (! $esSuper && $diagnosis_template->tenant_id === null) {
            if ($tenantId === null) {
                return back()->withErrors(['body' => __('diagnosis_templates.errors.no_tenant')]);
            }

            // Copia al escribir: la de fábrica no se toca.
            $destino = DiagnosisTemplate::create(
                collect($diagnosis_template->only([
                    'family', 'case', 'oil_types', 'equipment_types',
                    'analyte', 'threshold', 'origin', 'sort_order',
                ]))->all() + [
                    'slug'       => Str::random(22),
                    'tenant_id'  => $tenantId,
                    'is_active'  => true,
                    'created_by' => $request->user()?->id,
                ]
            );
        } elseif (! $esSuper && $diagnosis_template->tenant_id !== $tenantId) {
            // La copia de otro laboratorio no se edita ni se ve.
            abort(403);
        }

        // `bands` puede no venir en la petición (una plantilla de un solo texto
        // no manda tramos): con `$datos['bands']` a secas esto reventaba con
        // "Undefined array key" y el guardado devolvía un 500.
        $destino->update([
            'body'       => $datos['body'] ?? null,
            'bands'      => ($datos['bands'] ?? []) ?: null,
            'notes'      => $datos['notes'] ?? $destino->notes,
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', __('diagnosis_templates.saved'));
    }

    /**
     * Vuelve a la redacción de fábrica: borra la copia del laboratorio.
     *
     * No hay "deshacer una edición del super": para eso está volver a correr el
     * seeder, que reescribe las globales desde el archivo del repositorio.
     */
    public function restore(Request $request, DiagnosisTemplate $diagnosis_template): RedirectResponse
    {
        if ($diagnosis_template->tenant_id === null) {
            return back()->withErrors(['body' => __('diagnosis_templates.errors.factory_restore')]);
        }

        $esSuper = $request->user()?->hasRole('super') ?? false;

        if (! $esSuper && $diagnosis_template->tenant_id !== $request->user()?->tenant_id) {
            abort(403);
        }

        $diagnosis_template->update([
            'deleted_by'          => $request->user()?->id,
            'deleted_description' => __('diagnosis_templates.restored_reason'),
        ]);
        $diagnosis_template->delete();

        return back()->with('success', __('diagnosis_templates.restored'));
    }
}
