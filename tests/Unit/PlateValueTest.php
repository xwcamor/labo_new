<?php

namespace Tests\Unit;

use App\Support\PlateValue;
use PHPUnit\Framework\TestCase;

/**
 * La placa escrita como la escribe la gente.
 *
 * El formulario guarda números separados —hace falta un número comparable para
 * la clase de tensión del IEEE C57.106— pero nadie tipea la placa en tres
 * casillas: la chapa dice "220/60/10" y la planilla que llega para importar trae
 * una sola columna. Lo que se fija acá es que esa cadena se convierta bien, y en
 * particular el caso de la coma, que puede ser decimal o separador.
 */
class PlateValueTest extends TestCase
{
    public function test_la_barra_separa_devanados(): void
    {
        $this->assertSame([220.0, 60.0, 10.0], PlateValue::parse('220/60/10'));
    }

    public function test_tolera_espacios_y_otros_separadores(): void
    {
        $this->assertSame([220.0, 60.0], PlateValue::parse('220 / 60'));
        $this->assertSame([220.0, 60.0], PlateValue::parse('220-60'));
        $this->assertSame([138.0, 13.8], PlateValue::parse('138 13.8'));
    }

    public function test_descarta_la_unidad_pegada(): void
    {
        // "220 kV / 60 kV" no puede producir segmentos vacíos ni tomar la
        // unidad como un número.
        $this->assertSame([220.0, 60.0], PlateValue::parse('220 kV / 60 kV'));
        $this->assertSame([30.0], PlateValue::parse('30MVA'));
    }

    public function test_la_coma_entre_digitos_es_decimal(): void
    {
        // Es el caso que importa: "13,8" es UNA tensión de 13.8 kV. Leerlo como
        // dos (13 y 8) manda la clase de tensión al tramo equivocado y con ella
        // el criterio de aceptación del ensayo.
        $this->assertSame([13.8], PlateValue::parse('13,8'));
        $this->assertSame([220.0, 13.8], PlateValue::parse('220/13,8'));
    }

    public function test_la_coma_con_espacio_separa(): void
    {
        // Con espacio detrás ya no es un decimal: son dos valores.
        $this->assertSame([220.0, 60.0], PlateValue::parse('220, 60'));
    }

    public function test_un_numero_solo_pasa_tal_cual(): void
    {
        $this->assertSame([220.0], PlateValue::parse(220));
        $this->assertSame([13.8], PlateValue::parse('13.8'));
    }

    public function test_lo_que_no_tiene_numeros_da_lista_vacia(): void
    {
        $this->assertSame([], PlateValue::parse(''));
        $this->assertSame([], PlateValue::parse('   '));
        $this->assertSame([], PlateValue::parse('sin datos'));
        $this->assertSame([], PlateValue::parse(null));
    }

    public function test_reparte_en_casillas_y_avisa_de_lo_que_sobra(): void
    {
        [$casillas, $sobra] = PlateValue::split('220/60/10', 3);

        $this->assertSame([220.0, 60.0, 10.0], $casillas);
        $this->assertSame([], $sobra);
    }

    public function test_las_casillas_que_faltan_quedan_nulas(): void
    {
        [$casillas, $sobra] = PlateValue::split('220/60', 3);

        $this->assertSame([220.0, 60.0, null], $casillas);
        $this->assertSame([], $sobra);
    }

    public function test_lo_que_no_entra_se_devuelve_en_vez_de_perderse(): void
    {
        // Una placa de cuatro devanados contra tres columnas: el cuarto NO se
        // descarta en silencio. Perder un dato sin avisar es el defecto que este
        // proyecto vino a corregir.
        [$casillas, $sobra] = PlateValue::split('500/220/60/10', 3);

        $this->assertSame([500.0, 220.0, 60.0], $casillas);
        $this->assertSame([10.0], $sobra);
    }

    public function test_la_etiqueta_se_arma_sin_ceros_de_relleno(): void
    {
        $this->assertSame('220 / 60 / 10', PlateValue::label([220, 60, 10]));
        $this->assertSame('138 / 13.8', PlateValue::label([138.0, 13.80]));
        // Los huecos no dejan barras sueltas.
        $this->assertSame('220', PlateValue::label([220, null, null]));
        $this->assertNull(PlateValue::label([null, null]));
    }

    // ─── Las reglas que salieron de medir los datos reales ───────────────

    public function test_el_cero_no_es_un_valor_de_placa(): void
    {
        // Es el hallazgo más importante de la auditoría: en el sistema anterior
        // "sin dato" se escribía `-` o `0`, el `.to_i` lo volvía 0 kV, y un 0
        // entra en el cuadro de "hasta 69 kV" — el criterio MÁS LAXO. No saber
        // la tensión hacía que el aceite se juzgara con la vara más blanda.
        $this->assertSame([], PlateValue::parse('0'));
        $this->assertSame([], PlateValue::parse('-'));
        $this->assertSame([220.0], PlateValue::parse('220/0'));
    }

    public function test_reconoce_la_barra_usada_como_division(): void
    {
        // Caso real de los reactores: `500/1.73` es 500 partido por raíz de
        // tres, no un devanado de 1.73 kV.
        $this->assertTrue(PlateValue::looksLikeDivision('500/1.73'));
        $this->assertTrue(PlateValue::looksLikeDivision('500 / 1.73'));

        // Y no confunde una placa legítima con eso.
        $this->assertFalse(PlateValue::looksLikeDivision('220/60/10'));
        $this->assertFalse(PlateValue::looksLikeDivision('220/13.8'));
        $this->assertFalse(PlateValue::looksLikeDivision('500'));
    }

    public function test_las_tensiones_se_ordenan_de_mayor_a_menor(): void
    {
        // Los tres campos son ROLES (alta / baja / terciario) y el de ALTA
        // decide la clase de tensión del cuadro de límites. Guardar "13.8/220"
        // tal cual dejaría 13.8 como alta tensión y el ensayo se juzgaría
        // contra el criterio de otra clase.
        $this->assertSame([220.0, 13.8, null], PlateValue::sortVoltages([13.8, 220.0, null]));
        $this->assertSame([500.0, 220.0, 60.0], PlateValue::sortVoltages([220.0, 60.0, 500.0]));
        // Lo que ya viene ordenado no se mueve.
        $this->assertSame([220.0, 60.0, 10.0], PlateValue::sortVoltages([220.0, 60.0, 10.0]));
    }
}
