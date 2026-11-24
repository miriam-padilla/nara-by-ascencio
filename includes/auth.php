<?php
session_start();
date_default_timezone_set('America/Mazatlan');

function e(?string $t): string
{
    return htmlspecialchars($t ?? '', ENT_QUOTES, 'UTF-8');
}

function requerirRol(array $roles): void
{
    if (empty($_SESSION['rol']) || !in_array($_SESSION['rol'], $roles, true)) {
        header('Location: login.php');
        exit;
    }
}

function registrarBitacora(PDO $pdo, string $accion, ?string $tabla, ?string $detalle, ?int $usuarioId = null): void
{
    $st = $pdo->prepare("INSERT INTO bitacora (usuario_id, accion, tabla_afectada, detalle) VALUES (?, ?, ?, ?)");
    $st->execute([$usuarioId ?? ($_SESSION['usuario_id'] ?? null), $accion, $tabla, $detalle]);
}

function pacienteIdActual(PDO $pdo): int
{
    $st = $pdo->prepare("SELECT id FROM paciente WHERE usuario_id = ?");
    $st->execute([$_SESSION['usuario_id']]);
    return (int)$st->fetchColumn();
}

function avisoFlash(): void
{
    if (!empty($_SESSION['flash'])) {
        echo '<p class="alerta ok">' . e($_SESSION['flash']) . '</p>';
        unset($_SESSION['flash']);
    }
    if (!empty($_SESSION['flash_error'])) {
        echo '<p class="alerta error">' . e($_SESSION['flash_error']) . '</p>';
        unset($_SESSION['flash_error']);
    }
}

const NIVELES = ['ninguno' => 0, 'lectura' => 1, 'editar' => 2];
const ETIQUETAS_NIVEL = ['ninguno' => 'Acceso restringido', 'lectura' => 'Solo lectura', 'editar' => 'Lectura y edición'];

// Funciones de la página: clave => [nombre, tipos de usuario a los que aplica]
// Tipos de usuario: visitante (sin cuenta), paciente, usuario (personal), administrador (siempre todo)
function seccionesPermisos(): array
{
    return [
        'citas'         => ['Citas', ['usuario']],
        'pacientes'     => ['Pacientes', ['usuario']],
        'seguimiento'   => ['Seguimiento', ['usuario']],
        'historial'     => ['Historial clínico', ['usuario']],
        'movimientos'   => ['Movimientos', ['usuario']],
        'productos'     => ['Agregar producto o servicio', ['usuario']],
        'promociones'   => ['Promociones', ['usuario']],
        'calendario'    => ['Calendario', ['usuario']],
        'ver_servicios' => ['Ver servicios y promociones', ['visitante', 'paciente']],
        'registro'      => ['Crear cuenta (Registrarme)', ['visitante']],
        'mis_datos'     => ['Mis datos', ['paciente']],
        'mi_historial'  => ['Historial clínico (del paciente)', ['paciente']],
        'mi_plan'       => ['Seguimiento: plan alimenticio', ['paciente']],
        'mis_citas'     => ['Agendar, modificar y cancelar citas', ['paciente']],
    ];
}

function permisosPorDefecto(): array
{
    return [
        'citas' => ['usuario' => 'editar'], 'pacientes' => ['usuario' => 'editar'], 'seguimiento' => ['usuario' => 'editar'],
        'historial' => ['usuario' => 'lectura'], 'movimientos' => ['usuario' => 'ninguno'], 'productos' => ['usuario' => 'lectura'],
        'promociones' => ['usuario' => 'lectura'], 'calendario' => ['usuario' => 'editar'],
        'ver_servicios' => ['visitante' => 'lectura', 'paciente' => 'lectura'], 'registro' => ['visitante' => 'editar'],
        'mis_datos' => ['paciente' => 'lectura'], 'mi_historial' => ['paciente' => 'lectura'],
        'mi_plan' => ['paciente' => 'lectura'], 'mis_citas' => ['paciente' => 'editar'],
    ];
}

// La administradora guarda la tabla de permisos (función x tipo de usuario) en su propio registro de usuarios.
function matrizPermisos(?PDO $pdo = null): array
{
    static $m = null;
    if ($m !== null) return $m;
    $m = permisosPorDefecto();
    try {
        if (!$pdo) { require_once __DIR__ . '/../config/conexion.php'; $pdo = conectarRol('publico'); }
        $json = $pdo->query("SELECT permisos FROM usuarios WHERE rol = 'administrador' ORDER BY id LIMIT 1")->fetchColumn();
        $guardada = json_decode((string)$json, true);
        if (is_array($guardada)) {
            foreach ($guardada as $f => $roles) {
                if (!isset($m[$f]) || !is_array($roles)) continue;
                foreach ($roles as $r => $n) {
                    if (isset(NIVELES[$n]) && array_key_exists($r, $m[$f])) $m[$f][$r] = $n;
                }
            }
        }
    } catch (Throwable $e) { /* se usan los permisos por defecto */ }
    return $m;
}

function tipoUsuarioSesion(): string
{
    return match ($_SESSION['rol'] ?? '') {
        'administrador' => 'administrador', 'trabajador' => 'usuario', 'paciente' => 'paciente', default => 'visitante',
    };
}

// Nivel que tiene el tipo de usuario actual en una función
function nivelRol(string $clave): string
{
    $tipo = tipoUsuarioSesion();
    if ($tipo === 'administrador') return 'editar';
    return matrizPermisos()[$clave][$tipo] ?? 'ninguno';
}

function nivelPermiso(PDO $pdo, string $seccion): string
{
    if (($_SESSION['rol'] ?? '') === 'administrador') return 'editar';
    if (($_SESSION['rol'] ?? '') !== 'trabajador') return 'ninguno';
    return matrizPermisos($pdo)[$seccion]['usuario'] ?? 'ninguno';
}

function requerirSeccion(PDO $pdo, string $seccion, string $minimo = 'lectura'): string
{
    requerirRol(['administrador', 'trabajador']);
    $n = nivelPermiso($pdo, $seccion);
    if (NIVELES[$n] < NIVELES[$minimo]) {
        $_SESSION['flash_error'] = 'No tienes permiso para acceder a esa sección.';
        header('Location: panel.php');
        exit;
    }
    return $n;
}

// Para pacientes: exige un nivel mínimo en una función (por ejemplo 'mis_datos')
function requerirNivelRol(string $clave, string $minimo = 'lectura'): string
{
    $n = nivelRol($clave);
    if (NIVELES[$n] < NIVELES[$minimo]) {
        $_SESSION['flash_error'] = 'No tienes permiso para acceder a esa sección.';
        header('Location: panel.php');
        exit;
    }
    return $n;
}

// ¿El paciente tiene una actualización nueva de su nutrióloga?
function hayAvisoSeguimiento(): bool
{
    static $r = null;
    if ($r !== null) return $r;
    $r = false;
    if (($_SESSION['rol'] ?? '') === 'paciente') {
        try {
            require_once __DIR__ . '/../config/conexion.php';
            $st = conectarRol('paciente')->prepare("SELECT aviso_seguimiento FROM paciente WHERE usuario_id = ?");
            $st->execute([$_SESSION['usuario_id']]);
            $r = (int)$st->fetchColumn() === 1;
        } catch (Throwable $e) { $r = false; }
    }
    return $r;
}

// Nombre legible de las acciones de la bitácora
function etiquetaAccion(string $a): string
{
    return [
        'login' => 'Inicio de sesión', 'logout' => 'Cierre de sesión', 'login_fallido' => 'Intento de acceso fallido',
        'acceso_sin_password' => 'Intento de acceso sin contraseña', 'registro' => 'Nuevo registro', 'crear' => 'Alta de registro',
        'editar' => 'Edición', 'eliminar' => 'Eliminación', 'cancelar' => 'Cancelación de cita', 'agendar' => 'Cita agendada',
        'reagendar' => 'Cita modificada', 'bloquear' => 'Horario bloqueado', 'desbloquear' => 'Horario liberado',
        'valoracion' => 'Valoración inicial', 'seguimiento' => 'Seguimiento guardado', 'actualizar_seguimiento' => 'Aviso de actualización',
        'permisos' => 'Cambio de permisos', 'cambio_password' => 'Cambio de contraseña',
        'bloquear_dia' => 'Día bloqueado', 'desbloquear_dia' => 'Día liberado',
    ][$a] ?? $a;
}
