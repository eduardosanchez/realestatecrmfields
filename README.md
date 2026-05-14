# RealEstateCrmFields — Módulo CRM Inmobiliario para Dolibarr

Módulo Dolibarr (ID 500100) que extiende el CRM nativo con clasificación de
terceros en inmuebles, seguimiento de consultas de compradores, gestión de
contacto con propietarios, tablero ejecutivo e ingesta de leads vía webhook.

---

## Stack

- **Backend**: PHP 7.4+ sobre Dolibarr 23.0.2
- **Base de datos**: MySQL/MariaDB, prefijo `zu4s_`
- **Frontend**: jQuery, Font Awesome (incluidos en Dolibarr)
- **Integración externa**: Webhook desde WordPress/WPForms (`esanchez.com.ar`)

---

## Páginas principales

| Archivo | Descripción |
|---|---|
| `captacion.php` | Inmuebles en captación. Lista RE_ACT con último contacto, propietario, semáforo de urgencia, puntuación de prioridad por barrio |
| `compradores.php` | Seguimiento de compradores (RE_AOR) con estado de consultas, agente asignado, recordatorios |
| `actores.php` | Lista de actores con datos agregados de consultas, montos de inversión, búsqueda |
| `__actores.php` | Versión dev de actores con filtros extra: *pendientes hoy*, *días sin contacto (30/60/90)*, temperatura |
| `pendientes.php` | Recordatorios vencidos/programados de consultas y gestiones. Marcar como contactado |
| `dashboard.php` | Tablero con 4 KPIs, leads por origen, pendientes por prioridad, cierres mensuales, top-5 inmuebles consultados |
| `actor_timeline.php` | Línea de tiempo de un actor: todas sus consultas + logs + stats rápidas |

---

## Modelos / Clases

| Clase | Archivo | Responsabilidad |
|---|---|---|
| `ReConsulta` | `class/reconsulta.class.php` | Consultas de comprador sobre inmueble (CRUD, búsqueda) |
| `ReGestion` | `class/regestion.class.php` | Contacto con propietario en captación (CRUD, búsqueda) |
| `RePropietario` | `class/repropietario.class.php` | Vínculo propietario↔inmueble con histórico (CRUD, desvincular) |
| `RealEstateCrmFields` | `class/realestatecrmfields.class.php` | Tipos, subtipos, visibilidad de campos extrafields |
| `Actions_realestatecrmfields` | `class/actions_realestatecrmfields.class.php` | Hooks Dolibarr: inyecta selector de subtipo, columnas/filtros en listas, secciones en ficha |

---

## Tablas creadas por el módulo

| Tabla | Uso |
|---|---|
| `zu4s_c_re_subtypent` | Subtipos de terceros (INVERSOR, GARAJE, ABOGADO…) agrupados por fk_typent |
| `zu4s_re_extrafields_visibility` | Reglas de visibilidad de campos extrafields por tipo+subtipo |
| `zu4s_re_consulta` | Consultas de compradores: canal, estado, nota, rango USD, recordatorio, cierre |
| `zu4s_re_consulta_log` | Bitácora de cambios de estado en consultas |
| `zu4s_re_gestion_propietario` | Contactos de captación con propietarios: canal, resultado, nota, recordatorio |
| `zu4s_re_propietario_activo` | Relación propietario↔inmueble con rol, fechas desde/hasta, activo |

**Columna agregada a `zu4s_societe`**: `fk_re_subtypent VARCHAR(32)`

---

## Endpoints AJAX

| Archivo | Acciones | Funcionalidad |
|---|---|---|
| `ajax/consulta_save.php` | create/update/delete/get/search/log | CRUD completo de consultas + bitácora + búsqueda de actores/activos |
| `ajax/gestion_save.php` | create/update/delete/get/search | CRUD de gestión con propietarios |
| `ajax/propietario_save.php` | create/update/desvincular/delete/get | CRUD de vínculos propietario↔inmueble |
| `ajax/pendiente_done.php` | done/done_gest | Marcar recordatorio como completado con nota de seguimiento |
| `ajax/webhook_lead.php` | POST | Recibe leads de WordPress, crea/actualiza actor, genera consulta con recordatorio |
| `ajax/get_activo_public.php` | GET | Datos públicos del inmueble + URL de ficha PDF (sin sesión Dolibarr) |
| `ajax/matching_activos.php` | GET | Matching de inmuebles según presupuesto, barrio y tipo de comprador |
| `ajax/get_hidden_fields.php` | GET | Campos ocultos para tipo+subtipo |
| `ajax/update_visibility.php` | POST | Toggle visibilidad de campo |
| `ajax/save_subtype.php` | POST | Guarda subtipo del tercero |

---

## Secciones en ficha de tercero (tpl/)

| Template | Se inyecta en | Muestra |
|---|---|---|
| `tpl/consultas_section.tpl.php` | Ficha RE_ACT / RE_AOR | Tabla de consultas recibidas + modal crear/editar + log + semáforo |
| `tpl/gestion_section.tpl.php` | Ficha RE_ACT | Tabla de gestiones con propietario + modal crear/editar |
| `tpl/propietario_section.tpl.php` | Ficha RE_ACT | Propietarios vinculados (actuales + históricos) + modal vincular/desvincular |
| `tpl/activos_section.tpl.php` | Ficha RE_AOR | Inmuebles vinculados al propietario |

---

## helpers.php — funciones compartidas clave

| Función | Uso |
|---|---|
| `reSort($field)`, `reSortIcon($field)` | Links e íconos de ordenamiento de columnas |
| `reTelWa($phone)` | Formatea teléfono argentino para link de WhatsApp |
| `subAbrev($label)` | Abrevia etiquetas de subtipo (ej: "Estacionamiento Servicio" → "Est. Serv.") |
| `semaforo_sql()` | Fragmento SQL que calcula urgencia (verde/naranja/rojo) según días desde último contacto |
| `colorSemaforo($dias, $umbrales)` | Devuelve color según días y umbrales |
| `calcUmbrales($rows)` | Calcula umbrales dinámicos con percentiles |
| `getPrioBarrios()` | Lista de barrios prioritarios de CABA para scoring |
| `prio_barrio_sql()` / `prio_orden_sql()` / `prio_where_sql()` | Fragmentos SQL para ordenar/filtrar por prioridad de barrio |

---

## JS clave

| Archivo | Responsabilidad |
|---|---|
| `js/realestate.js` | Selector de subtipo en ficha, visibilidad dinámica de campos, filtros en listas |
| `js/consultas.js` | Modal CRUD de consultas en ficha de tercero |
| `js/gestion.js` | Modal CRUD de gestión en ficha |
| `js/propietario.js` | Modal de vínculo propietario↔inmueble |
| `js/captacion.js` | Modal de contacto en página de captación |

---

## Archivos SQL en orden de instalación

1. `sql/llx_c_re_subtypent.sql` — Tabla de subtipos
2. `sql/llx_re_extrafields_visibility.sql` — Tabla de visibilidad
3. `sql/llx_re_consulta.sql` — Consultas
4. `sql/llx_re_consulta_alter.sql` — Agrega campos de recordatorio
5. `sql/llx_re_consulta_log.sql` — Bitácora
6. `sql/llx_re_consulta_cierre.sql` — Campos de cierre
7. `sql/llx_re_gestion_propietario.sql` — Gestiones
8. `sql/llx_re_propietario_activo.sql` — Vínculos
9. `sql/data.sql` — Datos iniciales + ALTERs en societe/c_typent
10. `sql/add_indexes.sql` — Índices de performance
11. `sql/fix_typent_codes.sql` — Migración de códigos viejos

---

## Tareas comunes al editar

- **Agregar columna a una tabla de listado**: buscar `$allowed_sort` para campos ordenables, y la sección `foreach ($rows as $row)` para el render de filas.
- **Agregar filtro**: sumar `GETPOST` arriba, condición en `$sqlWhere`, input/select en la fila de filtros del `<form>`.
- **Modificar consulta SQL**: la consulta principal se arma en 3 partes: `$sqlFrom` (FROM + JOINs), `$sqlWhere` (WHERE), y `$sqlSelect` (SELECT). Algunas páginas usan `str_replace` para inyectar JOINs extra — **cuidado** con reemplazar múltiples ocurrencias.
- **Agregar modal**: copiar estructura del modal existente (HTML oculto + JS con `$.post` al endpoint AJAX).
- **Token CSRF**: `getToken()` (definido en helpers.php) o `$('meta[name="anti-csrf-newtoken"]').attr('content')` en JS.

---

## Constantes esperadas en conf/conf.php

```php
$dolibarr_re_webhook_secret = '...';   // shared secret para webhook_lead.php
$dolibarr_re_ficha_secret = '...';     // secret para firmar tokens de PDF
$dolibarr_re_token_ttl = 86400;        // TTL de token (24h default)
```

---

## Requisitos

- Dolibarr 16+ (producción en 23.0.2)
- PHP 7.4+
- MySQL/MariaDB con prefijo `zu4s_`
