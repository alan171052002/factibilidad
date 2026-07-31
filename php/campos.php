<?php
// ============================================================
//  DEFINICIÓN DE CAMPOS Y PESOS — extraída del Excel DFM
//  Total pesos = 1.00  (100%)
//  Campos con peso 0 son informativos / sin puntuación directa
// ============================================================

function getCamposDefinicion(): array
{
    return [

        // ── INFORMACIÓN GENERAL (cabecera, sin peso) ─────────────
        [
            'id' => 'general',
            'titulo' => 'Información General',
            'icono' => '📋',
            'campos' => [
                ['clave' => 'cliente', 'label' => 'Cliente', 'tipo' => 'text', 'peso' => 0, 'requerido' => false],
                ['clave' => 'lider_proyecto', 'label' => 'Líder de Proyecto', 'tipo' => 'text', 'peso' => 0, 'requerido' => false],
                ['clave' => 'fecha_entrada', 'label' => 'Fecha de Entrada del Proyecto', 'tipo' => 'date', 'peso' => 0, 'requerido' => false],
                ['clave' => 'fecha_entrega_equipo', 'label' => 'Fecha de Entrega a Equipo', 'tipo' => 'date', 'peso' => 0, 'requerido' => false],
                ['clave' => 'fecha_estimada_cierre', 'label' => 'Fecha Estimada de Cierre', 'tipo' => 'date', 'peso' => 0, 'requerido' => false],
                ['clave' => 'fecha_entrega_lider', 'label' => 'Fecha de Entrega a Líder de PY', 'tipo' => 'date', 'peso' => 0, 'requerido' => false],
                ['clave' => 'fecha_cierre', 'label' => 'Fecha de Cierre de Factibilidad y Cotización', 'tipo' => 'date', 'peso' => 0, 'requerido' => false],
            ],
        ],

        // ── TIPO ─────────────────────────────────────────────────
        [
            'id' => 'tipo',
            'titulo' => 'Tipo de Solicitud',
            'icono' => '🏷️',
            'campos' => [
                [
                    'clave' => 'tipo',
                    'label' => 'Tipo',
                    'tipo' => 'radio',
                    'peso' => 0,
                    'requerido' => false,
                    'opciones' => ['Producto / Product', 'Componente / Component', 'Insumo', 'Ensamble / Assembly', 'Servicio / Service']
                ],
            ],
        ],

        // ── GRADO (1%) ───────────────────────────────────────────
        [
            'id' => 'grado',
            'titulo' => 'Grado de Evaluación',
            'icono' => '⭐',
            'campos' => [
                [
                    'clave' => 'grado',
                    'label' => 'Grado',
                    'tipo' => 'radio',
                    'peso' => 0.01,
                    'requerido' => false,
                    'opciones' => ['Básico', 'Intermedio', 'Complejo']
                ],
            ],
        ],

        // ── DISEÑO (15%) ─────────────────────────────────────────
        [
            'id' => 'diseno',
            'titulo' => 'Diseño',
            'icono' => '📐',
            'campos' => [
                [
                    'clave' => 'diseno_dibujo_maestro',
                    'label' => 'Dibujo Maestro Cliente / Customer Master Drawing',
                    'tipo' => 'select',
                    'peso' => 0.10,
                    'requerido' => true,
                    'opciones' => ['', 'Disponible', 'En proceso', 'No aplica']
                ],
                [
                    'clave' => 'diseno_nivel_ingenieria',
                    'label' => 'Nivel de Ingeniería',
                    'tipo' => 'select',
                    'peso' => 0.02,
                    'requerido' => true,
                    'opciones' => ['', 'A', 'B', 'C', 'D', 'E']
                ],
                [
                    'clave' => 'diseno_muestra',
                    'label' => 'Muestra y/o Contraparte / Samples',
                    'tipo' => 'select',
                    'peso' => 0.03,
                    'requerido' => true,
                    'opciones' => ['', 'Foto', 'Muestra Física (EDC)', 'Contraparte', 'No aplica']
                ],
            ],
        ],

        // ── ESPECIFICACIONES, REQUERIMIENTOS Y TOLERANCIAS (10%+) ─
        [
            'id' => 'especificaciones',
            'titulo' => 'Especificaciones, Requerimientos y Tolerancias',
            'icono' => '📏',
            'campos' => [
                ['clave' => 'spec_dimensionales', 'label' => 'Dimensionales', 'tipo' => 'select', 'peso' => 0.10, 'requerido' => true, 'opciones' => ['', '1 a 75', '76 a 150', '151 a 300']],
                ['clave' => 'spec_geometricas', 'label' => 'Geométricas', 'tipo' => 'select', 'peso' => 0, 'requerido' => true, 'opciones' => ['', 'Si', 'No']],
                ['clave' => 'spec_criticas', 'label' => 'Características Críticas', 'tipo' => 'select', 'peso' => 0, 'requerido' => true, 'opciones' => ['', 'Definidas', 'No definidas', 'No aplica']],
                ['clave' => 'spec_funcionamiento', 'label' => 'Características de Funcionamiento', 'tipo' => 'select', 'peso' => 0, 'requerido' => true, 'opciones' => ['', 'Definidas', 'No definidas', 'No aplica']],
                ['clave' => 'spec_aplicacion', 'label' => 'Aplicación', 'tipo' => 'text', 'peso' => 0, 'requerido' => true],
                ['clave' => 'spec_apariencia', 'label' => 'Apariencia y Acabados', 'tipo' => 'select', 'peso' => 0, 'requerido' => true, 'opciones' => ['', 'Definida', 'No definida', 'No aplica']],
                ['clave' => 'spec_normas_adicionales', 'label' => 'Normas Adicionales (cuando aplique)', 'tipo' => 'text', 'peso' => 0, 'requerido' => true],
            ],
        ],

        // ── MATERIAL — MATERIAS PRIMAS ────────────────────────────
        [
            'id' => 'material',
            'titulo' => 'Material (Materias Primas)',
            'icono' => '🔩',
            'campos' => [
                [
                    'clave' => 'mat_tipo',
                    'label' => 'Tipo de Material (Estándar)',
                    'tipo' => 'checkbox',
                    'peso' => 0,
                    'requerido' => false,
                    'opciones' => ['Barra (Red, Hex, Cuad) / Bar', 'Tubo (Circ, Cuad, Rectangular) / Tube', 'Solera - Placa / Metal Plate']
                ],
                [
                    'clave' => 'mat_preformado',
                    'label' => 'Tipo de Preformado',
                    'tipo' => 'checkbox',
                    'peso' => 0,
                    'requerido' => false,
                    'opciones' => ['Fundición / Casting', 'Forja / Forging', 'Extrusión / Estrusion', 'Piezas con Sobre Material', 'Corte Láser / Laser Cut']
                ],
                [
                    'clave' => 'mat_origen',
                    'label' => 'Origen de MP / Raw Material Origin',
                    'tipo' => 'text',
                    'peso' => 0.02,
                    'requerido' => true
                ],
            ],
        ],

        // ── REQ. COMPLEMENTARIOS PARA PREFORMADOS (17%) ──────────
        [
            'id' => 'preformados',
            'titulo' => 'Req. Complementarios para Productos Preformados',
            'icono' => '📦',
            'campos' => [
                ['clave' => 'pre_dibujos2d_pre', 'label' => 'Dibujos 2D (PDF) Pre-Mecanizados', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Disponible', 'No Disponible']],
                ['clave' => 'pre_modelo3d_pre', 'label' => 'Modelo 3D (IGS/STEP) Pre-Mecanizados', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Disponible', 'No Disponible']],
                ['clave' => 'pre_dibujos2d_mec', 'label' => 'Dibujos 2D (PDF) Mecanizados', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Disponible', 'No Disponible']],
                ['clave' => 'pre_modelo3d_mec', 'label' => 'Modelo 3D (IGS/STEP) Mecanizados', 'tipo' => 'select', 'peso' => 0.10, 'requerido' => true, 'opciones' => ['', 'Disponible', 'No Disponible']],
                ['clave' => 'pre_dibujos_pex', 'label' => 'Dibujos 2D (PDF) Ensamble y/o PEX', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Disponible', 'No Disponible', 'No aplica']],
                ['clave' => 'pre_muestras_fisicas', 'label' => 'Muestras Físicas', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Disponible', 'No disponible', 'No aplica']],
                ['clave' => 'pre_normas_adicionales', 'label' => 'Normas Adicionales (cuando aplique)', 'tipo' => 'text', 'peso' => 0.07, 'requerido' => true],
            ],
        ],

        // ── VOLUMEN (40%) ─────────────────────────────────────────
        [
            'id' => 'volumen',
            'titulo' => 'Volumen',
            'icono' => '📊',
            'campos' => [
                ['clave' => 'vol_precio_objetivo', 'label' => 'Precio Objetivo / Target Price (USD)', 'tipo' => 'number', 'peso' => 0, 'requerido' => false],
                ['clave' => 'vol_eau', 'label' => 'EAU (Estimated Annual Usage)', 'tipo' => 'number', 'peso' => 0.15, 'requerido' => true],
                ['clave' => 'vol_piezas_dia', 'label' => 'Piezas por Día', 'tipo' => 'number', 'peso' => 0, 'requerido' => false],
                ['clave' => 'vol_piezas_mes', 'label' => 'Piezas por Mes', 'tipo' => 'number', 'peso' => 0, 'requerido' => false],
                ['clave' => 'vol_moq', 'label' => 'MOQ (Mínima Orden)', 'tipo' => 'number', 'peso' => 0, 'requerido' => false],
                ['clave' => 'vol_freq', 'label' => 'FREQ (Frecuencia de Envío)', 'tipo' => 'text', 'peso' => 0.05, 'requerido' => true],
                ['clave' => 'vol_sop', 'label' => 'SOP - Start of Production', 'tipo' => 'date', 'peso' => 0, 'requerido' => false],
                ['clave' => 'vol_eop', 'label' => 'EOP - End of Production', 'tipo' => 'date', 'peso' => 0, 'requerido' => false],
                ['clave' => 'vol_duracion', 'label' => 'Duración del Proyecto', 'tipo' => 'text', 'peso' => 0.05, 'requerido' => true],
                ['clave' => 'vol_facturacion_anual', 'label' => 'Monto de Facturación Estimada Anual', 'tipo' => 'number', 'peso' => 0.10, 'requerido' => true],
                ['clave' => 'vol_capacidad_planta', 'label' => 'Capacidad de Planta', 'tipo' => 'text', 'peso' => 0.05, 'requerido' => true],
            ],
        ],

        // ── GENERALES Y PROCESOS EXTERNOS (4%) ───────────────────
        [
            'id' => 'generales',
            'titulo' => 'Generales y Procesos Externos',
            'icono' => '⚙️',
            'campos' => [
                ['clave' => 'gen_peso', 'label' => 'Peso (Weight) KG', 'tipo' => 'number', 'peso' => 0.02, 'requerido' => true],
                ['clave' => 'gen_dimensiones', 'label' => 'Dimensiones Generales', 'tipo' => 'text', 'peso' => 0.02, 'requerido' => true],
                ['clave' => 'gen_pintura', 'label' => 'Pintura / Anodizados', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
                ['clave' => 'gen_anticorrosivo', 'label' => 'Anticorrosivo', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
                ['clave' => 'gen_recubrimientos', 'label' => 'Recubrimientos', 'tipo' => 'text', 'peso' => 0, 'requerido' => false],
                ['clave' => 'gen_tratamiento_termico', 'label' => 'Tratamiento Térmico', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
                ['clave' => 'gen_erosionado', 'label' => 'Erosionado', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
                ['clave' => 'gen_acabado', 'label' => 'Acabado Superficial', 'tipo' => 'text', 'peso' => 0, 'requerido' => false],
                ['clave' => 'gen_pavonado', 'label' => 'Pavonado / Fosfatado', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
                ['clave' => 'gen_otros', 'label' => 'Otros', 'tipo' => 'textarea', 'peso' => 0, 'requerido' => false],
            ],
        ],

        // ── LOGÍSTICOS, EMBALAJE Y ESTÁNDAR PACK (4%) ────────────
        [
            'id' => 'logistica',
            'titulo' => 'Logísticos, Embalaje y Estándar Pack',
            'icono' => '🚚',
            'campos' => [
                ['clave' => 'log_identificacion', 'label' => 'Identificación / Grabado', 'tipo' => 'text', 'peso' => 0, 'requerido' => false],
                ['clave' => 'log_trazabilidad', 'label' => 'Trazabilidad', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
                ['clave' => 'log_destino', 'label' => 'Destino', 'tipo' => 'text', 'peso' => 0.02, 'requerido' => true],
                ['clave' => 'log_terminos_envio', 'label' => 'Términos de Envío', 'tipo' => 'select', 'peso' => 0.02, 'requerido' => true, 'opciones' => ['', 'EXW', 'FOB', 'CIF', 'DDP', 'DAP', 'FCA', 'CPT']],
                ['clave' => 'log_empaque_ind', 'label' => 'Empaque Individual (Hule Burbuja/Bolsa)', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
                ['clave' => 'log_granel', 'label' => 'Granel (Bolsa o Caja)', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
                ['clave' => 'log_caja_piezas', 'label' => 'Caja (Cantidad de Piezas)', 'tipo' => 'number', 'peso' => 0, 'requerido' => false],
                ['clave' => 'log_tarima', 'label' => 'Tarima', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
            ],
        ],

        // ── DOCUMENTACIÓN Y LIBERACIÓN (7%) ──────────────────────
        [
            'id' => 'documentacion',
            'titulo' => 'Documentación y Liberación',
            'icono' => '📄',
            'campos' => [
                ['clave' => 'doc_ppap', 'label' => 'PPAP', 'tipo' => 'select', 'peso' => 0.02, 'requerido' => true, 'opciones' => ['', 'Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4', 'Nivel 5', 'No aplica']],
                ['clave' => 'doc_spap', 'label' => 'SPAP', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
                ['clave' => 'doc_basica', 'label' => 'Básica', 'tipo' => 'select', 'peso' => 0.02, 'requerido' => true, 'opciones' => ['', 'Sí', 'No', 'No aplica']],
                ['clave' => 'doc_muestras_lib', 'label' => 'Muestras para Liberación', 'tipo' => 'number', 'peso' => 0, 'requerido' => false],
                ['clave' => 'doc_tiempo_liberacion', 'label' => 'Tiempo de Liberación del Proyecto/Muestras', 'tipo' => 'text', 'peso' => 0.03, 'requerido' => true],
            ],
        ],

        // ── PROCESOS DE MANUFACTURA (sin peso, informativos) ─────
        [
            'id' => 'manufactura',
            'titulo' => 'Procesos de Manufactura (Tiempos e Inversión)',
            'icono' => '🏭',
            'campos' => [
                ['clave' => 'mfg_corte', 'label' => 'Corte', 'tipo' => 'checkbox_single', 'peso' => 0, 'requerido' => false],
                ['clave' => 'mfg_torneado', 'label' => 'Torneado', 'tipo' => 'checkbox_single', 'peso' => 0, 'requerido' => false],
                ['clave' => 'mfg_fresados', 'label' => 'Fresados', 'tipo' => 'checkbox_single', 'peso' => 0, 'requerido' => false],
                ['clave' => 'mfg_soldadura', 'label' => 'Soldadura', 'tipo' => 'checkbox_single', 'peso' => 0, 'requerido' => false],
                ['clave' => 'mfg_doblez', 'label' => 'Doblez', 'tipo' => 'checkbox_single', 'peso' => 0, 'requerido' => false],
                ['clave' => 'mfg_punzonado', 'label' => 'Punzonado', 'tipo' => 'checkbox_single', 'peso' => 0, 'requerido' => false],
                ['clave' => 'mfg_medicion', 'label' => 'Medición', 'tipo' => 'checkbox_single', 'peso' => 0, 'requerido' => false],
                ['clave' => 'mfg_preensamble', 'label' => 'Pre Ensamble y Ensamble Final', 'tipo' => 'checkbox_single', 'peso' => 0, 'requerido' => false],
                ['clave' => 'mfg_pruebas', 'label' => 'Pruebas', 'tipo' => 'checkbox_single', 'peso' => 0, 'requerido' => false],
                ['clave' => 'mfg_req_inversion', 'label' => '¿Requiere Inversión en Dispositivos/Htas/Tecnologías?', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No']],
                ['clave' => 'mfg_tec_suficiente', 'label' => '¿Tecnología Suficiente para Manufactura?', 'tipo' => 'select', 'peso' => 0, 'requerido' => false, 'opciones' => ['', 'Sí', 'No', 'Parcial']],
                ['clave' => 'mfg_monto_inversion', 'label' => 'Monto de Inversión / Financiamiento', 'tipo' => 'number', 'peso' => 0, 'requerido' => false],
            ],
        ],
    ];
}
