<?php
require_once "../includes/auth.php";
require_once "../includes/layout.php";
require_once "../includes/tablas.php";
require_once "../includes/serviciosinfo.php";

$verCatalogo = nivelRol('ver_servicios') !== 'ninguno';
$servicios = $verCatalogo ? listaServicios() : [];
$promos = $verCatalogo ? listaPromociones() : [];
$atendemos = array_filter(tiposAtencion(), fn($t) => $t !== 'Otro');
$fotoHero = is_file(__DIR__ . '/img/logo-nara.jpg') ? 'img/logo-nara.jpg' : 'img/hero.svg';
$fotoNutri = fotoNutriologa();

encabezado('Inicio', false);
?>
<div class="pagina">
  <div class="contenido">

    <section class="hero">
      <div>
        <h1>Nutrición y Bienestar</h1>
        <p>Consultoría nutricional personalizada en Los Mochis. Agenda tu cita en línea y da seguimiento a tu evolución.</p>
      </div>
      <div class="caja-logo"><img src="<?= e($fotoHero) ?>" alt="NARA by Ascencio, nutrición y bienestar"></div>
    </section>

    <?php if ($promos): ?>
    <section id="promociones">
      <h2>Promociones</h2>
      <div class="tarjetas promos">
        <?php foreach ($promos as $pr): ?>
          <article class="tarjeta promo">
            <?php if ($pr['imagen'] && is_file(__DIR__ . '/img/catalogo/' . $pr['imagen'])): ?><img src="img/catalogo/<?= e($pr['imagen']) ?>" alt="<?= e($pr['nombre']) ?>"><?php endif; ?>
            <h3><?= e($pr['nombre']) ?></h3>
            <?php if ($pr['descripcion']): ?><p><?= e($pr['descripcion']) ?></p><?php endif; ?>
            <?php if ($pr['vigencia_hasta']): ?><small>Vigente hasta el <?= e($pr['vigencia_hasta']) ?></small><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section id="servicios">
      <h2>Servicios</h2>
      <?php if (!$servicios): ?>
        <p>Consulta nuestros servicios por <a href="<?= e(enlaceWhatsApp('Hola, quiero información de los servicios.')) ?>" target="_blank" rel="noopener">WhatsApp</a>.</p>
      <?php else: ?>
      <div class="carrusel" aria-roledescription="carrusel">
        <button type="button" class="flecha ant" aria-label="Servicio anterior">‹</button>
        <button type="button" class="flecha sig" aria-label="Servicio siguiente">›</button>
        <div class="pistas">
          <?php foreach ($servicios as $s): $info = infoServicio($s); ?>
            <article class="diapositiva">
              <a class="foto" href="servicio.php?id=<?= (int)$s['id'] ?>" title="Ver información de <?= e($s['nombre']) ?>"><img src="<?= e(fotoServicio($s)) ?>" alt="<?= e($s['nombre']) ?>"></a>
              <div>
                <h3><a href="servicio.php?id=<?= (int)$s['id'] ?>"><?= e($s['nombre']) ?></a></h3>
                <p><?= e($info['resumen']) ?></p>
                <a class="boton" href="servicio.php?id=<?= (int)$s['id'] ?>">Ver más</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="puntos"></div>
      </div>
      <?php endif; ?>
    </section>

    <section>
      <h2>¿Cómo funciona?</h2>
      <div class="tarjetas pasos">
        <div class="tarjeta paso"><strong>1. Regístrate</strong><br>Crea tu cuenta desde «Inicio de sesión».</div>
        <div class="tarjeta paso"><strong>2. Agenda</strong><br>Elige el día y la hora disponibles.</div>
        <div class="tarjeta paso"><strong>3. Da seguimiento</strong><br>Consulta tu historial y tu plan alimenticio.</div>
      </div>
    </section>

    <section>
      <h2>Preguntas frecuentes</h2>
      <details><summary>¿Cómo cancelo o cambio mi cita?</summary><p>Entra al menú «Cita» y elige «Cancelar o modificar cita».</p></details>
      <details><summary>¿Puedo ver mi plan alimenticio?</summary><p>Sí, en «Seguimiento», una vez que la nutrióloga lo registre.</p></details>
      <details><summary>¿Y si no puedo registrarme en línea?</summary><p>Llámanos o escríbenos por WhatsApp y te registramos en el consultorio.</p></details>
    </section>

    <section id="contacto">
      <h2>Contacto</h2>
      <div class="tarjeta">
        <p><strong>Dirección:</strong> <?= e(SITIO_DIRECCION) ?> · <a href="<?= e(enlaceMapa()) ?>" target="_blank" rel="noopener">Abrir en Google Maps</a></p>
        <p><strong>Horario:</strong> <?= e(SITIO_HORARIO_1) ?> · <?= e(SITIO_HORARIO_2) ?></p>
        <p><strong>WhatsApp:</strong>
          <a class="wa-numero" href="<?= e(enlaceWhatsApp('Hola, quiero información.')) ?>" target="_blank" rel="noopener"><?= iconoWhatsApp() ?> <?= e(SITIO_TEL_BONITO) ?></a>
        </p>
        <div class="mapa" id="mapa">
          <iframe src="<?= e(mapaIncrustado()) ?>" title="Mapa de la ubicación del consultorio" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
          <button type="button" class="mapa-activar">Haz clic para mover y acercar el mapa</button>
        </div>
      </div>
    </section>

  </div>

  <aside class="lateral">
    <div class="tarjeta perfil-nutri">
      <img class="foto-nutri" src="<?= e($fotoNutri) ?>" alt="Nutrióloga Carolina Ascencio">
      <strong>Carolina Ascencio</strong>
      Nutrióloga · Lic. en Nutrición y Cosmiatra
      <p class="pensamiento">Comer bien no es una dieta: es cuidarte todos los días. Yo te acompaño, paso a paso, para que te sientas mejor.</p>
      <hr style="border:0;border-top:1px solid var(--borde);margin:1rem 0">
      <h2>¿A quién atendemos?</h2>
      <p><?= e(implode(', ', array_map('mb_strtolower', $atendemos))) ?>.</p>
    </div>
  </aside>
</div>
<?php pie(); ?>
