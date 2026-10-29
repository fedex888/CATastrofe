/* =====================================================================
   filtros.js - Filtros en tiempo real sobre tablas
   Uso:
     <div data-filtros-container>
       <input data-filtro="texto">
       <select data-filtro="categoria">...</select>
       <table data-filtro-tabla>
         <tr data-nombre="..." data-categoria="1" ...>
       </table>
     </div>
   ===================================================================== */
(function () {
    'use strict';

    document.querySelectorAll('[data-filtros-container]').forEach(function (cont) {
        var controles = cont.querySelectorAll('[data-filtro]');
        var tabla = cont.querySelector('[data-filtro-tabla]');
        if (!tabla) return;
        var filas = tabla.querySelectorAll('tbody tr');

        function aplicar() {
            var f = {};
            controles.forEach(function (c) {
                f[c.dataset.filtro] = (c.value || '').toLowerCase().trim();
            });

            filas.forEach(function (tr) {
                var ok = true;
                if (f.texto) {
                    var nombre = (tr.dataset.nombre || '').toLowerCase();
                    if (nombre.indexOf(f.texto) === -1) ok = false;
                }
                // Rango de fechas contra data-fecha (formato Y-m-d)
                if (f.desde && (tr.dataset.fecha || '') < f.desde) ok = false;
                if (f.hasta && (tr.dataset.fecha || '') > f.hasta) ok = false;
                // Resto de filtros: coincidencia exacta contra data-<clave>
                Object.keys(f).forEach(function (k) {
                    if (k === 'texto' || k === 'desde' || k === 'hasta' || !f[k]) return;
                    var valor = (tr.getAttribute('data-' + k) || '').toLowerCase();
                    if (valor !== f[k]) ok = false;
                });
                tr.style.display = ok ? '' : 'none';
            });
        }

        controles.forEach(function (c) {
            c.addEventListener('input', aplicar);
            c.addEventListener('change', aplicar);
        });
        aplicar();
    });
})();