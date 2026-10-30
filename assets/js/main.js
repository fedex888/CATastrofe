/* =====================================================================
   main.js - Interacciones generales
   ===================================================================== */
(function () {
    'use strict';

    // ---- Autocierre de mensajes flash ------------------------------
    document.querySelectorAll('[data-flash]').forEach(function (el) {
        setTimeout(function () {
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 5000);
    });

    // ---- Apertura/cierre de <dialog> -------------------------------
    document.querySelectorAll('[data-dialog-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dlg = document.getElementById(btn.dataset.dialogOpen);
            if (dlg && typeof dlg.showModal === 'function') {
                dlg.showModal();
            }
        });
    });
    document.querySelectorAll('[data-dialog-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dlg = btn.closest('dialog');
            if (dlg) dlg.close();
        });
    });
    // Cerrar al hacer click en el backdrop
    document.querySelectorAll('dialog').forEach(function (dlg) {
        dlg.addEventListener('click', function (ev) {
            // Solo el click directo sobre el <dialog> (el backdrop). Los clicks generados por teclado
            // (Enter, Espacio) llegan con coordenadas 0,0 desde un botón interno y no deben cerrarlo.
            if (ev.target !== dlg) return;
            var r = dlg.getBoundingClientRect();
            if (ev.clientX < r.left || ev.clientX > r.right || ev.clientY < r.top || ev.clientY > r.bottom) {
                dlg.close();
            }
        });
    });

    // ---- Confirmación de envío (data-confirm) ----------------------
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (ev) {
            if (!confirm(el.dataset.confirm)) {
                ev.preventDefault();
            }
        });
    });
    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!confirm(f.dataset.confirm)) {
                ev.preventDefault();
            }
        });
    });

    // ---- Tabs ------------------------------------------------------
    document.querySelectorAll('[data-tabs]').forEach(function (cont) {
        var tabs = cont.querySelectorAll('.tab');
        var panes = cont.querySelectorAll('.tab-content');
        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                tabs.forEach(function (x) { x.classList.remove('active'); });
                panes.forEach(function (p) { p.classList.remove('active'); });
                t.classList.add('active');
                var pane = cont.querySelector('#' + t.dataset.tab);
                if (pane) pane.classList.add('active');
            });
        });
    });

    // ---- Movimientos: filtrar motivos por tipo --------------------
    var selTipo = document.querySelector('[data-tipo-movimiento]');
    var selMotivo = document.querySelector('[data-motivo-select]');
    var campoVenc = document.querySelector('[data-vencimiento-wrapper]');
    var selProducto = document.querySelector('[data-producto-select]');

    function aplicarVisibilidadMotivos() {
        if (!selTipo || !selMotivo) return;
        var tipo = selTipo.value;
        Array.prototype.forEach.call(selMotivo.options, function (opt) {
            if (!opt.value) return;
            var ok = opt.dataset.tipo === tipo;
            opt.hidden = !ok;
            opt.disabled = !ok;
        });
        // Si el seleccionado no corresponde, resetear
        var sel = selMotivo.options[selMotivo.selectedIndex];
        if (sel && sel.dataset.tipo !== tipo) {
            selMotivo.value = '';
        }
    }
    function aplicarVisibilidadVencimiento() {
        if (!campoVenc || !selProducto || !selTipo) return;
        var opt = selProducto.options[selProducto.selectedIndex];
        var controla = opt && opt.dataset.vencimiento === '1';
        var esEntrada = selTipo.value === 'entrada';
        campoVenc.style.display = (controla && esEntrada) ? '' : 'none';
        var input = campoVenc.querySelector('input');
        if (input) {
            input.required = controla && esEntrada;
            if (!(controla && esEntrada)) input.value = '';
        }
    }
    if (selTipo) selTipo.addEventListener('change', function () {
        aplicarVisibilidadMotivos();
        aplicarVisibilidadVencimiento();
    });
    if (selProducto) selProducto.addEventListener('change', aplicarVisibilidadVencimiento);
    aplicarVisibilidadMotivos();
    aplicarVisibilidadVencimiento();

    // ---- Movimientos: avisar si la salida supera el stock --------
    var formMov = document.querySelector('form[action$="api/accion_movimiento.php"]');
    if (formMov && selTipo && selProducto) {
        formMov.addEventListener('submit', function (ev) {
            if (selTipo.value !== 'salida') return;
            var opt = selProducto.options[selProducto.selectedIndex];
            var stock = opt ? parseFloat(opt.dataset.stock) : NaN;
            var campo = formMov.querySelector('[name="cantidad"]');
            var cant = campo ? parseFloat(campo.value) : NaN;
            if (!isNaN(stock) && !isNaN(cant) && cant > stock) {
                ev.preventDefault();
                alert('La cantidad solicitada supera el stock disponible (' +
                    stock.toLocaleString('es-AR') + ').');
            }
        });
    }

    // ---- Stock: mostrar campo vencimiento al crear producto ------
    var selCat = document.querySelector('[data-categoria-producto]');
    var wrapVenc = document.querySelector('[data-stock-inicial-vencimiento]');
    function aplicarVencimientoCategoria() {
        if (!selCat || !wrapVenc) return;
        var opt = selCat.options[selCat.selectedIndex];
        var controla = opt && opt.dataset.vencimiento === '1';
        wrapVenc.style.display = controla ? '' : 'none';
        var input = wrapVenc.querySelector('input');
        if (input) {
            input.required = controla;
            if (!controla) input.value = '';
        }
    }
    if (selCat) selCat.addEventListener('change', aplicarVencimientoCategoria);
    aplicarVencimientoCategoria();

    // ---- Stock: dialog de edición única, poblado por JS -----------
    var formEditar = document.getElementById('form-editar-producto');
    if (formEditar) {
        document.querySelectorAll('[data-editar]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var p;
                try { p = JSON.parse(btn.dataset.editar); } catch (e) { return; }
                formEditar.querySelector('[name="id"]').value = p.id;
                formEditar.querySelector('[name="nombre"]').value = p.nombre || '';
                formEditar.querySelector('[name="categoria_id"]').value = p.categoria_id;
                formEditar.querySelector('[name="proveedor_id"]').value = p.proveedor_id;
                formEditar.querySelector('[name="stock_minimo"]').value = p.stock_minimo;
                formEditar.querySelector('[name="stock_maximo"]').value = p.stock_maximo;
                formEditar.querySelector('[name="unidad_medida"]').value = p.unidad_medida || 'unidad';
                formEditar.querySelector('[name="descripcion"]').value = p.descripcion || '';
                document.getElementById('dialog-editar-producto').showModal();
            });
        });
    }
})();