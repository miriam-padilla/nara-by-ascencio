// Asistente virtual del sitio (responde con reglas; no usa servicios de pago)
(function () {
  const cfg = document.getElementById('chat-config');
  if (!cfg) return;
  const WA = cfg.dataset.wa, TEL = cfg.dataset.tel, ROL = cfg.dataset.rol, DIR = cfg.dataset.dir;
  const wa = txt => 'https://wa.me/' + WA + '?text=' + encodeURIComponent(txt);
  const mapa = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(DIR);

  // Cada respuesta es una lista de partes: texto o {t: texto, href: enlace}
  const R = {
    inicio: ['¡Hola! Soy NARA-bot, el asistente virtual de NARA by Ascencio. ¿En qué puedo ayudarte?'],
    servicios: ['Ofrecemos consulta nutricional (primera consulta y seguimiento), plan alimenticio personalizado, tratamientos corporales y tratamientos faciales. ',
                { t: 'Ver servicios', href: 'index.php#servicios' }],
    atendidos: ['Atendemos reducción de peso, diabetes, resistencia a la insulina, hipertensión, dislipidemias, hipotiroidismo, menopausia, síndrome poliendocrino metabólico, embarazo y niños.'],
    horarios: ['Atendemos de lunes a viernes de 9:00 a 13:00 y de 15:00 a 19:00, y los sábados de 9:00 a 13:00.'],
    ubicacion: ['Estamos en ' + DIR + '. ', { t: 'Abrir en Google Maps', href: mapa }],
    contacto: ['Citas por teléfono y WhatsApp: 668 101 5028. ',
               { t: 'Llamar', href: 'tel:+52' + TEL }, ' · ',
               { t: 'Escribir por WhatsApp', href: wa('Hola, quisiera información sobre una consulta.') }],
    humano: ['Con gusto te atiende el consultorio. Escríbenos por WhatsApp y te responderán en horario de atención. ',
             { t: 'Abrir WhatsApp', href: wa('Hola, necesito más información específica.') }],
    salud: ['No puedo dar diagnósticos ni indicaciones médicas o alimenticias por este medio. Lo mejor es agendar una consulta para una valoración personalizada. ',
            { t: 'Hablar con el consultorio', href: wa('Hola, quisiera agendar una consulta.') }],
    gracias: ['¡Con gusto! Si necesitas algo más, aquí estoy.'],
    fallback: ['No estoy seguro de haber entendido. Puedes elegir una opción de abajo o escribir al consultorio para información más específica. ',
               { t: 'Escribir por WhatsApp', href: wa('Hola, tengo una duda.') }]
  };

  function cita() {
    if (ROL === 'paciente') return ['Puedes agendar, cancelar o reagendar tu cita desde tu cuenta. ', { t: 'Ir a la agenda', href: 'agenda.php' }];
    return ['Para agendar en línea necesitas una cuenta: ', { t: 'Registrarme', href: 'registro.php' },
            ' (si aún no tienes) o ', { t: 'Iniciar sesión', href: 'login.php' },
            '. Después eliges el día y la hora disponibles. También puedes pedir tu cita por WhatsApp: ',
            { t: 'Pedir cita por WhatsApp', href: wa('Hola, quisiera agendar una cita.') }];
  }

  const norm = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  function entender(texto) {
    const t = norm(texto);
    const tiene = (...p) => p.some(x => t.includes(x));
    if (tiene('gracias')) return R.gracias;
    if (tiene('cita', 'agendar', 'reservar', 'reagendar', 'cancelar')) return cita();
    if (tiene('hora', 'horario', 'abren', 'cierran', 'sabado')) return R.horarios;
    if (tiene('donde', 'ubicacion', 'direccion', 'llegar', 'mapa')) return R.ubicacion;
    if (tiene('servicio', 'precio', 'costo', 'cuanto', 'facial', 'corporal', 'tratamiento', 'plan')) return R.servicios;
    if (tiene('diabetes', 'peso', 'hipertension', 'colesterol', 'trigliceridos', 'tiroides', 'menopausia', 'embarazo', 'nino', 'insulina', 'dislipidemia', 'atienden')) return R.atendidos;
    if (tiene('dieta', 'comer', 'medicamento', 'diagnostico', 'enfermo', 'dolor')) return R.salud;
    if (tiene('telefono', 'whatsapp', 'llamar', 'contacto', 'numero')) return R.contacto;
    if (tiene('humano', 'persona', 'consultorio', 'hablar', 'ayuda')) return R.humano;
    if (tiene('hola', 'buenas', 'buen dia')) return R.inicio;
    return R.fallback;
  }

  // ----- Interfaz -----
  const BOT = '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="6" r="3.5" fill="#ffc9e3"/><path d="M32 9v7" stroke="#fff" stroke-width="3" stroke-linecap="round"/>' +
    '<rect x="3" y="27" width="7" height="13" rx="3.5" fill="#ffc9e3"/><rect x="54" y="27" width="7" height="13" rx="3.5" fill="#ffc9e3"/>' +
    '<rect x="9" y="15" width="46" height="36" rx="13" fill="#fff"/><rect x="15" y="21" width="34" height="24" rx="9" fill="#4a2468"/>' +
    '<circle cx="25" cy="31" r="4.2" fill="#ffc9e3"/><circle cx="39" cy="31" r="4.2" fill="#ffc9e3"/>' +
    '<path d="M26 38.5q6 5 12 0" fill="none" stroke="#ffc9e3" stroke-width="2.6" stroke-linecap="round"/>' +
    '<circle cx="19" cy="38" r="2" fill="#d94f93" opacity=".7"/><circle cx="45" cy="38" r="2" fill="#d94f93" opacity=".7"/></svg>';

  const boton = document.createElement('button');
  boton.id = 'chat-boton'; boton.type = 'button'; boton.hidden = true;
  boton.setAttribute('aria-label', 'Abrir asistente virtual'); boton.innerHTML = BOT;

  const saludo = document.createElement('div');
  saludo.id = 'chat-saludo'; saludo.hidden = true;
  saludo.innerHTML = '¡Hola! Soy <strong>NARA-bot</strong>. Puedo ayudarte a agendar tu cita o resolver dudas.' +
    '<button type="button" aria-label="Cerrar aviso">×</button>';

  const panel = document.createElement('div');
  panel.id = 'chat-panel'; panel.hidden = true;
  panel.innerHTML = '<div class="chat-cab">' + BOT + '<span>NARA-bot<small>Asistente virtual</small></span>' +
    '<button type="button" id="chat-cerrar" aria-label="Cerrar">×</button></div>' +
    '<div id="chat-msgs"></div><div id="chat-rapidos"></div>' +
    '<form id="chat-form"><input id="chat-texto" type="text" placeholder="Escribe tu pregunta" autocomplete="off"><button type="submit">Enviar</button></form>';
  document.body.append(boton, saludo, panel);

  const msgs = panel.querySelector('#chat-msgs');
  function burbuja(partes, quien) {
    const d = document.createElement('div');
    d.className = 'burbuja ' + quien;
    (Array.isArray(partes) ? partes : [partes]).forEach(p => {
      if (typeof p === 'string') d.appendChild(document.createTextNode(p));
      else {
        const a = document.createElement('a');
        a.href = p.href; a.textContent = p.t;
        if (p.href.startsWith('http')) { a.target = '_blank'; a.rel = 'noopener'; }
        d.appendChild(a);
      }
    });
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
  }

  const rapidos = [['Servicios', () => R.servicios], ['Horarios', () => R.horarios], ['Ubicación', () => R.ubicacion],
                   ['¿A quién atienden?', () => R.atendidos], ['Agendar cita', cita], ['Hablar con el consultorio', () => R.humano]];
  const zonaRapidos = panel.querySelector('#chat-rapidos');
  rapidos.forEach(([txt, fn]) => {
    const b = document.createElement('button');
    b.type = 'button'; b.textContent = txt;
    b.addEventListener('click', () => { burbuja(txt, 'yo'); burbuja(fn(), 'bot'); });
    zonaRapidos.appendChild(b);
  });

  panel.querySelector('#chat-form').addEventListener('submit', e => {
    e.preventDefault();
    const inp = panel.querySelector('#chat-texto');
    const v = inp.value.trim();
    if (!v) return;
    burbuja(v, 'yo'); burbuja(entender(v), 'bot'); inp.value = '';
  });

  const guardar = (k, v) => { try { sessionStorage.setItem(k, v); } catch (e) {} };
  const leer = k => { try { return sessionStorage.getItem(k); } catch (e) { return null; } };

  let iniciado = false;
  function abrir() {
    panel.hidden = false; boton.hidden = true; saludo.hidden = true;
    guardar('nara_saludo', 'x');
    if (!iniciado) { burbuja(R.inicio, 'bot'); iniciado = true; }
    panel.querySelector('#chat-texto').focus();
  }
  function cerrar() { panel.hidden = true; boton.hidden = false; }
  boton.addEventListener('click', abrir);
  saludo.addEventListener('click', e => { if (e.target.tagName !== 'BUTTON') abrir(); });
  saludo.querySelector('button').addEventListener('click', () => { saludo.hidden = true; guardar('nara_saludo', 'x'); });
  panel.querySelector('#chat-cerrar').addEventListener('click', cerrar);

  // El asistente aparece 2 minutos después de que el usuario entra al sitio y se queda disponible.
  const ESPERA = 120000;
  if (!leer('nara_t0')) guardar('nara_t0', String(Date.now()));
  const faltan = Math.max(0, ESPERA - (Date.now() - Number(leer('nara_t0'))));
  setTimeout(() => {
    boton.hidden = false;
    if (!leer('nara_saludo')) saludo.hidden = false;
  }, faltan);
})();
