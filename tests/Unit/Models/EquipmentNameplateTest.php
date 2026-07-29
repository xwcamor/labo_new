<?php

namespace Tests\Unit\Models;

use App\Models\Equipment;
use Tests\TestCase;

/**
 * La placa del equipo: "500 / 220 / 33 kV", "120 / 160 / 200 MVA".
 *
 * El sistema viejo guardaba esas dos placas como texto libre y sacaba el número
 * que necesitaba con `split('/').map(&:to_f).max`, repetido en cinco lugares.
 * Acá son columnas, la placa se arma en un solo método y el máximo —el número
 * que se le manda a TrafoDex, que tiene una sola tensión por equipo— sale del
 * mismo lugar.
 */
class EquipmentNameplateTest extends TestCase
{
    private function equipo(array $attrs): Equipment
    {
        return new Equipment($attrs);
    }

    public function test_nameplate_joins_the_three_windings(): void
    {
        $e = $this->equipo([
            'voltage_kv_hv' => 500,
            'voltage_kv_lv' => 220,
            'voltage_kv_tv' => 33,
        ]);

        $this->assertSame('500 / 220 / 33', $e->voltage_label);
    }

    public function test_nameplate_keeps_decimals_that_matter_and_drops_the_ones_that_do_not(): void
    {
        // 4.16 kV es una tensión real de distribución: el decimal no se pierde.
        // 500 kV se escribe 500 y no 500.00, que es como la lee el papel.
        $e = $this->equipo(['voltage_kv_hv' => 4.16, 'voltage_kv_lv' => 0.46]);

        $this->assertSame('4.16 / 0.46', $e->voltage_label);
    }

    public function test_a_two_winding_unit_has_no_trailing_separator(): void
    {
        $e = $this->equipo(['voltage_kv_hv' => 220, 'voltage_kv_lv' => 66]);

        $this->assertSame('220 / 66', $e->voltage_label);
        $this->assertNull($e->power_label);
    }

    public function test_power_nameplate_holds_the_cooling_stages(): void
    {
        $e = $this->equipo([
            'power_mva'   => 120,
            'power_mva_2' => 160,
            'power_mva_3' => 200,
        ]);

        $this->assertSame('120 / 160 / 200', $e->power_label);
    }

    /**
     * La banda de tensión elige el cuadro de límites contra el que se juzga el
     * resultado. Si el terciario quedara fuera del máximo, un 500/220/33 podría
     * caer en la banda equivocada.
     */
    public function test_voltage_class_is_the_highest_winding(): void
    {
        $e = $this->equipo([
            'voltage_kv_hv' => 33,
            'voltage_kv_lv' => 500,
            'voltage_kv_tv' => 220,
        ]);

        $this->assertSame(500.0, $e->voltage_class);
    }

    /**
     * Lo que viaja a TrafoDex, que tiene UNA potencia por equipo: el máximo.
     * Es el mismo criterio que el sistema viejo ya aplicaba al exportar.
     */
    public function test_power_rating_for_the_sync_is_the_highest_value(): void
    {
        $e = $this->equipo(['power_mva' => 120, 'power_mva_2' => 160, 'power_mva_3' => 200]);

        $this->assertSame(200.0, $e->power_rating);
    }

    public function test_an_equipment_without_plate_data_says_nothing_instead_of_zero(): void
    {
        $e = $this->equipo([]);

        $this->assertNull($e->voltage_label);
        $this->assertNull($e->power_label);
        $this->assertNull($e->voltage_class);
        $this->assertNull($e->power_rating);
    }
}
