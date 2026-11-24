// Validación en el navegador para todos los formularios con class="validar"
document.addEventListener('DOMContentLoaded', () => {
  const emailOk = v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);

  function mostrar(campo, mensaje) {
    let span = campo.parentElement.querySelector('.msg-error');
    if (!span) {
      span = document.createElement('span');
      span.className = 'msg-error';
      campo.parentElement.appendChild(span);
    }
    span.textContent = mensaje;
    campo.classList.toggle('invalido', mensaje !== '');
    return mensaje === '';
  }

  function revisar(campo) {
    if (campo.type === 'checkbox') return campo.required && !campo.checked ? mostrar(campo, 'Debes aceptar para continuar.') : mostrar(campo, '');
    const v = campo.value.trim();
    if (campo.type === 'radio') return true;
    if (campo.required && v === '') return mostrar(campo, 'Este campo es obligatorio.');
    if (v === '') return mostrar(campo, '');
    if (campo.type === 'email' && !emailOk(v)) return mostrar(campo, 'Escribe un correo válido.');
    if (campo.minLength > 0 && v.length < campo.minLength) return mostrar(campo, 'Mínimo ' + campo.minLength + ' caracteres.');
    if (campo.hasAttribute('data-tel') && !/^\d{10}$/.test(v)) return mostrar(campo, 'Deben ser 10 dígitos.');
    return mostrar(campo, '');
  }

  document.querySelectorAll('form.validar').forEach(form => {
    form.addEventListener('submit', e => {
      let valido = true;
      form.querySelectorAll('input, select, textarea').forEach(c => { if (!revisar(c)) valido = false; });

      // Horario: debe elegirse una opción
      const radios = form.querySelectorAll('input[type=radio][name=hora]');
      if (radios.length && !form.querySelector('input[name=hora]:checked')) {
        alert('Elige un horario.');
        valido = false;
      }
      if (!valido) e.preventDefault();
    });
    form.querySelectorAll('input, select, textarea').forEach(c => c.addEventListener('blur', () => revisar(c)));
  });
});
