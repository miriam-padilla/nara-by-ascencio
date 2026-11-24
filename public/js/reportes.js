// Reportes: ordenar tablas, graficar, exportar a Excel (CSV) y calculadora de ganancias
document.addEventListener('DOMContentLoaded', () => {
  const dinero = n => (n < 0 ? '-' : '') + '$' + Math.abs(Number(n)).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const valor = td => (td.dataset.v !== undefined ? parseFloat(td.dataset.v) : td.textContent.trim());

  document.querySelectorAll('.reporte').forEach(rep => {
    const tabla = rep.querySelector('table.datos');
    const graf = rep.querySelector('.grafica-rep');
    const selMetrica = rep.querySelector('[data-metrica]');
    const filas = () => [...tabla.querySelectorAll('tr')].filter(tr => tr.querySelector('td') && !tr.classList.contains('total') && tr.children.length > 1);

    // ---- Ordenar al hacer clic en el encabezado ----
    tabla.querySelectorAll('th').forEach((th, i) => {
      th.classList.add('ordenable');
      th.title = 'Clic para ordenar';
      th.addEventListener('click', () => {
        const asc = th.dataset.dir !== 'asc';
        tabla.querySelectorAll('th').forEach(o => { delete o.dataset.dir; });
        th.dataset.dir = asc ? 'asc' : 'desc';
        const rs = filas();
        const total = tabla.querySelector('tr.total');
        rs.sort((a, b) => {
          const x = valor(a.children[i]), y = valor(b.children[i]);
          const r = (typeof x === 'number' && typeof y === 'number') ? x - y : String(x).localeCompare(String(y), 'es');
          return asc ? r : -r;
        });
        rs.forEach(r => tabla.appendChild(r));
        if (total) tabla.appendChild(total);
        if (!graf.hidden) dibujar();
      });
    });

    // ---- Columnas numéricas para graficar ----
    const cabeceras = [...tabla.querySelectorAll('th')];
    const numericas = cabeceras.map((th, i) => i).filter(i => filas().length && filas()[0].children[i] && filas()[0].children[i].dataset.v !== undefined);
    numericas.forEach(i => {
      const o = document.createElement('option');
      o.value = i; o.textContent = cabeceras[i].textContent;
      selMetrica.appendChild(o);
    });
    if (!selMetrica.options.length) { rep.querySelectorAll('[data-accion="grafica"]').forEach(b => b.hidden = true); return; }
    selMetrica.value = tabla.dataset.grafica;
    selMetrica.addEventListener('change', dibujar);

    function dibujar() {
      const col = parseInt(selMetrica.value, 10);
      const esDinero = /ingreso|precio/i.test(cabeceras[col].textContent);
      const datos = filas().map(tr => ({ nombre: tr.children[0].textContent.trim(), v: parseFloat(tr.children[col].dataset.v) || 0 }));
      graf.innerHTML = '';
      if (!datos.length) { graf.textContent = 'No hay datos para graficar.'; return; }
      const max = Math.max(...datos.map(d => d.v), 1);
      const fila = 30, izq = 190, ancho = 640, der = 90, alto = datos.length * fila + 16;
      const ns = 'http://www.w3.org/2000/svg';
      const svg = document.createElementNS(ns, 'svg');
      svg.setAttribute('viewBox', `0 0 ${ancho} ${alto}`);
      svg.setAttribute('role', 'img');
      svg.setAttribute('aria-label', 'Gráfica de ' + cabeceras[col].textContent);
      const el = (t, a, txt) => { const n = document.createElementNS(ns, t); for (const k in a) n.setAttribute(k, a[k]); if (txt !== undefined) n.textContent = txt; svg.appendChild(n); return n; };
      datos.forEach((d, i) => {
        const y = 8 + i * fila, w = Math.max(2, (ancho - izq - der) * d.v / max);
        const nombre = d.nombre.length > 26 ? d.nombre.slice(0, 25) + '…' : d.nombre;
        el('text', { x: izq - 10, y: y + 17, 'text-anchor': 'end', 'font-size': 12.5, fill: '#2b2233' }, nombre);
        el('rect', { x: izq, y: y + 3, width: w, height: 20, rx: 4, fill: i % 2 ? '#9a6bb5' : '#6b3a8c' });
        el('text', { x: izq + w + 8, y: y + 18, 'font-size': 12, fill: '#2b2233' }, esDinero ? dinero(d.v) : d.v);
      });
      graf.appendChild(svg);
    }

    rep.querySelectorAll('[data-accion]').forEach(b => b.addEventListener('click', () => {
      if (b.dataset.accion === 'grafica') {
        graf.hidden = !graf.hidden;
        rep.querySelector('.metrica').hidden = graf.hidden;
        b.textContent = graf.hidden ? 'Ver gráfica' : 'Ocultar gráfica';
        if (!graf.hidden) dibujar();
      } else {
        const filasCsv = [...tabla.querySelectorAll('tr')].filter(tr => tr.children.length > 1).map(tr =>
          [...tr.children].map(c => '"' + (c.dataset.v !== undefined ? c.dataset.v : c.textContent.trim()).replace(/"/g, '""') + '"').join(','));
        const blob = new Blob(['﻿' + filasCsv.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = b.dataset.archivo + '.csv';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
      }
    }));
  });

  // ---- Calculadora de ganancias ----
  const calc = document.getElementById('calculadora');
  if (calc) {
    const ingresos = parseFloat(calc.dataset.ingresos) || 0, atendidas = parseInt(calc.dataset.atendidas, 10) || 0;
    const inputs = calc.querySelectorAll('[data-gasto]');
    const actualizar = () => {
      const gastos = [...inputs].reduce((s, i) => s + (parseFloat(i.value) || 0), 0);
      const ganancia = ingresos - gastos;
      calc.querySelector('#c-gastos').textContent = dinero(gastos);
      const g = calc.querySelector('#c-ganancia');
      g.textContent = dinero(ganancia);
      g.style.color = ganancia < 0 ? '#c0392b' : '';
      calc.querySelector('#c-margen').textContent = ingresos > 0 ? 'Margen ' + (100 * ganancia / ingresos).toFixed(1) + ' %' : 'Sin ingresos en el periodo';
      const ticket = atendidas > 0 ? ingresos / atendidas : 0;
      const eq = calc.querySelector('#c-equilibrio');
      if (gastos > 0 && ticket > 0) {
        const necesarias = Math.ceil(gastos / ticket);
        eq.textContent = `Punto de equilibrio: ${necesarias} citas atendidas para cubrir los gastos (llevas ${atendidas}).`;
      } else {
        eq.textContent = '';
      }
    };
    inputs.forEach(i => i.addEventListener('input', actualizar));
    actualizar();
  }
});
