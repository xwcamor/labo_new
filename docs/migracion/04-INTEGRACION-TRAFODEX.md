# Integración TR LAB → TRAFODEX

> Reemplaza la escritura directa en la base de TRAFODEX por una API con
> idempotencia, cola de reintento y envío del informe firmado.
>
> Documento espejo del lado de TRAFODEX:
> `trafodex/docs/INTEGRACION-LABORATORIO.md`.

---

## 1. Qué hay hoy y por qué se cambia

`labo_old` abre una segunda conexión (`establish_connection(:primary2)`) contra
`tr_app_development` e inserta filas a mano en `chromatographicals`, `physicals`,
`furanos` y `transformers`.

Problemas ya detallados en el análisis: sin idempotencia (reejecutar duplica),
sin transacción, emparejamiento de equipos por número de serie en texto con
`find_by` (toma el primero si hay repetidos), acoplamiento al esquema, y el
motor de diagnóstico de TRAFODEX no corre, así que el índice de salud queda
desactualizado. Además el PDF firmado nunca llega.

**Decisión: API HTTP.** No cola compartida, no base compartida, no webhook
inverso obligatorio.

Razones: los dos sistemas ya viven en máquinas y bases distintas; TRAFODEX ya
tiene `/api/v1` con Sanctum, abilities por token y `BelongsToTenant` que filtra
por workspace automáticamente; y una API deja el motor de diagnóstico corriendo
del lado correcto (el diagnóstico es de TRAFODEX, no del laboratorio).

---

## 2. Reparto de responsabilidades

| | TR LAB | TRAFODEX |
|---|---|---|
| Recibir la muestra física | sí | no |
| Ejecutar el ensayo | sí | no |
| Evaluar contra criterio de aceptación | sí | no |
| Emitir el informe de ensayo firmado | sí | no |
| Diagnóstico del equipo (DGAF, Duval, IEEE, HI) | **no** | sí |
| Tendencias del equipo en el tiempo | no | sí |
| Tablero de flota | no | sí |

TR LAB dice "este aceite cumple/no cumple la norma de aceptación".
TRAFODEX dice "este transformador tiene una falla térmica y su índice de salud
es 62". Son dos preguntas distintas y no se deben mezclar.

---

## 3. Contrato

Base: `POST https://{trafodex}/api/v1/...`
Auth: `Authorization: Bearer {token}` — Sanctum, token del usuario de sistema
del workspace, con ability `lab:write`.

### 3.1 Resolución del equipo

Antes de enviar resultados hay que saber a qué transformador de TRAFODEX
corresponde. Tres vías, en orden:

1. `equipment.external_ref` ya poblado → se usa directo.
2. `GET /api/v1/transformers/lookup?serial=...&tag=...&customer=...`
   → devuelve `[]`, `[uno]` o `[varios]`.
3. Si devuelve varios o ninguno, **no se adivina**: la muestra queda en estado
   `equipo_sin_vincular` y aparece en una bandeja de conciliación donde un
   humano elige o crea. Ésta es la diferencia concreta con el `find_by` actual.

### 3.2 Envío de resultados

```http
POST /api/v1/lab-results
Idempotency-Key: 6b1f...  (uuid de outbound_messages)

{
  "transformer": { "slug": "aBc...", "serial": "12345", "tag": "TR-01" },
  "lab": {
    "laboratory_code": "HITACHI-PGTR",
    "report_number": "REP-LAB-2026-0001",
    "sample_code": "2026-0744",
    "sampled_at": "2026-07-21T09:30:00-05:00",
    "issued_at":  "2026-07-24T16:00:00-05:00"
  },
  "tests": [
    {
      "kind": "chromatography",
      "measured_at": "2026-07-21T14:00:00-05:00",
      "values": { "h2": 12, "o2": 21000, "n2": 61000, "ch4": 5,
                  "co": 340, "co2": 2100, "c2h4": 2, "c2h6": 3, "c2h2": 0 }
    },
    {
      "kind": "physicochemical",
      "measured_at": "2026-07-21T11:00:00-05:00",
      "values": { "acid": 0.002, "rig": 70, "ten": 49.8, "wat": 6 },
      "methods": { "rig": { "standard": "ASTM D1816", "gap_mm": 2.0 },
                   "pot": { "standard": "ASTM D924",  "temp_c": 25 } }
    },
    { "kind": "furanos", "measured_at": "...", "values": { "fal": 120 } },
    { "kind": "power_factor", "measured_at": "...",
      "values": { "pot": 0.15, "temp_c": 25 } }
  ]
}
```

Respuesta `201`:

```json
{
  "transformer": { "slug": "aBc...", "health_index": 78.4, "health_rating": 3,
                   "condition": "Bueno" },
  "created": [ { "kind": "chromatography", "id": 9134, "dgaf_score": 1.12,
                 "dgaf_condition": "Muy Bueno" } ],
  "warnings": []
}
```

Puntos del diseño:

- **Códigos de analito, no columnas.** El cuerpo usa `h2`, `acid`, `rig` — los
  mismos `analytes.code` de TR LAB. TRAFODEX los mapea a sus columnas. Agregar
  un parámetro no cambia el contrato.
- **`Idempotency-Key` obligatorio.** TRAFODEX guarda la clave; un reenvío
  devuelve `200` con el mismo cuerpo en vez de crear una muestra duplicada.
  Esto es lo que hoy no existe y hace peligroso reintentar.
- **El diagnóstico corre del lado de TRAFODEX** y su resultado vuelve en la
  respuesta, para que el laboratorio pueda mostrarlo como referencia.
- **`methods` es informativo pero se guarda.** TRAFODEX tiene pendiente
  confirmar el gap de rigidez de sus datos históricos; a partir de esta
  integración lo recibe explícito.

### 3.3 Envío del informe firmado

```http
POST /api/v1/lab-results/{id}/documents
Content-Type: multipart/form-data

file:          REP-LAB-2026-0001.pdf
kind:          lab_report
report_number: REP-LAB-2026-0001
issued_at:     2026-07-24T16:00:00-05:00
sha256:        3f2a...
verify_url:    https://lab.../verify/AB12CD34
```

TRAFODEX lo guarda asociado a la muestra y lo muestra en la ficha del
transformador como "Informe de laboratorio" descargable, junto con el enlace de
verificación del laboratorio.

Se envía **solo el PDF emitido**, nunca el borrador ni el Word editable: el
Word no puede sostener la promesa de autenticidad (criterio ya establecido en
TRAFODEX para su propio informe editable).

### 3.4 Alta de equipos

```http
POST /api/v1/transformers        (ability: transformers:write)
```

Solo cuando el laboratorio recibe una muestra de un equipo que TRAFODEX no
conoce **y** el operador lo confirma desde la bandeja de conciliación. Nunca
automático: crear transformadores fantasma por un error de tipeo en el número
de serie ensucia la flota y el tablero.

---

## 4. Envío asíncrono y reintentos

Nada se envía dentro del ciclo de petición del usuario.

```
Informe emitido
  └─ evento ReportIssued
       └─ crea outbound_messages (status=pendiente, idempotency_key=uuid)
            └─ job SendLabResult (cola database, backoff exponencial)
                 ├─ 2xx        → status=enviado, guarda remote_id + respuesta
                 ├─ 409/422    → status=error, NO reintenta (dato inválido)
                 ├─ 5xx / red  → reintenta (1m, 5m, 15m, 1h, 6h), luego error
                 └─ agotado    → notifica al admin del workspace
```

Bandeja "Envíos a TRAFODEX": pendientes, enviados, con error; reintento manual;
ver el payload y la respuesta. Es lo que hoy no existe — el asistente de 4 pasos
no deja rastro de qué se envió ni de si falló.

Configuración por workspace en `integration_targets`: URL, token cifrado,
activo/inactivo, y si el envío es automático al emitir o manual.

---

## 5. Retorno opcional de TRAFODEX

Fase posterior, no bloqueante: `POST {lab}/api/v1/webhooks/diagnosis` para que
TRAFODEX avise cuando recalcula el índice de salud de un equipo con muestras
del laboratorio. Sirve para que el informe de laboratorio pueda incluir, como
anexo informativo, el estado del equipo.

Se deja fuera de la fase 7 a propósito: TRAFODEX tiene los webhooks documentados
como funcionalidad premium futura y no conviene adelantarlos por esto.

---

## 6. Cambios necesarios del lado de TRAFODEX

Resumen; el detalle está en `trafodex/docs/INTEGRACION-LABORATORIO.md`.

1. Nuevo `LabResultApiController` bajo `/api/v1`, siguiendo el patrón de
   `CustomerApiController`.
2. Abilities nuevas: `lab:write`, `transformers:read`, `transformers:write`.
3. Tabla `idempotency_keys` (key, token_id, response, expires_at).
4. Tabla `sample_documents` polimórfica para el PDF del laboratorio.
5. `GET /api/v1/transformers/lookup`.
6. Al insertar una muestra por API, disparar el mismo recálculo que la UI
   (`HealthIndexService::evaluate` y la caché de flota).
7. Nueva feature de plan: `lab_integration` (enterprise, junto con `api_access`).
