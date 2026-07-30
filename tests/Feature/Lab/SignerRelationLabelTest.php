<?php

namespace Tests\Feature\Lab;

use App\Support\SignerRelation;
use Tests\TestCase;

/**
 * La etiqueta con la que firma un firmante: UNA lista, no dos.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL DEFECTO QUE ESTO FIJA                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Había dos listas de etiquetas para las mismas seis relaciones y no coincidían:
 *
 *     approvals.relation.prepared = "Elaborado por"    ← lo que se ve en pantalla
 *     reports.relation.prepared   = "Realizado por"    ← lo que salía impreso
 *
 * El laboratorio elegía "Elaborado por" en el módulo de Firmas, lo veía elegido,
 * y los dos PDF seguían imprimiendo "REALIZADO POR". Nada en la pantalla
 * explicaba por qué, y "no funciona" era la conclusión correcta.
 *
 * Lo que se fija: que la etiqueta impresa salga de la MISMA lista que la de la
 * pantalla, y que las dos plantillas de informe la pidan por el helper en vez de
 * armar la clave de traducción a mano. Duplicar la lista de nuevo es fácil —basta
 * agregar una clave `relation` a los archivos de idioma de `reports`— y el
 * síntoma tarda en aparecer porque solo se nota al imprimir.
 */
class SignerRelationLabelTest extends TestCase
{
    public function test_las_seis_relaciones_tienen_etiqueta_en_los_dos_idiomas(): void
    {
        foreach (['es', 'en'] as $idioma) {
            $this->app->setLocale($idioma);

            foreach (SignerRelation::ALL as $relacion) {
                $etiqueta = SignerRelation::label($relacion);

                $this->assertNotSame('', $etiqueta);
                // Una línea de firma que dice "approvals.relation.prepared" es
                // peor que una que dice la relación de omisión.
                $this->assertStringNotContainsString('approvals.', $etiqueta);
            }
        }
    }

    public function test_una_relacion_desconocida_cae_en_la_de_omision(): void
    {
        // Puede llegar de un dato viejo o de una fila creada a mano. Lo que NO
        // puede es imprimirse la clave de traducción sobre la línea de firma.
        $this->assertSame(
            SignerRelation::label(SignerRelation::DEFAULT),
            SignerRelation::label('firmado_con_ganas'),
        );
        $this->assertSame(
            SignerRelation::label(SignerRelation::DEFAULT),
            SignerRelation::label(null),
        );
    }

    public function test_no_existe_una_segunda_lista_de_etiquetas(): void
    {
        // La lista vive en `approvals.relation`, que es la que se ve en pantalla.
        // Si alguien vuelve a agregar `reports.relation`, los PDF se desincronizan
        // de la pantalla sin que ningún otro test lo note.
        foreach (['es', 'en'] as $idioma) {
            $reports = require resource_path("lang/{$idioma}/reports.php");

            $this->assertArrayNotHasKey('relation', $reports);
            $this->assertArrayNotHasKey('reported_by', $reports);
        }
    }

    public function test_las_dos_plantillas_piden_la_etiqueta_por_el_helper(): void
    {
        // El informe CLÁSICO la arma en PHP...
        $renderer = file_get_contents(app_path('Services/Lab/LegacyReportRenderer.php'));
        $this->assertStringContainsString('SignerRelation::label(', $renderer);

        // ...y el MODERNO en el blade. Los dos tienen que decir lo mismo.
        $blade = file_get_contents(
            resource_path('views/lab_management/reports/test_report.blade.php'),
        );
        $this->assertStringContainsString('SignerRelation::label(', $blade);
    }

    public function test_el_desplegable_ofrece_las_seis_con_su_etiqueta(): void
    {
        $opciones = SignerRelation::options();

        $this->assertSame(SignerRelation::ALL, array_keys($opciones));
        $this->assertSame(
            SignerRelation::label('prepared'),
            $opciones['prepared'],
        );
    }
}
