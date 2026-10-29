/* =====================================================================
   reportes.js - Reportes 1 y 2 (totales recalculados en el navegador)
   ===================================================================== */
(function () {
    'use strict';

    var compras = window.REPORTE_COMPRAS || [];
    var proveedores = window.REPORTE_PROVEEDORES || [];

    function fmt(n) {
        if (n === null || n === undefined || isNaN(n)) return '0';
        return (Math.round(n * 100) / 100).toLocaleString('es-AR');
    }
    function pct(n) {
        if (n === null || n === undefined || isNaN(n)) return '0%';
        return (Math.round(n * 1000) / 10).toLocaleString('es-AR') + '%';
    }
    function dentroDeRango(fecha, desde, hasta) {
        if (!fecha) return false;
        var f = fecha.substring(0, 10);
        if (desde && f < desde) return false;
        if (hasta && f > hasta) return false;
        return true;
    }

    // ---------------- Reporte 1: productos más comprados -----------
    function renderCompras() {
        var desde = (document.getElementById('comp-desde') || {}).value || '';
        var hasta = (document.getElementById('comp-hasta') || {}).value || '';
        var tbody = document.querySelector('#tabla-compras tbody');
        var tfoot = document.querySelector('#tabla-compras tfoot');
        if (!tbody) return;

        var agrupado = {};
        compras.forEach(function (c) {
            if (!dentroDeRango(c.fecha_recepcion, desde, hasta)) return;
            var key = c.producto_id;
            if (!agrupado[key]) {
                agrupado[key] = {
                    producto: c.producto,
                    categoria: c.categoria,
                    pedida: 0,
                    recibida: 0
                };
            }
            agrupado[key].pedida   += Number(c.cantidad_pedida) || 0;
            agrupado[key].recibida += Number(c.cantidad_recibida) || 0;
        });

        var lista = Object.values(agrupado).sort(function (a, b) {
            return b.recibida - a.recibida;
        });

        tbody.innerHTML = '';
        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="muted">Sin datos en el rango seleccionado.</td></tr>';
        }
        var totP = 0, totR = 0;
        lista.forEach(function (r) {
            totP += r.pedida;
            totR += r.recibida;
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + escapeHtml(r.producto) + '</td>' +
                '<td>' + escapeHtml(r.categoria) + '</td>' +
                '<td class="num">' + fmt(r.pedida)   + '</td>' +
                '<td class="num">' + fmt(r.recibida) + '</td>';
            tbody.appendChild(tr);
        });

        if (tfoot) {
            tfoot.innerHTML =
                '<tr><th colspan="2" class="text-right">Totales</th>' +
                '<th class="num">' + fmt(totP) + '</th>' +
                '<th class="num">' + fmt(totR) + '</th></tr>';
        }
    }

    // ---------------- Reporte 2: cumplimiento proveedores ----------
    function renderProveedores() {
        var desde = (document.getElementById('prov-desde') || {}).value || '';
        var hasta = (document.getElementById('prov-hasta') || {}).value || '';
        var tbody = document.querySelector('#tabla-proveedores tbody');
        if (!tbody) return;

        // Agrupamos por orden y luego por proveedor
        var agrupado = {};
        proveedores.forEach(function (p) {
            if (!dentroDeRango(p.fecha_recepcion, desde, hasta)) return;
            var key = p.proveedor_id;
            if (!agrupado[key]) {
                agrupado[key] = {
                    proveedor: p.proveedor,
                    ordenes: 0,
                    a_tiempo: 0,
                    demora_acum: 0,
                    lineas: 0,
                    lineas_ok: 0
                };
            }
            var g = agrupado[key];
            g.ordenes += 1;
            var diff = 0;
            if (p.fecha_prometida && p.fecha_recepcion) {
                var a = new Date(p.fecha_prometida + 'T00:00:00');
                var b = new Date(p.fecha_recepcion.substring(0, 10) + 'T00:00:00');
                diff = Math.round((b - a) / 86400000);
            }
            if (diff <= 0) g.a_tiempo += 1;
            g.demora_acum += Math.max(diff, 0);
            g.lineas    += Number(p.lineas) || 0;
            g.lineas_ok += Number(p.lineas_completas) || 0;
        });

        var lista = Object.values(agrupado).sort(function (a, b) {
            var pa = a.ordenes ? a.a_tiempo / a.ordenes : 0;
            var pb = b.ordenes ? b.a_tiempo / b.ordenes : 0;
            return pb - pa;
        });

        tbody.innerHTML = '';
        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="muted">Sin datos en el rango seleccionado.</td></tr>';
        }
        lista.forEach(function (g) {
            var porcATiempo = g.ordenes ? (g.a_tiempo / g.ordenes) : 0;
            var demoraProm = g.ordenes ? (g.demora_acum / g.ordenes) : 0;
            var porcCompletas = g.lineas ? (g.lineas_ok / g.lineas) : 0;
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + escapeHtml(g.proveedor) + '</td>' +
                '<td class="num">' + g.ordenes + '</td>' +
                '<td class="num">' + pct(porcATiempo) + '</td>' +
                '<td class="num">' + demoraProm.toFixed(1) + ' d</td>' +
                '<td class="num">' + pct(porcCompletas) + '</td>';
            tbody.appendChild(tr);
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ---------------- Inicialización --------------------------------
    ['comp-desde', 'comp-hasta', 'prov-desde', 'prov-hasta'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', function () {
            renderCompras();
            renderProveedores();
        });
    });

    renderCompras();
    renderProveedores();
})();