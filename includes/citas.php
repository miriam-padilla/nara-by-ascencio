<?php
// Lunes a viernes: 9-13 y 15-19. Sábado: 9-13. Domingo cerrado.
function horasDelDia(string $fecha): array
{
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d) return [];
    $n = (int)$d->format('N');
    if ($n === 7) return [];
    $manana = ['09:00','10:00','11:00','12:00'];
    return $n === 6 ? $manana : array_merge($manana, ['15:00','16:00','17:00','18:00']);
}

function fechaValida(string $fecha): ?string
{
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) return 'Fecha inválida.';
    if ((int)$d->format('N') === 7) return 'No se atiende los domingos.';
    if ($fecha < date('Y-m-d')) return 'No se puede elegir una fecha pasada.';
    return null;
}

function horaValida(string $fecha, string $hora): ?string
{
    if (!in_array($hora, horasDelDia($fecha), true)) return 'Horario inválido.';
    if ($fecha === date('Y-m-d') && $hora <= date('H:i')) return 'Ese horario ya pasó.';
    return null;
}

// [hora => true si está libre]. Ocupado = cita programada o bloqueo de la administradora.
function estadoHorarios(PDO $pdo, string $fecha): array
{
    $st = $pdo->prepare("SELECT TIME_FORMAT(hora,'%H:%i') FROM agenda WHERE fecha = ? AND estado IN ('programada','bloqueada')");
    $st->execute([$fecha]);
    $ocupadas = $st->fetchAll(PDO::FETCH_COLUMN);
    $r = [];
    foreach (horasDelDia($fecha) as $h) {
        $r[$h] = !in_array($h, $ocupadas, true) && horaValida($fecha, $h) === null;
    }
    return $r;
}

function horariosLibres(PDO $pdo, string $fecha): array
{
    return array_keys(array_filter(estadoHorarios($pdo, $fecha)));
}

function errorCita(PDOException $e): array
{
    $c = (int)($e->errorInfo[1] ?? 0);
    if ($c === 1062) return [false, 'Ese horario acaba de ser ocupado. Elige otro.'];
    if ($c === 1213 || $c === 1205) return [false, 'El sistema está ocupado, intenta de nuevo.'];
    return [false, 'No se pudo completar la operación.'];
}

/* TRANSACCIÓN 1: agendar */
function agendarCita(PDO $pdo, int $pacienteId, int $servicioId, string $fecha, string $hora): array
{
    if ($m = fechaValida($fecha)) return [false, $m];
    if ($m = horaValida($fecha, $hora)) return [false, $m];
    if ($servicioId <= 0) return [false, 'Elige un servicio.'];

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT id FROM agenda WHERE fecha = ? AND hora = ? AND estado IN ('programada','bloqueada') FOR UPDATE");
        $st->execute([$fecha, $hora . ':00']);
        if ($st->fetch()) { $pdo->rollBack(); return [false, 'Ese horario ya está ocupado.']; }

        $pdo->prepare("INSERT INTO agenda (paciente_id, servicio_id, fecha, hora) VALUES (?, ?, ?, ?)")
            ->execute([$pacienteId, $servicioId, $fecha, $hora . ':00']);
        registrarBitacora($pdo, 'agendar', 'agenda', "Cita $fecha $hora (id " . $pdo->lastInsertId() . ")");
        $pdo->commit();
        return [true, 'Cita agendada correctamente.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return errorCita($e);
    }
}

/* TRANSACCIÓN 2: reagendar */
function reagendarCita(PDO $pdo, int $citaId, int $pacienteId, string $fecha, string $hora): array
{
    if ($m = fechaValida($fecha)) return [false, $m];
    if ($m = horaValida($fecha, $hora)) return [false, $m];

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT id FROM agenda WHERE id = ? AND paciente_id = ? AND estado = 'programada' FOR UPDATE");
        $st->execute([$citaId, $pacienteId]);
        if (!$st->fetch()) { $pdo->rollBack(); return [false, 'La cita no existe o no se puede modificar.']; }

        $st = $pdo->prepare("SELECT id FROM agenda WHERE fecha = ? AND hora = ? AND estado IN ('programada','bloqueada') AND id <> ? FOR UPDATE");
        $st->execute([$fecha, $hora . ':00', $citaId]);
        if ($st->fetch()) { $pdo->rollBack(); return [false, 'Ese horario ya está ocupado.']; }

        $pdo->prepare("UPDATE agenda SET fecha = ?, hora = ? WHERE id = ?")->execute([$fecha, $hora . ':00', $citaId]);
        registrarBitacora($pdo, 'reagendar', 'agenda', "Cita $citaId movida a $fecha $hora");
        $pdo->commit();
        return [true, 'Cita reagendada correctamente.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return errorCita($e);
    }
}

function cancelarCita(PDO $pdo, int $citaId, int $pacienteId): array
{
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT id FROM agenda WHERE id = ? AND paciente_id = ? AND estado = 'programada' FOR UPDATE");
        $st->execute([$citaId, $pacienteId]);
        if (!$st->fetch()) { $pdo->rollBack(); return [false, 'No se puede cancelar esa cita.']; }

        $pdo->prepare("UPDATE agenda SET estado = 'cancelada' WHERE id = ?")->execute([$citaId]);
        registrarBitacora($pdo, 'cancelar', 'agenda', "Cita $citaId cancelada");
        $pdo->commit();
        return [true, 'Cita cancelada. El horario quedó libre.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return errorCita($e);
    }
}

/* ---------- Calendario: bloquear / desbloquear ---------- */
function bloquearHora(PDO $pdo, string $fecha, string $hora): array
{
    if ($fecha < date('Y-m-d')) return [false, 'No se puede modificar una fecha pasada.'];
    if (!in_array($hora, horasDelDia($fecha), true)) return [false, 'Horario inválido.'];
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT id FROM agenda WHERE fecha = ? AND hora = ? AND estado IN ('programada','bloqueada') FOR UPDATE");
        $st->execute([$fecha, $hora . ':00']);
        if ($st->fetch()) { $pdo->rollBack(); return [false, 'Ese horario ya está ocupado o bloqueado.']; }
        $pdo->prepare("INSERT INTO agenda (fecha, hora, estado) VALUES (?, ?, 'bloqueada')")->execute([$fecha, $hora . ':00']);
        registrarBitacora($pdo, 'bloquear', 'agenda', "Horario bloqueado $fecha $hora");
        $pdo->commit();
        return [true, 'Horario bloqueado.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return errorCita($e);
    }
}

function desbloquearHora(PDO $pdo, string $fecha, string $hora): array
{
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("UPDATE agenda SET estado = 'cancelada' WHERE fecha = ? AND hora = ? AND estado = 'bloqueada'");
        $st->execute([$fecha, $hora . ':00']);
        if ($st->rowCount() === 0) { $pdo->rollBack(); return [false, 'Ese horario no estaba bloqueado.']; }
        registrarBitacora($pdo, 'desbloquear', 'agenda', "Horario liberado $fecha $hora");
        $pdo->commit();
        return [true, 'Horario liberado.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return errorCita($e);
    }
}

function bloquearDia(PDO $pdo, string $fecha): array
{
    if ($fecha < date('Y-m-d')) return [false, 'No se puede modificar una fecha pasada.'];
    $horas = horasDelDia($fecha);
    if (!$horas) return [false, 'Ese día no hay atención.'];
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT TIME_FORMAT(hora,'%H:%i') FROM agenda WHERE fecha = ? AND estado IN ('programada','bloqueada') FOR UPDATE");
        $st->execute([$fecha]);
        $ocupadas = $st->fetchAll(PDO::FETCH_COLUMN);
        $nuevos = 0; $conCita = 0;
        $ins = $pdo->prepare("INSERT INTO agenda (fecha, hora, estado) VALUES (?, ?, 'bloqueada')");
        foreach ($horas as $h) {
            if (in_array($h, $ocupadas, true)) { $conCita++; continue; }
            $ins->execute([$fecha, $h . ':00']);
            $nuevos++;
        }
        registrarBitacora($pdo, 'bloquear_dia', 'agenda', "Día bloqueado $fecha ($nuevos horarios)");
        $pdo->commit();
        $txt = "Día bloqueado ($nuevos horarios).";
        if ($conCita > 0) $txt .= " Las horas que ya tenían cita o bloqueo se respetaron.";
        return [true, $txt];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return errorCita($e);
    }
}

function desbloquearDia(PDO $pdo, string $fecha): array
{
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("UPDATE agenda SET estado = 'cancelada' WHERE fecha = ? AND estado = 'bloqueada'");
        $st->execute([$fecha]);
        if ($st->rowCount() === 0) { $pdo->rollBack(); return [false, 'Ese día no tenía bloqueos.']; }
        registrarBitacora($pdo, 'desbloquear_dia', 'agenda', "Día liberado $fecha");
        $pdo->commit();
        return [true, 'Día liberado.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return errorCita($e);
    }
}
