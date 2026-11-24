<?php
function tiposAtencion(): array
{
    return ['Reducción de peso', 'Diabetes', 'Resistencia a la insulina', 'Hipertensión', 'Dislipidemias',
            'Hipotiroidismo', 'Menopausia', 'Síndrome poliendocrino metabólico', 'Embarazo', 'Niños', 'Otro'];
}

// Tablas que se editan con el formulario genérico (admin_tabla.php)
function configTablas(): array
{
    $pacientes = "SELECT p.id, u.nombre AS etiqueta FROM paciente p JOIN usuarios u ON u.id = p.usuario_id ORDER BY u.nombre";
    return [
        'paciente' => [
            'titulo'  => 'Pacientes',
            'seccion' => 'pacientes',
            'lista'   => "SELECT p.id, u.nombre, p.edad, p.sexo, p.telefono, p.tipo_atencion, p.activo
                          FROM paciente p JOIN usuarios u ON u.id = p.usuario_id ORDER BY u.nombre",
            'campos'  => [
                'usuario_id'       => ['etiqueta' => 'Usuario', 'tipo' => 'fk', 'req' => true,
                                       'sql' => "SELECT id, CONCAT(nombre,' (',email,')') AS etiqueta FROM usuarios WHERE rol='paciente' ORDER BY nombre"],
                'edad'             => ['etiqueta' => 'Edad', 'tipo' => 'number', 'req' => false],
                'sexo'             => ['etiqueta' => 'Sexo', 'tipo' => 'enum', 'req' => false, 'opciones' => ['F', 'M', 'Otro']],
                'telefono'         => ['etiqueta' => 'Teléfono (10 dígitos)', 'tipo' => 'text', 'req' => false],
                'activo'           => ['etiqueta' => 'Activo (en seguimiento actualmente)', 'tipo' => 'enum', 'req' => true, 'opciones' => ['Si', 'No']],
                'tipo_atencion'    => ['etiqueta' => 'Tipo de atención', 'tipo' => 'enum', 'req' => false, 'opciones' => tiposAtencion()],
                'peso_inicial'     => ['etiqueta' => 'Peso inicial (kg)', 'tipo' => 'number', 'req' => false, 'step' => '0.01'],
                'talla_cm'         => ['etiqueta' => 'Talla (cm)', 'tipo' => 'number', 'req' => false, 'step' => '0.01'],
                'objetivo'         => ['etiqueta' => 'Objetivo del paciente', 'tipo' => 'text', 'req' => false],
                'alergias'         => ['etiqueta' => 'Alergias o intolerancias', 'tipo' => 'textarea', 'req' => false],
                'plan_alimenticio' => ['etiqueta' => 'Plan alimenticio', 'tipo' => 'textarea', 'req' => false],
            ],
        ],
        'servicio' => [
            'titulo'  => 'Servicios',
            'seccion' => 'productos',
            'lista'   => "SELECT id, categoria, nombre, costo FROM servicio WHERE categoria <> 'promocion' ORDER BY categoria, id",
            'campos'  => [
                'categoria'    => ['etiqueta' => 'Categoría', 'tipo' => 'enum', 'req' => true, 'opciones' => ['nutricion', 'corporal', 'facial', 'producto']],
                'nombre'       => ['etiqueta' => 'Nombre del servicio o producto', 'tipo' => 'text', 'req' => true],
                'costo'        => ['etiqueta' => 'Costo', 'tipo' => 'number', 'req' => true, 'step' => '0.01'],
                'descripcion'  => ['etiqueta' => 'Descripción (en qué consiste, beneficios)', 'tipo' => 'textarea', 'req' => false],
            ],
        ],
        // Agenda: SOLO citas activas. Canceladas y atendidas pasan al historial de citas.
        'agenda' => [
            'titulo'  => 'Agenda (citas activas)',
            'seccion' => 'citas',
            'lista'   => "SELECT a.id, a.fecha, TIME_FORMAT(a.hora,'%H:%i') AS hora, u.nombre AS paciente, s.nombre AS servicio
                          FROM agenda a JOIN paciente p ON p.id = a.paciente_id JOIN usuarios u ON u.id = p.usuario_id
                          JOIN servicio s ON s.id = a.servicio_id
                          WHERE a.estado = 'programada' ORDER BY a.fecha, a.hora",
            'campos'  => [
                'paciente_id' => ['etiqueta' => 'Paciente', 'tipo' => 'fk', 'req' => true, 'sql' => $pacientes],
                'servicio_id' => ['etiqueta' => 'Servicio', 'tipo' => 'fk', 'req' => true, 'sql' => "SELECT id, nombre AS etiqueta FROM servicio ORDER BY nombre"],
                'fecha'       => ['etiqueta' => 'Fecha', 'tipo' => 'date', 'req' => true],
                'hora'        => ['etiqueta' => 'Hora', 'tipo' => 'time', 'req' => true],
                'estado'      => ['etiqueta' => 'Estado (cancelada o atendida la mueve al historial de citas)', 'tipo' => 'enum', 'req' => true, 'opciones' => ['programada', 'cancelada', 'atendida']],
            ],
        ],
        // Seguimiento: se captura desde el Historial clínico (historial.php); no tiene lista propia.
        'seguimiento' => [
            'titulo'  => 'Seguimiento',
            'seccion' => 'seguimiento',
            'lista'   => "SELECT id FROM seguimiento",
            'campos'  => [
                'paciente_id'      => ['etiqueta' => 'Paciente', 'tipo' => 'fk', 'req' => true, 'sql' => $pacientes],
                'fecha'            => ['etiqueta' => 'Fecha', 'tipo' => 'date', 'req' => true],
                'peso'             => ['etiqueta' => 'Peso (kg)', 'tipo' => 'number', 'req' => false, 'step' => '0.01'],
                'cintura_cm'       => ['etiqueta' => 'Cintura (cm)', 'tipo' => 'number', 'req' => false, 'step' => '0.01'],
                'adherencia'       => ['etiqueta' => 'Adherencia al plan', 'tipo' => 'enum', 'req' => false, 'opciones' => ['baja', 'media', 'alta']],
                'observaciones'    => ['etiqueta' => 'Observaciones', 'tipo' => 'textarea', 'req' => false],
                'proxima_revision' => ['etiqueta' => 'Próxima revisión', 'tipo' => 'date', 'req' => false],
            ],
        ],
    ];
}
