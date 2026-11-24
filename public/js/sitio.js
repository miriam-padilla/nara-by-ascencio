// Menú desplegable, carrusel de servicios y ventana emergente
document.addEventListener('DOMContentLoaded', () => {
  // ----- Menú -----
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('header nav');
  if (toggle && nav) toggle.addEventListener('click', () => nav.classList.toggle('visible'));

  const drops = document.querySelectorAll('li.drop');
  function cerrarMenus(excepto) {
    drops.forEach(d => {
      if (d !== excepto) { d.classList.remove('abierto'); d.querySelector('.drop-btn').setAttribute('aria-expanded', 'false'); }
    });
  }
  drops.forEach(d => {
    const btn = d.querySelector('.drop-btn');
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const abrir = !d.classList.contains('abierto');
      cerrarMenus(d);
      d.classList.toggle('abierto', abrir);
      btn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    });
  });
  document.addEventListener('click', () => cerrarMenus(null));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarMenus(null); });

  // ----- Mapa: se activa con un clic (así no estorba al desplazar la página) -----
  document.querySelectorAll('.mapa').forEach(m => {
    const b = m.querySelector('.mapa-activar');
    if (!b) return;
    b.addEventListener('click', () => { b.hidden = true; m.classList.add('activo'); });
    m.addEventListener('mouseleave', () => { if (m.classList.contains('activo')) { m.classList.remove('activo'); b.hidden = false; } });
  });

  // ----- Cajas desplegables del panel (solo una abierta) y buscador de pacientes -----
  const cajas = document.querySelectorAll('details.caja');
  cajas.forEach(c => c.addEventListener('toggle', () => { if (c.open) cajas.forEach(o => { if (o !== c) o.open = false; }); }));
  document.querySelectorAll('.filtrar-lista').forEach(inp => inp.addEventListener('input', () => {
    const t = inp.value.toLowerCase();
    inp.parentElement.querySelectorAll('a').forEach(a => { a.style.display = a.textContent.toLowerCase().includes(t) ? '' : 'none'; });
  }));

  // ----- Carrusel -----
  document.querySelectorAll('.carrusel').forEach(c => {
    const pistas = c.querySelector('.pistas');
    const total = pistas.children.length;
    const puntos = c.querySelector('.puntos');
    let i = 0;

    for (let k = 0; k < total; k++) {
      const b = document.createElement('button');
      b.type = 'button'; b.setAttribute('aria-label', 'Ir al servicio ' + (k + 1));
      b.addEventListener('click', () => { ir(k); reiniciar(); });
      puntos.appendChild(b);
    }
    function ir(n) {
      i = (n + total) % total;
      pistas.style.transform = 'translateX(' + (-100 * i) + '%)';
      [...puntos.children].forEach((p, k) => p.classList.toggle('activo', k === i));
    }
    function reiniciar() {}  // el carrusel solo cambia al usar las flechas o los puntos
    c.querySelector('.ant').addEventListener('click', () => { ir(i - 1); reiniciar(); });
    c.querySelector('.sig').addEventListener('click', () => { ir(i + 1); reiniciar(); });
    ir(0);
    if (total < 2) c.querySelectorAll('.flecha').forEach(f => f.hidden = true);
  });

  // ----- Ventana emergente de éxito: redirige sola -----
  const modal = document.getElementById('modal-exito');
  if (modal) {
    const seg = Number(modal.dataset.segundos) || 4;
    setTimeout(() => { window.location.href = modal.dataset.destino; }, seg * 1000);
  }
});

// En "Cancelar o modificar cita": al marcar "Modificar" se abre el calendario para elegir nueva fecha y hora
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[data-modificar]').forEach(c => {
    c.addEventListener('change', () => { if (c.checked) window.location.href = 'agenda.php?reagendar=' + c.dataset.modificar; });
  });
});
