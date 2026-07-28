-- Costo de dar de alta UNA hoja de trabajo completa en cada forma.
--
-- Se mide dentro del servidor, en un bucle, para aislar el costo de
-- almacenamiento (monton + indices + WAL) del costo de ida y vuelta con PHP.
-- El de ida y vuelta es identico en las dos formas para la vertical (una sola
-- sentencia) y peor para la ancha (una sentencia por prueba), asi que medirlo
-- adentro favorece a la forma ancha, no al reves.

create or replace function bench.insert_vertical(p_veces int, p_solo_cromas boolean default false)
returns double precision language plpgsql as $$
declare
  t0 timestamptz; sid bigint; base bigint;
begin
  select coalesce(max(sample_id), 0) + 1 into base from bench.samples;
  t0 := clock_timestamp();
  for i in 0 .. p_veces - 1 loop
    sid := base + i;
    insert into bench.samples values (sid, 1, 999000000 + i, date '2026-07-28', 'M-BENCH-' || i);
    insert into bench.results_v
      (tenant_id, equipment_id, analyte_id, test_definition_id, measured_at, value_num, qualifier, unit)
    select 1, 999000000 + i, a.id, a.test_definition_id, date '2026-07-28',
           round((a.base_value * 0.7)::numeric, 4), null, a.unit
    from bench.analytes a
    where not p_solo_cromas or a.test_definition_id = 10;
  end loop;
  return extract(epoch from clock_timestamp() - t0) * 1000.0 / p_veces;
end $$;

create or replace function bench.insert_ancho(p_veces int, p_solo_cromas boolean default false)
returns double precision language plpgsql as $$
declare
  t0 timestamptz; sid bigint; base bigint;
begin
  select coalesce(max(sample_id), 0) + 1 into base from bench.samples;
  t0 := clock_timestamp();
  for i in 0 .. p_veces - 1 loop
    sid := base + i;
    insert into bench.samples values (sid, 1, 999000000 + i, date '2026-07-28', 'M-BENCH-' || i);
    insert into bench.w_analisis_cromatografico
      (tenant_id, equipment_id, measured_at, no_de_muestra, norma,
       hidrogeno_h2_ppm, oxigeno_o2_ppm, nitrogeno_n2_ppm, metano_ch4_ppm, mcarbono_co_ppm,
       dcarbono_co2_ppm, etileno_c2h4_ppm, etano_c2h6_ppm, acetileno_c2h2_ppm,
       total_de_gases_combustibles, total)
    values (1, 999000000 + i, date '2026-07-28', 'M-BENCH-' || i, 1,
            42, 8400, 38500, 21, 280, 2100, 17.5, 14, 2.1, 350, 49000);
    if not p_solo_cromas then
      insert into bench.w_grado_de_polimerizacion
        (tenant_id, equipment_id, measured_at, no_de_muestra, norma, masa_g, contenido_de_agua_en,
         tiempo_muestra_s, constante_viscosimetro_muestra, tiempo_blanco, constante_viscosimetro_blanco,
         viscosidad_de_muestra_t, viscosidad_de_solventet0, concetracion_muestra_g100ml,
         viscosidad_especifica_ns, k_de_martin, viscosidad_intrinseca_n, grado_de_polimerizacion, promedio)
      values (1, 999000000 + i, date '2026-07-28', 'M-BENCH-' || i, 1,
              7, 280, 7, 280, 7, 280, 280, 280, 280, 280, 7, 280, 630, 7);
      insert into bench.w_numero_acido
        (tenant_id, equipment_id, measured_at, no_de_muestra, norma, bureta_pp_la_01c, balanza_pp_la_01c,
         factor_koh, vol_blanco, peso_aceite_g, volumen_gastado_ml, resultado_mgkohg_aceite)
      values (1, 999000000 + i, date '2026-07-28', 'M-BENCH-' || i, 1, 2, 3, 7, 7, 7, 7, 0.028);
      insert into bench.w_contenido_de_agua
        (tenant_id, equipment_id, measured_at, no_de_muestra, norma, balanza_pp_la_01c,
         r1_pp_la_01c, r1, r2_pp_la_01c, r2, repetibilidad, resultado_ppm)
      values (1, 999000000 + i, date '2026-07-28', 'M-BENCH-' || i, 1, 3, 2, 7, 2, 7, 7, 10.5);
    end if;
  end loop;
  return extract(epoch from clock_timestamp() - t0) * 1000.0 / p_veces;
end $$;

-- Deja el banco de pruebas como estaba: los equipos 999000000+ son sinteticos
-- de esta medicion y falsearian los conteos de las demas.
create or replace function bench.limpiar_insercion() returns void language plpgsql as $$
begin
  delete from bench.results_v                 where equipment_id >= 999000000;
  delete from bench.w_analisis_cromatografico where equipment_id >= 999000000;
  delete from bench.w_grado_de_polimerizacion where equipment_id >= 999000000;
  delete from bench.w_numero_acido            where equipment_id >= 999000000;
  delete from bench.w_contenido_de_agua       where equipment_id >= 999000000;
  delete from bench.samples                   where equipment_id >= 999000000;
end $$;
