<?php
// Información de cada servicio para la página de detalle y el carrusel.
// Para cambiar un texto, edita este archivo (no hace falta tocar la base de datos).

function sinAcentos(string $t): string
{
    $t = mb_strtolower($t);
    return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
}

// Foto del servicio: 1) img/servicioID.jpg  2) foto por palabra clave  3) ilustración de su categoría
function fotoServicio(array $s): string
{
    $dir = __DIR__ . '/../public/img/';
    $id = (int)$s['id'];
    if (!empty($s['imagen']) && is_file($dir . 'catalogo/' . $s['imagen'])) return 'img/catalogo/' . $s['imagen'];
    $n = sinAcentos($s['nombre']);
    $claves = ['emsculpt' => 'emsculpt.jpg', 'hifu' => 'hifu.jpg', 'hi-fu' => 'hifu.jpg', 'pdr' => 'pdr.jpg',
               'laser' => 'laser.jpg', 'depila' => 'laser.jpg', 'clorofila' => 'clorofila.jpg', 'salufila' => 'clorofila.jpg', 'primera consulta' => 'consulta-nutricional.jpg', 'consulta nutricional' => 'consulta-nutricional.jpg', 'plan alimenticio' => 'consulta.jpg', 'seguimiento' => 'seguimiento.jpg'];
    foreach ($claves as $k => $archivo) {
        if (!str_contains($n, $k)) continue;
        foreach ([$archivo, preg_replace('/\.jpg$/', '.png', $archivo), preg_replace('/\.jpg$/', '.webp', $archivo)] as $c) {
            if (is_file($dir . $c)) return "img/$c";
        }
    }
    if (is_file($dir . "servicio$id.jpg")) return "img/servicio$id.jpg";
    foreach (['jpg', 'png', 'webp'] as $x) { if (is_file($dir . $s['categoria'] . ".$x")) return 'img/' . $s['categoria'] . ".$x"; }
    return 'img/' . $s['categoria'] . '.svg';
}

function infoServicio(array $s): array
{
    $n = sinAcentos($s['nombre']);
    $has = fn(string ...$k) => (bool)array_filter($k, fn($x) => str_contains($n, $x));
    $F = [
        'mnt'    => ['Academy of Nutrition and Dietetics: What is medical nutrition therapy?', 'https://www.eatright.org/health/wellness/healthful-habits/what-is-medical-nutrition-therapy'],
        'bia'    => ['NIH / PMC: Current body composition measurement techniques', 'https://pmc.ncbi.nlm.nih.gov/articles/PMC5771660/'],
        'emsc'   => ['North Shore Dermatology Center: Emsculpt', 'https://www.northshoredermatologycenter.com/?p=2668'],
        'hifu'   => ['Cleveland Clinic: Ultherapy (ultrasonido focalizado)', 'https://my.clevelandclinic.org/cosmetic-plastic-surgery/procedures/ultherapy'],
        'pdrn'   => ['NIH / PMC: Polydeoxyribonucleotide (PDRN), revisión', 'https://pmc.ncbi.nlm.nih.gov/articles/PMC8618295/'],
        'mayo'   => ['Mayo Clinic: Laser hair removal', 'https://www.mayoclinic.org/tests-procedures/laser-hair-removal/about/pac-20394555'],
        'aad'    => ['American Academy of Dermatology: Laser hair removal FAQs', 'https://www.aad.org/public/cosmetic/hair-removal/laser-hair-removal-faqs'],
        'clorof' => ['Cleveland Clinic: ¿Tiene beneficios la clorofila líquida?', 'https://health.clevelandclinic.org/are-there-health-benefits-to-using-liquid-chlorophyll'],
    ];
    $r = null;

    if ($has('emsculpt')) $r = [
        'resumen' => 'Tratamiento corporal para tonificar músculo y reducir grasa localizada.',
        'consiste' => 'Tratamiento corporal no invasivo que usa energía electromagnética focalizada de alta intensidad (HIFEM) para provocar contracciones musculares más intensas que las del ejercicio voluntario (hasta 20 mil por sesión). Está autorizado por la FDA para abdomen y glúteos. La fuente consultada describe un protocolo de 4 sesiones de 30 minutos, dos veces por semana, con mantenimiento cada 3 a 6 meses; tu esquema lo define la especialista en la valoración.',
        'beneficios' => ['Endurece y tonifica el abdomen', 'Reduce la grasa localizada', 'Modela y esculpe la figura', 'Levanta y tonifica los glúteos',
                         'Estudios patrocinados por el fabricante reportan en promedio cerca de 19 % menos grasa, 16 % más masa muscular y 4 cm menos de cintura, medidos unos 2 meses después de 4 sesiones'],
        'para_quien' => ['Adultos en buena condición física que buscan más definición abdominal o levantar los glúteos', 'Personas activas que ya cuidan su alimentación y quieren más definición'],
        'precauciones' => ['No es lo ideal si hay mucha grasa acumulada: la energía llega a unos 7 cm de profundidad', 'No se recomienda con implantes metálicos o electrónicos en la zona, marcapasos o desfibrilador, tumores malignos, epilepsia, trastornos de sangrado, cirugía reciente, embarazo o lactancia'],
        'fuentes' => [$F['emsc']],
    ];
    elseif ($has('hifu', 'hi-fu')) $r = [
        'resumen' => 'Tratamiento facial de lifting para rostro, cuello y escote.',
        'consiste' => 'Tratamiento facial no quirúrgico que usa ultrasonido focalizado (HIFU). Un aplicador manual envía energía a capas profundas de la piel; el calor estimula al cuerpo a producir colágeno nuevo y la piel se va firmando poco a poco durante varios meses.',
        'beneficios' => ['Cejas y párpado superior: lifting', 'Contorno de ojos: disminución de ojeras', 'Pómulos: lifting', 'Mejillas: reducción de pliegues naso-labiales',
                         'Parte inferior de la cara: adelgazamiento, lifting y reducción de pliegues', 'Cuello: lifting', 'Escote: líneas perfectas, lifting y reducción de arrugas',
                         'Por lo general no requiere tiempo de recuperación y puedes maquillarte al terminar', 'Alternativa menos invasiva que una cirugía de rostro'],
        'para_quien' => ['Adultos de 30 años o más con flacidez leve a moderada en rostro y cuello', 'Algunas personas más jóvenes, como prevención', 'Quienes quieren prolongar los resultados de una cirugía estética', 'Quienes aún no están listos para operarse'],
        'precauciones' => ['No reemplaza un lifting quirúrgico cuando se necesita un levantamiento importante', 'La valoración con la especialista determina si eres buena candidata'],
        'fuentes' => [$F['hifu']],
    ];
    elseif ($has('pdr')) $r = [
        'resumen' => 'Tratamiento facial regenerador con respaldo médico.',
        'consiste' => 'Tratamiento facial con PDRN (polidesoxirribonucleótido), una sustancia derivada del ADN de salmón (trucha salmón o salmón keta) con más de 95 % de pureza. Según la revisión científica consultada, actúa sobre receptores de adenosina A2A: reduce la inflamación, favorece la formación de vasos sanguíneos y estimula la actividad de los fibroblastos, las células que producen colágeno. Se aplica por personal profesional, con respaldo médico.',
        'beneficios' => ['Estimula la producción de colágeno', 'Trata las cicatrices de acné', 'Elimina signos de la edad', 'Rejuvenece la piel',
                         'En estudios de heridas se observó mayor cierre y mejor cicatrización, con más colágeno y vasos sanguíneos y menos inflamación'],
        'para_quien' => ['Personas con cicatrices de acné', 'Personas que buscan mejorar la calidad de la piel y los signos del paso del tiempo'],
        'precauciones' => ['La mayor parte de la evidencia proviene de estudios de laboratorio, en animales o clínicos pequeños; los datos de seguridad son limitados', 'Avisa a la especialista si tienes alergias o enfermedades de la piel'],
        'fuentes' => [$F['pdrn']],
    ];
    elseif ($has('laser', 'depila')) $r = [
        'resumen' => 'Depilación láser con tecnología de tri diodo.',
        'consiste' => 'Depilación con láser (máquina tri diodo Soprano). El láser se dirige a la melanina, el pigmento del vello; la luz se convierte en calor, daña el folículo y frena el crecimiento. El vello tratado se cae en los días o semanas siguientes. La mayoría de las personas necesita entre 2 y 8 sesiones, separadas por varias semanas, y a veces sesiones de mantenimiento.',
        'beneficios' => ['Reduce el vello no deseado en piernas, axilas, labio superior, mentón y línea del bikini', 'El vello que vuelve a salir suele ser más fino y claro',
                         'Tecnología nueva en el consultorio'],
        'para_quien' => ['Funciona mejor en piel clara con vello oscuro, donde hay más contraste', 'Las personas de piel morena o negra pueden tratarse si la profesional elige el láser y los parámetros adecuados', 'Con vello rubio, blanco, gris o pelirrojo la respuesta es menor'],
        'precauciones' => ['No se aplica cerca de los ojos ni sobre piel tatuada', 'Evita el sol y las camas de bronceado antes y después del tratamiento', 'Es común sentir enrojecimiento e hinchazón de 1 a 3 días; rara vez hay ampollas, cicatrices o cambios de color en la piel', 'Reduce el vello pero no trata la causa de un exceso hormonal'],
        'fuentes' => [$F['mayo'], $F['aad']],
    ];
    elseif ($has('seguimiento')) $r = [
        'resumen' => 'Revisión de tu avance y ajustes a tu plan.',
        'consiste' => 'Consulta para revisar cómo vas: peso, medidas y cumplimiento de tu plan. En la terapia nutricional las visitas posteriores sirven para dar seguimiento al progreso y ajustar las metas y el plan según sea necesario.',
        'beneficios' => ['Registro de tu evolución', 'Ajustes personalizados a tu plan', 'Resolución de dudas', 'Seguimiento constante de tus resultados'],
        'para_quien' => ['Pacientes que ya tuvieron su primera consulta y tienen un plan alimenticio', 'Quienes necesitan ajustar su plan por cambios en su peso, rutina o salud'],
        'precauciones' => [],
        'fuentes' => [$F['mnt']],
    ];
    elseif ($has('plan')) $r = [
        'resumen' => 'Plan de alimentación hecho a tu medida.',
        'consiste' => 'Plan alimenticio personalizado según tu valoración, tus objetivos y tus condiciones de salud. La terapia nutricional se basa en establecer metas contigo y construir un plan individual, adaptado a tu cultura y a tu estilo de vida.',
        'beneficios' => ['Diseñado para ti', 'Se adapta a tu estilo de vida', 'Disponible en tu cuenta para consultarlo cuando quieras'],
        'para_quien' => ['Quien quiere bajar, subir o mantener su peso con una guía clara', 'Personas con condiciones como diabetes, enfermedades del corazón o alergias alimentarias que requieren cuidar su alimentación'],
        'precauciones' => ['Si tienes una enfermedad, consulta también a tu médico'],
        'fuentes' => [$F['mnt']],
    ];
    elseif ($has('consulta')) $r = [
        'resumen' => 'Valoración nutricional completa con análisis por bioimpedancia.',
        'consiste' => 'Consulta con valoración completa: se revisan tus hábitos de alimentación y actividad, tu historia de salud y tus medidas, y se hace un análisis de composición corporal por bioimpedancia. La bioimpedancia mide el paso de una corriente eléctrica muy leve por el cuerpo para estimar el agua corporal total y la masa libre de grasa; es rápida y sencilla. Con esa información se diseña un plan de alimentación individual, como en la terapia médica nutricional.',
        'beneficios' => ['Historial clínico', 'Peso y talla', 'Porcentaje de agua, músculo y grasa', 'Edad metabólica', 'Nivel de grasa visceral', 'Contenido de sodio', 'Suplementación', 'Carpeta personalizada', 'Plan alimenticio personalizado'],
        'para_quien' => ['Cualquier persona que quiera mejorar su alimentación', 'Personas con diabetes, enfermedades del corazón, alergias alimentarias o enfermedad renal, entre otras condiciones', 'Quien inicia por primera vez: esta consulta es el punto de partida'],
        'precauciones' => ['La bioimpedancia supone un nivel de hidratación constante; si cambia (por ejemplo en el embarazo), la estimación es menos exacta, y no se considera todavía un método de referencia', 'Si tienes una enfermedad, coordina tu plan con tu médico'],
        'fuentes' => [$F['mnt'], $F['bia']],
    ];
    elseif ($has('clorofila', 'salufila')) $r = [
        'resumen' => 'Suplemento de clorofila líquida.',
        'consiste' => 'La clorofila es el pigmento verde de las plantas. Las gotas de clorofila líquida suelen contener clorofilina, una mezcla semisintética de sales de cobre y sodio derivada de la clorofila.',
        'beneficios' => ['Se han comprobado propiedades antioxidantes y antiinflamatorias', 'Estudios pequeños sugieren efectos en la piel y en la cicatrización (uso tópico)', 'Hay investigación preliminar sobre salud intestinal; se necesitan estudios más grandes'],
        'para_quien' => ['Personas que ya cuidan su alimentación y quieren complementarla con un suplemento, con orientación de la nutrióloga'],
        'precauciones' => ['No es una cura universal: varios beneficios populares tienen poca evidencia', 'Puede causar malestar estomacal, heces oscuras y mayor sensibilidad al sol', 'Las verduras verdes (espinaca, col rizada, chícharos) aportan clorofila junto con fibra y otros nutrientes'],
        'fuentes' => [$F['clorof']],
    ];
    if ($r === null) {
        $porCategoria = [
            'corporal' => 'Tratamiento corporal realizado por personal profesional.',
            'facial'   => 'Tratamiento facial realizado por personal profesional.',
            'nutricion' => 'Atención nutricional personalizada.',
        ];
        $t = $porCategoria[$s['categoria']] ?? 'Servicio del consultorio.';
        $r = ['resumen' => $t, 'consiste' => $t . ' Pregunta por los detalles en tu cita o por WhatsApp.', 'beneficios' => [], 'para_quien' => [], 'precauciones' => [], 'fuentes' => []];
    }
    if (!empty($s['descripcion'])) {   // la descripción capturada por la administradora tiene prioridad
        $d = trim($s['descripcion']);
        $r['resumen'] = mb_strlen($d) > 140 ? mb_substr($d, 0, 137) . '…' : $d;
        $r['consiste'] = $d;
    }
    return $r;
}

function textoCosto(array $s): string
{
    return (float)$s['costo'] > 0 ? '$' . number_format((float)$s['costo'], 0) : 'Informes vía WhatsApp';
}
