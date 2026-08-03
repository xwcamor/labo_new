# Cotejo: el Excel del laboratorio contra lo sembrado

> Generado por `etl_valores_orientacion.py` — no editar a mano; corregir el
> script y regenerar. El Excel es la FUENTE PRIMARIA; lo sembrado salió del
> código Ruby. **Este cotejo no cambia ningún número**: es el informe con el
> que el laboratorio decide.

- Celdas con criterio en el Excel: **321** (de 559 leídas).
- Límites sembrados comparables encontrados en el Excel: **225**.

| Categoría | Cantidad | Qué significa |
|---|---|---|
| **Coinciden** | 199 | El código viejo implementó fiel el Excel: quedan confirmados. |
| **Difieren** | 26 | El Excel dice una cosa y lo sembrado (= el código viejo) otra. Decide el laboratorio cuál manda. |
| **Solo en el Excel** | 191 | Criterios que el sistema viejo nunca implementó (estados nuevo/antes de energizar/tratado, rigidez IEC 60156, hoja OTROS…). El laboratorio elige cuáles activar. |
| **Solo en el código** | 7 | Sembrado sin respaldo en el Excel (ej. los cuadros de Reactor). |

## 1. Difieren — A DECIDIR

| Cuadro | Analito | Excel | Sembrado | Celda |
|---|---|---|---|---|
| Horno · Mineral | c2h2 | ** | - | CR!K7 |
| Mineral · ≤69 kV | con | - | Brillante y Claro | FQ!I17 |
| Mineral · 69-230 kV | con | - | Brillante y Claro | FQ!J17 |
| Mineral · ≥230 kV | con | - | Brillante y Claro | FQ!K17 |
| Éster · ≤72.5 kV | pot@25 | 3 | - | FQ!AC7 |
| Éster · ≤72.5 kV | pot@90 | - | 0.15 - máximo | FQ!AC9 |
| Éster · ≤72.5 kV | ten | 10 | 22.0 - mínimo | FQ!AC14 |
| Éster · ≤72.5 kV | wat | 450 | 200.0 - máximo | FQ!AC15 |
| Éster · ≤72.5 kV | col | 1.5 | 2.0 - máximo | FQ!AC16 |
| Éster · ≤72.5 kV | con | - | Claro libre de sedimentos | FQ!AC17 |
| Éster · 72.5-170 kV | acid | 0.3 | 0.50 - máximo | FQ!AD6 |
| Éster · 72.5-170 kV | pot@25 | 3 | - | FQ!AD7 |
| Éster · 72.5-170 kV | pot@90 | - | 0.15 - máximo | FQ!AD9 |
| Éster · 72.5-170 kV | rig_d1816 | 47 | 50.0 - mínimo | FQ!AD11 |
| Éster · 72.5-170 kV | ten | 12 | 22.0 - mínimo | FQ!AD14 |
| Éster · 72.5-170 kV | wat | 350 | 150.0 - máximo | FQ!AD15 |
| Éster · 72.5-170 kV | col | 1.5 | 2.0 - máximo | FQ!AD16 |
| Éster · 72.5-170 kV | con | - | Claro libre de sedimentos | FQ!AD17 |
| Éster · ≥170 kV | acid | 0.3 | 0.15 - máximo | FQ!AE6 |
| Éster · ≥170 kV | pot@25 | 3 | - | FQ!AE7 |
| Éster · ≥170 kV | pot@90 | - | 0.15 - máximo | FQ!AE9 |
| Éster · ≥170 kV | rig_d1816 | 50 | 60.0 - mínimo | FQ!AE11 |
| Éster · ≥170 kV | ten | 14 | 22.0 - mínimo | FQ!AE14 |
| Éster · ≥170 kV | wat | 200 | 100.0 - máximo | FQ!AE15 |
| Éster · ≥170 kV | col | 1.5 | 2.0 - máximo | FQ!AE16 |
| Éster · ≥170 kV | con | - | Claro libre de sedimentos | FQ!AE17 |


## 2. Solo en el código — sin respaldo en el Excel

| Cuadro | Analito | Sembrado |
|---|---|---|
| Reactor · Mineral | h2 | 150 - máximo |
| Reactor · Mineral | ch4 | 130 - máximo |
| Reactor · Mineral | co | 600 - máximo |
| Reactor · Mineral | co2 | 14000 - máximo |
| Reactor · Mineral | c2h4 | 280 - máximo |
| Reactor · Mineral | c2h6 | 90 - máximo |
| Reactor · Mineral | c2h2 | 20 - máximo |


## 3. Solo en el Excel — nunca implementados (elegir cuáles activar)

| Celda | Fluido | Estado | Kv | Equipo | Analito | Criterio |
|---|---|---|---|---|---|---|
| FQ!E6 | mineral | nuevo | - | - | acid | 0.03 - máximo |
| FQ!E7 | mineral | nuevo | - | - | pot@25 | 0.05 |
| FQ!E10 | mineral | nuevo | - | - | pot@100 | 0.3 |
| FQ!E11 | mineral | nuevo | - | - | rig_d1816 | 35 |
| FQ!E13 | mineral | nuevo | - | - | rig_iec60156 | 30 -mínimo |
| FQ!E14 | mineral | nuevo | - | - | ten | 40 |
| FQ!E15 | mineral | nuevo | - | - | wat | 35 |
| FQ!E16 | mineral | nuevo | - | - | col | 0.5 |
| FQ!E17 | mineral | nuevo | - | - | con | Brillante y Claro |
| FQ!E18 | mineral | nuevo | - | - | den | 0.91 |
| FQ!F6 | mineral | antes_de_energizar | ≤69 | - | acid | 0.03 - máximo |
| FQ!F7 | mineral | antes_de_energizar | ≤69 | - | pot@25 | 0.05 |
| FQ!F9 | mineral | antes_de_energizar | ≤69 | - | pot@90 | 0.015 -máximo |
| FQ!F10 | mineral | antes_de_energizar | ≤69 | - | pot@100 | 0.4 |
| FQ!F11 | mineral | antes_de_energizar | ≤69 | - | rig_d1816 | 45 |
| FQ!F13 | mineral | antes_de_energizar | ≤69 | - | rig_iec60156 | 55 -mínimo |
| FQ!F14 | mineral | antes_de_energizar | ≤69 | - | ten | 38 |
| FQ!F15 | mineral | antes_de_energizar | ≤69 | - | wat | 20 |
| FQ!F16 | mineral | antes_de_energizar | ≤69 | - | col | 1 |
| FQ!F17 | mineral | antes_de_energizar | ≤69 | - | con | Brillante y Claro |
| FQ!G6 | mineral | antes_de_energizar | >69-<230 | - | acid | 0.03 - máximo |
| FQ!G7 | mineral | antes_de_energizar | >69-<230 | - | pot@25 | 0.05 |
| FQ!G9 | mineral | antes_de_energizar | >69-<230 | - | pot@90 | 0.015 -máximo |
| FQ!G10 | mineral | antes_de_energizar | >69-<230 | - | pot@100 | 0.4 |
| FQ!G11 | mineral | antes_de_energizar | >69-<230 | - | rig_d1816 | 55 |
| FQ!G13 | mineral | antes_de_energizar | >69-<230 | - | rig_iec60156 | 60 -mínimo |
| FQ!G14 | mineral | antes_de_energizar | >69-<230 | - | ten | 38 |
| FQ!G15 | mineral | antes_de_energizar | >69-<230 | - | wat | 10 |
| FQ!G16 | mineral | antes_de_energizar | >69-<230 | - | col | 1 |
| FQ!G17 | mineral | antes_de_energizar | >69-<230 | - | con | Brillante y Claro |
| FQ!H6 | mineral | antes_de_energizar | ≥230-<345 | - | acid | 0.03 - máximo |
| FQ!H7 | mineral | antes_de_energizar | ≥230-<345 | - | pot@25 | 0.05 |
| FQ!H9 | mineral | antes_de_energizar | ≥230-<345 | - | pot@90 | 0.010 -máximo |
| FQ!H10 | mineral | antes_de_energizar | ≥230-<345 | - | pot@100 | 0.3 |
| FQ!H11 | mineral | antes_de_energizar | ≥230-<345 | - | rig_d1816 | 60 |
| FQ!H13 | mineral | antes_de_energizar | ≥230-<345 | - | rig_iec60156 | 60 -mínimo |
| FQ!H14 | mineral | antes_de_energizar | ≥230-<345 | - | ten | 38 |
| FQ!H15 | mineral | antes_de_energizar | ≥230-<345 | - | wat | 10 |
| FQ!H16 | mineral | antes_de_energizar | ≥230-<345 | - | col | 0.5 |
| FQ!H17 | mineral | antes_de_energizar | ≥230-<345 | - | con | Brillante y Claro |
| FQ!L6 | mineral | tratado | ≤69 | - | acid | 0.05 - máximo |
| FQ!L11 | mineral | tratado | ≤69 | - | rig_d1816 | 45 |
| FQ!L14 | mineral | tratado | ≤69 | - | ten | 35 |
| FQ!L15 | mineral | tratado | ≤69 | - | wat | 35 |
| FQ!L16 | mineral | tratado | ≤69 | - | col | 1.5 |
| FQ!L17 | mineral | tratado | ≤69 | - | con | Brillante y Claro |
| FQ!M6 | mineral | tratado | >69-<230 | - | acid | 0.05 - máximo |
| FQ!M11 | mineral | tratado | >69-<230 | - | rig_d1816 | 55 |
| FQ!M14 | mineral | tratado | >69-<230 | - | ten | 35 |
| FQ!M15 | mineral | tratado | >69-<230 | - | wat | 20 |
| FQ!M16 | mineral | tratado | >69-<230 | - | col | 1.5 |
| FQ!M17 | mineral | tratado | >69-<230 | - | con | Brillante y Claro |
| FQ!N6 | mineral | tratado | ≥230 | - | acid | 0.05 - máximo |
| FQ!N11 | mineral | tratado | ≥230 | - | rig_d1816 | 60 |
| FQ!N14 | mineral | tratado | ≥230 | - | ten | 35 |
| FQ!N15 | mineral | tratado | ≥230 | - | wat | 15 |
| FQ!N16 | mineral | tratado | ≥230 | - | col | 1.5 |
| FQ!N17 | mineral | tratado | ≥230 | - | con | Brillante y Claro |
| FQ!O6 | conmutador | nuevo | ≤69 | - | acid | 0.03 - máximo |
| FQ!O11 | conmutador | nuevo | ≤69 | - | rig_d1816 | 45 |
| FQ!O12 | conmutador | nuevo | ≤69 | - | rig_d877 | 30 |
| FQ!O13 | conmutador | nuevo | ≤69 | - | rig_iec60156 | 30 -mínimo |
| FQ!O15 | conmutador | nuevo | ≤69 | - | wat | 20 |
| FQ!P6 | conmutador | nuevo | >69 | - | acid | 0.03 - máximo |
| FQ!P11 | conmutador | nuevo | >69 | - | rig_d1816 | 55 |
| FQ!P12 | conmutador | nuevo | >69 | - | rig_d877 | 30 |
| FQ!P13 | conmutador | nuevo | >69 | - | rig_iec60156 | 30 -mínimo |
| FQ!P15 | conmutador | nuevo | >69 | - | wat | 10 |
| FQ!Q6 | conmutador | en_servicio | neutral | - | acid | 0.2- máximo |
| FQ!Q11 | conmutador | en_servicio | neutral | - | rig_d1816 | 30 |
| FQ!Q12 | conmutador | en_servicio | neutral | - | rig_d877 | 25 |
| FQ!Q13 | conmutador | en_servicio | neutral | - | rig_iec60156 | 40 -mínimo |
| FQ!Q14 | conmutador | en_servicio | neutral | - | ten | 25 |
| FQ!Q15 | conmutador | en_servicio | neutral | - | wat | 40 |
| FQ!Q16 | conmutador | en_servicio | neutral | - | col | 2 |
| FQ!R13 | conmutador | en_servicio | ≤69 | - | rig_iec60156 | 40 -mínimo |
| FQ!S13 | conmutador | en_servicio | >69 | - | rig_iec60156 | 40 -mínimo |
| FQ!T6 | silicona | nuevo | - | - | acid | 0.01- máximo |
| FQ!T7 | silicona | nuevo | - | - | pot@25 | 0.01 |
| FQ!T12 | silicona | nuevo | - | - | rig_d877 | 35 |
| FQ!T15 | silicona | nuevo | - | - | wat | 50 |
| FQ!T16 | silicona | nuevo | - | - | col | 15 |
| FQ!T18 | silicona | nuevo | - | - | den | 0.964 |
| FQ!U6 | silicona | nuevo_en_trafo | - | - | acid | 0.01- máximo |
| FQ!U7 | silicona | nuevo_en_trafo | - | - | pot@25 | 0.1 |
| FQ!U12 | silicona | nuevo_en_trafo | - | - | rig_d877 | 30 |
| FQ!U15 | silicona | nuevo_en_trafo | - | - | wat | 50 |
| FQ!U17 | silicona | nuevo_en_trafo | - | - | con | Claro y libre de partículas |
| FQ!W6 | midel | nuevo | - | - | acid | 0.03 |
| FQ!W9 | midel | nuevo | - | - | pot@90 | 0.03 |
| FQ!W13 | midel | nuevo | - | - | rig_iec60156 | 45 |
| FQ!W15 | midel | nuevo | - | - | wat | 200 |
| FQ!W18 | midel | nuevo | - | - | den | 1 |
| FQ!X6 | midel | usado | - | - | acid | 2 |
| FQ!X8 | midel | usado | - | - | pot@25_iec60247 | 0.01 |
| FQ!X13 | midel | usado | - | - | rig_iec60156 | 30 |
| FQ!X15 | midel | usado | - | - | wat | 400 |
| FQ!Y6 | vegetal | nuevo | - | - | acid | 0.06 |
| FQ!Y7 | vegetal | nuevo | - | - | pot@25 | 0.2 |
| FQ!Y10 | vegetal | nuevo | - | - | pot@100 | 0.4 |
| FQ!Y11 | vegetal | nuevo | - | - | rig_d1816 | 35 |
| FQ!Y12 | vegetal | nuevo | - | - | rig_d877 | 30 |
| FQ!Y15 | vegetal | nuevo | - | - | wat | 200 |
| FQ!Y16 | vegetal | nuevo | - | - | col | <1.0 |
| FQ!Y18 | vegetal | nuevo | - | - | den | 0.96 |
| FQ!Z6 | vegetal | antes_de_energizar | ≤69 | - | acid | 0.06 |
| FQ!Z7 | vegetal | antes_de_energizar | ≤69 | - | pot@25 | 0.5 |
| FQ!Z11 | vegetal | antes_de_energizar | ≤69 | - | rig_d1816 | 45 |
| FQ!Z15 | vegetal | antes_de_energizar | ≤69 | - | wat | 300 |
| FQ!Z16 | vegetal | antes_de_energizar | ≤69 | - | col | <1.0 |
| FQ!Z17 | vegetal | antes_de_energizar | ≤69 | - | con | B Y C |
| FQ!AA6 | vegetal | antes_de_energizar | >69-230 | - | acid | 0.06 |
| FQ!AA7 | vegetal | antes_de_energizar | >69-230 | - | pot@25 | 0.5 |
| FQ!AA11 | vegetal | antes_de_energizar | >69-230 | - | rig_d1816 | 55 |
| FQ!AA15 | vegetal | antes_de_energizar | >69-230 | - | wat | 150 |
| FQ!AA16 | vegetal | antes_de_energizar | >69-230 | - | col | <1.0 |
| FQ!AA17 | vegetal | antes_de_energizar | >69-230 | - | con | B Y C |
| FQ!AB6 | vegetal | antes_de_energizar | ≥230 | - | acid | 0.06 |
| FQ!AB7 | vegetal | antes_de_energizar | ≥230 | - | pot@25 | 0.5 |
| FQ!AB11 | vegetal | antes_de_energizar | ≥230 | - | rig_d1816 | 60 |
| FQ!AB15 | vegetal | antes_de_energizar | ≥230 | - | wat | 100 |
| FQ!AB16 | vegetal | antes_de_energizar | ≥230 | - | col | <1.0 |
| FQ!AB17 | vegetal | antes_de_energizar | ≥230 | - | con | B Y C |
| OTROS!E5 | general | - | - | - | PCB | Libre de PCB <2 |
| OTROS!F5 | silicona | nuevo | - | - | PCB | Libre de PCB <2 |
| OTROS!G5 | silicona | nuevo_en_trafo | - | - | PCB | Libre de PCB <2 |
| OTROS!H5 | silicona | usado | - | - | PCB | Libre de PCB <2 |
| OTROS!I5 | vegetal | nuevo | - | - | PCB | Libre de PCB <2 |
| OTROS!J5 | vegetal | antes_de_energizar | ≤69 | - | PCB | Libre de PCB <2 |
| OTROS!K5 | vegetal | antes_de_energizar | >69-230 | - | PCB | Libre de PCB <2 |
| OTROS!L5 | vegetal | antes_de_energizar | ≥230 | - | PCB | Libre de PCB <2 |
| OTROS!M5 | vegetal | usado | ≤69 | - | PCB | Libre de PCB <2 |
| OTROS!N5 | vegetal | usado | >69-230 | - | PCB | Libre de PCB <2 |
| OTROS!O5 | vegetal | usado | ≥230 | - | PCB | Libre de PCB <2 |
| OTROS!E6 | general | - | - | - | AZUFRE | No corrosivo |
| OTROS!F6 | silicona | nuevo | - | - | AZUFRE | No corrosivo |
| OTROS!G6 | silicona | nuevo_en_trafo | - | - | AZUFRE | No corrosivo |
| OTROS!H6 | silicona | usado | - | - | AZUFRE | No corrosivo |
| OTROS!I6 | vegetal | nuevo | - | - | AZUFRE | No corrosivo |
| OTROS!J6 | vegetal | antes_de_energizar | ≤69 | - | AZUFRE | No corrosivo |
| OTROS!K6 | vegetal | antes_de_energizar | >69-230 | - | AZUFRE | No corrosivo |
| OTROS!L6 | vegetal | antes_de_energizar | ≥230 | - | AZUFRE | No corrosivo |
| OTROS!M6 | vegetal | usado | ≤69 | - | AZUFRE | No corrosivo |
| OTROS!N6 | vegetal | usado | >69-230 | - | AZUFRE | No corrosivo |
| OTROS!O6 | vegetal | usado | ≥230 | - | AZUFRE | No corrosivo |
| OTROS!E7 | general | - | - | - | DBDS | 5 -máximo |
| OTROS!F7 | silicona | nuevo | - | - | DBDS | 5 -máximo |
| OTROS!G7 | silicona | nuevo_en_trafo | - | - | DBDS | 5 -máximo |
| OTROS!H7 | silicona | usado | - | - | DBDS | 5 -máximo |
| OTROS!I7 | vegetal | nuevo | - | - | DBDS | 5 -máximo |
| OTROS!J7 | vegetal | antes_de_energizar | ≤69 | - | DBDS | 5 -máximo |
| OTROS!K7 | vegetal | antes_de_energizar | >69-230 | - | DBDS | 5 -máximo |
| OTROS!L7 | vegetal | antes_de_energizar | ≥230 | - | DBDS | 5 -máximo |
| OTROS!M7 | vegetal | usado | ≤69 | - | DBDS | 5 -máximo |
| OTROS!N7 | vegetal | usado | >69-230 | - | DBDS | 5 -máximo |
| OTROS!O7 | vegetal | usado | ≥230 | - | DBDS | 5 -máximo |
| OTROS!E8 | general | - | - | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!F8 | silicona | nuevo | - | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!G8 | silicona | nuevo_en_trafo | - | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!H8 | silicona | usado | - | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!I8 | vegetal | nuevo | - | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!J8 | vegetal | antes_de_energizar | ≤69 | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!K8 | vegetal | antes_de_energizar | >69-230 | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!L8 | vegetal | antes_de_energizar | ≥230 | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!M8 | vegetal | usado | ≤69 | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!N8 | vegetal | usado | >69-230 | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!O8 | vegetal | usado | ≥230 | - | INHIBIDOR | 0.08 - 3.00% |
| OTROS!F9 | silicona | nuevo | - | - | Flash point (°C min.) | 300 |
| OTROS!F10 | silicona | nuevo | - | - | Fire point (°C min.) | 340 |
| OTROS!G10 | silicona | nuevo_en_trafo | - | - | Fire point (°C min.) | 340 |
| OTROS!H10 | silicona | usado | - | - | Fire point (°C min.) | 340 |
| OTROS!F11 | silicona | nuevo | - | - | Pour point (°C max.) | -50 |
| OTROS!F12 | silicona | nuevo | - | - | Viscosity at 0 °C, cSt | 92 |
| OTROS!G12 | silicona | nuevo_en_trafo | - | - | Viscosity at 0 °C, cSt | 52.5 |
| OTROS!H12 | silicona | usado | - | - | Viscosity at 0 °C, cSt | 52.5 |
| OTROS!F13 | silicona | nuevo | - | - | Viscosity at 25 °C, cSt | 52.5 |
| OTROS!I14 | vegetal | nuevo | - | - | Viscosity at 40 °C, cSt | 50 |
| OTROS!J14 | vegetal | antes_de_energizar | ≤69 | - | Viscosity at 40 °C, cSt | 50 |
| OTROS!K14 | vegetal | antes_de_energizar | >69-230 | - | Viscosity at 40 °C, cSt | 50 |
| OTROS!L14 | vegetal | antes_de_energizar | ≥230 | - | Viscosity at 40 °C, cSt | 50 |
| OTROS!M14 | vegetal | usado | ≤69 | - | Viscosity at 40 °C, cSt | ≥10% Valor inicial |
| OTROS!N14 | vegetal | usado | >69-230 | - | Viscosity at 40 °C, cSt | ≥10% Valor inicial |
| OTROS!O14 | vegetal | usado | ≥230 | - | Viscosity at 40 °C, cSt | ≥10% Valor inicial |
| OTROS!F15 | silicona | nuevo | - | - | Viscosity at 100 °C, cSt | 17 |
| OTROS!E16 | general | - | - | - | GRADO DE POLIMERIZACION | 1000-1200 (Nuevo) |
| OTROS!E17 | general | - | - | - | GRADO DE POLIMERIZACION | 650-1000 (Bueno) |
| OTROS!E18 | general | - | - | - | GRADO DE POLIMERIZACION | 350-650 (Medianamente envejecido) |
| OTROS!E19 | general | - | - | - | GRADO DE POLIMERIZACION | < 350 (Envejecido) |
| OTROS!E20 | general | - | - | - | PASIVADOR | <50 ppm (Deficiente) |
| OTROS!E21 | general | - | - | - | PASIVADOR | 50-70 ppm (Cuestionable) |
| OTROS!E22 | general | - | - | - | PASIVADOR | >70 ppm (Bueno) |


## 4. Coinciden — confirmados contra la fuente primaria

| Cuadro | Analito | Excel | Sembrado | Celda |
|---|---|---|---|---|
| Conmutador | h2 | - | - | CR!C18 |
| Conmutador | o2 | - | - | CR!D18 |
| Conmutador | n2 | - | - | CR!E18 |
| Conmutador | ch4 | - | - | CR!F18 |
| Conmutador | co | - | - | CR!G18 |
| Conmutador | co2 | - | - | CR!H18 |
| Conmutador | c2h4 | - | - | CR!I18 |
| Conmutador | c2h6 | - | - | CR!J18 |
| Conmutador | c2h2 | - | - | CR!K18 |
| Distribución · Mineral | h2 | 100 -máximo | 100 - máximo | CR!C5 |
| Distribución · Mineral | o2 | - | - | CR!D5 |
| Distribución · Mineral | n2 | - | - | CR!E5 |
| Distribución · Mineral | ch4 | 50 -máximo | 50 - máximo | CR!F5 |
| Distribución · Mineral | co | 200 -máximo | 200 - máximo | CR!G5 |
| Distribución · Mineral | co2 | 5000 -máximo | 5000 - máximo | CR!H5 |
| Distribución · Mineral | c2h4 | 50 -máximo | 50 - máximo | CR!I5 |
| Distribución · Mineral | c2h6 | 50 -máximo | 50 - máximo | CR!J5 |
| Distribución · Mineral | c2h2 | 5 -máximo | 5 - máximo | CR!K5 |
| Potencia · Mineral | h2 | 150 | 150 - máximo | CR!C6 |
| Potencia · Mineral | o2 | - | - | CR!D6 |
| Potencia · Mineral | n2 | - | - | CR!E6 |
| Potencia · Mineral | ch4 | 130 | 130 - máximo | CR!F6 |
| Potencia · Mineral | co | 600 | 600 - máximo | CR!G6 |
| Potencia · Mineral | co2 | 14000 | 14000 - máximo | CR!H6 |
| Potencia · Mineral | c2h4 | 280 | 280 - máximo | CR!I6 |
| Potencia · Mineral | c2h6 | 90 | 90 - máximo | CR!J6 |
| Potencia · Mineral | c2h2 | 20 | 20 - máximo | CR!K6 |
| Horno · Mineral | h2 | 200 | 200 - máximo | CR!C7 |
| Horno · Mineral | o2 | - | - | CR!D7 |
| Horno · Mineral | n2 | - | - | CR!E7 |
| Horno · Mineral | ch4 | 150 | 150 - máximo | CR!F7 |
| Horno · Mineral | co | 800 | 800 - máximo | CR!G7 |
| Horno · Mineral | co2 | 6000 | 6000 - máximo | CR!H7 |
| Horno · Mineral | c2h4 | 200 | 200 - máximo | CR!I7 |
| Horno · Mineral | c2h6 | 150 | 150 - máximo | CR!J7 |
| De corriente · Mineral | h2 | 300 | 300 - máximo | CR!C8 |
| De corriente · Mineral | o2 | - | - | CR!D8 |
| De corriente · Mineral | n2 | - | - | CR!E8 |
| De corriente · Mineral | ch4 | 120 | 120 - máximo | CR!F8 |
| De corriente · Mineral | co | 1100 | 1100 - máximo | CR!G8 |
| De corriente · Mineral | co2 | 4000 | 4000 - máximo | CR!H8 |
| De corriente · Mineral | c2h4 | 40 | 40 - máximo | CR!I8 |
| De corriente · Mineral | c2h6 | 130 | 130 - máximo | CR!J8 |
| De corriente · Mineral | c2h2 | 5 | 5 - máximo | CR!K8 |
| De voltaje · Mineral | h2 | 1000 | 1000 - máximo | CR!C9 |
| De voltaje · Mineral | o2 | - | - | CR!D9 |
| De voltaje · Mineral | n2 | - | - | CR!E9 |
| De voltaje · Mineral | ch4 | - | - | CR!F9 |
| De voltaje · Mineral | co | - | - | CR!G9 |
| De voltaje · Mineral | co2 | - | - | CR!H9 |
| De voltaje · Mineral | c2h4 | 30 | 30 - máximo | CR!I9 |
| De voltaje · Mineral | c2h6 | - | - | CR!J9 |
| De voltaje · Mineral | c2h2 | 16 | 16 | CR!K9 |
| Instrumento · Mineral | h2 | 300 | 300 - máximo | CR!C10 |
| Instrumento · Mineral | o2 | - | - | CR!D10 |
| Instrumento · Mineral | n2 | - | - | CR!E10 |
| Instrumento · Mineral | ch4 | 30 | 30 - máximo | CR!F10 |
| Instrumento · Mineral | co | 300 | 300 - máximo | CR!G10 |
| Instrumento · Mineral | co2 | 900 | 900 - máximo | CR!H10 |
| Instrumento · Mineral | c2h4 | 10 | 10 - máximo | CR!I10 |
| Instrumento · Mineral | c2h6 | 50 | 50 - máximo | CR!J10 |
| Instrumento · Mineral | c2h2 | 2 | 2 - máximo | CR!K10 |
| Bushing · Mineral | h2 | 392 | 392 - máximo | CR!C11 |
| Bushing · Mineral | o2 | - | - | CR!D11 |
| Bushing · Mineral | n2 | - | - | CR!E11 |
| Bushing · Mineral | ch4 | 216 | 216 - máximo | CR!F11 |
| Bushing · Mineral | co | 927 | 927 - máximo | CR!G11 |
| Bushing · Mineral | co2 | 11578 | 11578 - máximo | CR!H11 |
| Bushing · Mineral | c2h4 | 70 | 70 - máximo | CR!I11 |
| Bushing · Mineral | c2h6 | 121 | 121 - máximo | CR!J11 |
| Bushing · Mineral | c2h2 | 5 | 5 - máximo | CR!K11 |
| Cables · Mineral | h2 | 500 | 500 - máximo | CR!C12 |
| Cables · Mineral | o2 | - | - | CR!D12 |
| Cables · Mineral | n2 | - | - | CR!E12 |
| Cables · Mineral | ch4 | 30 | 30 - máximo | CR!F12 |
| Cables · Mineral | co | 100 | 100 - máximo | CR!G12 |
| Cables · Mineral | co2 | 500 | 500 - máximo | CR!H12 |
| Cables · Mineral | c2h4 | 20 | 20 - máximo | CR!I12 |
| Cables · Mineral | c2h6 | 25 | 25 - máximo | CR!J12 |
| Cables · Mineral | c2h2 | 10 | 10 - máximo | CR!K12 |
| Interruptor · Mineral | h2 | - | - | CR!C13 |
| Interruptor · Mineral | o2 | - | - | CR!D13 |
| Interruptor · Mineral | n2 | - | - | CR!E13 |
| Interruptor · Mineral | ch4 | - | - | CR!F13 |
| Interruptor · Mineral | co | - | - | CR!G13 |
| Interruptor · Mineral | co2 | - | - | CR!H13 |
| Interruptor · Mineral | c2h4 | - | - | CR!I13 |
| Interruptor · Mineral | c2h6 | - | - | CR!J13 |
| Interruptor · Mineral | c2h2 | - | - | CR!K13 |
| Silicona | h2 | 200 | 200 - máximo | CR!C14 |
| Silicona | o2 | - | - | CR!D14 |
| Silicona | n2 | - | - | CR!E14 |
| Silicona | ch4 | 100 | 100 - máximo | CR!F14 |
| Silicona | co | 3000 | 3000 - máximo | CR!G14 |
| Silicona | co2 | 30000 | 30000 - máximo | CR!H14 |
| Silicona | c2h4 | 30 | 30 - máximo | CR!I14 |
| Silicona | c2h6 | 30 | 30 - máximo | CR!J14 |
| Silicona | c2h2 | 1 | 1 - máximo | CR!K14 |
| Midel | h2 | 64 | 64 - máximo | CR!C15 |
| Midel | o2 | - | - | CR!D15 |
| Midel | n2 | - | - | CR!E15 |
| Midel | ch4 | 104 | 104 - máximo | CR!F15 |
| Midel | co | 1344 | 1344 - máximo | CR!G15 |
| Midel | co2 | - | - | CR!H15 |
| Midel | c2h4 | 150 | 150 - máximo | CR!I15 |
| Midel | c2h6 | 124 | 124 - máximo | CR!J15 |
| Midel | c2h2 | 13 | 13 - máximo | CR!K15 |
| Éster vegetal | h2 | 112 | 112 - máximo | CR!C16 |
| Éster vegetal | o2 | - | - | CR!D16 |
| Éster vegetal | n2 | - | - | CR!E16 |
| Éster vegetal | ch4 | 20 | 20 - máximo | CR!F16 |
| Éster vegetal | co | 161 | 161 - máximo | CR!G16 |
| Éster vegetal | co2 | - | - | CR!H16 |
| Éster vegetal | c2h4 | 18 | 18 - máximo | CR!I16 |
| Éster vegetal | c2h6 | 232 | 232 - máximo | CR!J16 |
| Éster vegetal | c2h2 | 1 | 1 - máximo | CR!K16 |
| Vegetal girasol | h2 | 35 | 35 - máximo | CR!C17 |
| Vegetal girasol | o2 | - | - | CR!D17 |
| Vegetal girasol | n2 | - | - | CR!E17 |
| Vegetal girasol | ch4 | 25 | 25 - máximo | CR!F17 |
| Vegetal girasol | co | 497 | 497 - máximo | CR!G17 |
| Vegetal girasol | co2 | - | - | CR!H17 |
| Vegetal girasol | c2h4 | 16 | 16 - máximo | CR!I17 |
| Vegetal girasol | c2h6 | 58 | 58 - máximo | CR!J17 |
| Vegetal girasol | c2h2 | 0 | 0 - máximo | CR!K17 |
| Conmutador · ≤69 kV | acid | 0.2- máximo | 0.20 - máximo | FQ!R6 |
| Conmutador · ≤69 kV | pot@25 | - | - | FQ!R7 |
| Conmutador · ≤69 kV | pot@90 | - | - | FQ!R9 |
| Conmutador · ≤69 kV | pot@100 | - | - | FQ!R10 |
| Conmutador · ≤69 kV | rig_d1816 | 35 | 35.0 - mínimo | FQ!R11 |
| Conmutador · ≤69 kV | rig_d877 | 25 | 25.0 - mínimo | FQ!R12 |
| Conmutador · ≤69 kV | ten | 25 | 25.0 - mínimo | FQ!R14 |
| Conmutador · ≤69 kV | wat | 30 | 30.0 - máximo | FQ!R15 |
| Conmutador · ≤69 kV | col | 2 | 2.0 - máximo | FQ!R16 |
| Conmutador · ≤69 kV | con | - | - | FQ!R17 |
| Conmutador · ≤69 kV | den | - | - | FQ!R18 |
| Conmutador · >69 kV | acid | 0.2- máximo | 0.20 - máximo | FQ!S6 |
| Conmutador · >69 kV | pot@25 | - | - | FQ!S7 |
| Conmutador · >69 kV | pot@90 | - | - | FQ!S9 |
| Conmutador · >69 kV | pot@100 | - | - | FQ!S10 |
| Conmutador · >69 kV | rig_d1816 | 45 | 45.0 - mínimo | FQ!S11 |
| Conmutador · >69 kV | rig_d877 | 25 | 25.0 - mínimo | FQ!S12 |
| Conmutador · >69 kV | ten | 25 | 25.0 - mínimo | FQ!S14 |
| Conmutador · >69 kV | wat | 25 | 25.0 - máximo | FQ!S15 |
| Conmutador · >69 kV | col | 2 | 2.0 - máximo | FQ!S16 |
| Conmutador · >69 kV | con | - | - | FQ!S17 |
| Conmutador · >69 kV | den | - | - | FQ!S18 |
| Mineral · ≤69 kV | acid | 0.20 - máximo | 0.20 - máximo | FQ!I6 |
| Mineral · ≤69 kV | pot@25 | 0.5 | 0.50 - máximo | FQ!I7 |
| Mineral · ≤69 kV | pot@90 | - | - | FQ!I9 |
| Mineral · ≤69 kV | pot@100 | 5 | 5.0 - máximo | FQ!I10 |
| Mineral · ≤69 kV | rig_d1816 | 40 | 40.0 - mínimo | FQ!I11 |
| Mineral · ≤69 kV | rig_d877 | - | - | FQ!I12 |
| Mineral · ≤69 kV | ten | 25 | 25.0 - mínimo | FQ!I14 |
| Mineral · ≤69 kV | wat | 35 | 35.0 - máximo | FQ!I15 |
| Mineral · ≤69 kV | col | - | - | FQ!I16 |
| Mineral · ≤69 kV | den | - | - | FQ!I18 |
| Mineral · 69-230 kV | acid | 0.15 - máximo | 0.15 - máximo | FQ!J6 |
| Mineral · 69-230 kV | pot@25 | 0.5 | 0.50 - máximo | FQ!J7 |
| Mineral · 69-230 kV | pot@90 | - | - | FQ!J9 |
| Mineral · 69-230 kV | pot@100 | 5 | 5.0 - máximo | FQ!J10 |
| Mineral · 69-230 kV | rig_d1816 | 47 | 47.0 - mínimo | FQ!J11 |
| Mineral · 69-230 kV | rig_d877 | - | - | FQ!J12 |
| Mineral · 69-230 kV | ten | 30 | 30.0 - mínimo | FQ!J14 |
| Mineral · 69-230 kV | wat | 25 | 25.0 - máximo | FQ!J15 |
| Mineral · 69-230 kV | col | - | - | FQ!J16 |
| Mineral · 69-230 kV | den | - | - | FQ!J18 |
| Mineral · ≥230 kV | acid | 0.10 - máximo | 0.10 - máximo | FQ!K6 |
| Mineral · ≥230 kV | pot@25 | 0.5 | 0.50 - máximo | FQ!K7 |
| Mineral · ≥230 kV | pot@90 | - | - | FQ!K9 |
| Mineral · ≥230 kV | pot@100 | 5 | 5.0 - máximo | FQ!K10 |
| Mineral · ≥230 kV | rig_d1816 | 50 | 50.0 - mínimo | FQ!K11 |
| Mineral · ≥230 kV | rig_d877 | - | - | FQ!K12 |
| Mineral · ≥230 kV | ten | 32 | 32.0 - mínimo | FQ!K14 |
| Mineral · ≥230 kV | wat | 20 | 20.0 - máximo | FQ!K15 |
| Mineral · ≥230 kV | col | - | - | FQ!K16 |
| Mineral · ≥230 kV | den | - | - | FQ!K18 |
| Silicona | acid | 0.2- máximo | 0.20 - máximo | FQ!V6 |
| Silicona | pot@25 | 0.2 | 0.20 - máximo | FQ!V7 |
| Silicona | pot@90 | - | - | FQ!V9 |
| Silicona | pot@100 | - | - | FQ!V10 |
| Silicona | rig_d1816 | - | - | FQ!V11 |
| Silicona | rig_d877 | 25 | 25.0 - mínimo | FQ!V12 |
| Silicona | ten | - | - | FQ!V14 |
| Silicona | wat | 100 | 100 - máximo | FQ!V15 |
| Silicona | col | - | - | FQ!V16 |
| Silicona | con | Claro y libre de partículas | Claro y libre de partículas | FQ!V17 |
| Silicona | den | - | - | FQ!V18 |
| Éster · ≤72.5 kV | acid | 0.5 | 0.50 - máximo | FQ!AC6 |
| Éster · ≤72.5 kV | pot@100 | - | - | FQ!AC10 |
| Éster · ≤72.5 kV | rig_d1816 | 40 | 40.0 - mínimo | FQ!AC11 |
| Éster · ≤72.5 kV | rig_d877 | - | - | FQ!AC12 |
| Éster · ≤72.5 kV | den | - | - | FQ!AC18 |
| Éster · 72.5-170 kV | pot@100 | - | - | FQ!AD10 |
| Éster · 72.5-170 kV | rig_d877 | - | - | FQ!AD12 |
| Éster · 72.5-170 kV | den | - | - | FQ!AD18 |
| Éster · ≥170 kV | pot@100 | - | - | FQ!AE10 |
| Éster · ≥170 kV | rig_d877 | - | - | FQ!AE12 |
| Éster · ≥170 kV | den | - | - | FQ!AE18 |

