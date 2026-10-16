// Lógica de la página con jQuery

$(function () {
  'use strict';

  var API = 'api/';
  var PDF_MAXIMO = 4 * 1024 * 1024;
  var ESTATUS = ['Recibido', 'En proceso', 'Atendido'];
  var CLASE_ESTATUS = { 'Recibido': 'recibido', 'En proceso': 'proceso', 'Atendido': 'atendido' };

  // Worker de pdf.js
  if (window.pdfjsLib) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  }

  var estado = {
    pagina: 1,
    documentos: {},
    edicion: null,
    fotos: [],
    pdf: null,
    visorId: null,
    renderVisor: 0
  };

  // Utilidades

  function escapar(texto) {
    return $('<div>').text(texto == null ? '' : String(texto)).html();
  }

  function formatoFecha(iso) {
    if (!iso) return '';
    var p = iso.substring(0, 10).split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
  }

  function formatoTamano(bytes) {
    if (bytes == null) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  function hoyISO() {
    var d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().substring(0, 10);
  }

  function etiquetaEstatus(estatus) {
    return '<span class="estatus estatus--' + CLASE_ESTATUS[estatus] + '">' + escapar(estatus) + '</span>';
  }

  function urlPdf(id, descargar) {
    return API + 'pdf.php?id=' + encodeURIComponent(id) + (descargar ? '&descargar=1' : '');
  }

  // Petición a la API
  function api(opciones) {
    return $.ajax($.extend({ dataType: 'json', cache: false }, opciones)).then(null, function (xhr) {
      var r = xhr.responseJSON || {};
      var mensaje = r.error || (xhr.status === 0
        ? 'Sin conexión con el servidor.'
        : 'El servidor respondió con un error (' + xhr.status + ').');
      return $.Deferred().reject({ mensaje: mensaje, errores: r.errores || {}, campo: r.campo, status: xhr.status });
    });
  }

  function avisar(mensaje, tipo) {
    var $aviso = $('<div class="aviso">')
      .toggleClass('aviso--error', tipo === 'error')
      .append('<span class="icono">' + (tipo === 'error' ? 'error' : 'check_circle') + '</span>')
      .append($('<span>').text(mensaje))
      .appendTo('#avisos');
    setTimeout(function () {
      $aviso.addClass('saliendo');
      setTimeout(function () { $aviso.remove(); }, 260);
    }, tipo === 'error' ? 5000 : 3200);
  }

  // Navegación entre vistas

  function navegar() {
    var partes = (location.hash.replace('#', '') || 'inicio').split('/');
    var ruta = partes[0];
    var vista = { inicio: 'inicio', documentos: 'documentos', buscar: 'documentos', nuevo: 'formulario', editar: 'formulario', acerca: 'acerca' }[ruta];

    if (!vista) { location.replace('#inicio'); return; }

    cerrarMenu();
    $('.vista').attr('hidden', true);
    var $vista = $('#vista-' + vista).removeAttr('hidden');
    document.title = $vista.data('titulo') + ' · Gestión de Documentos UNACH';

    var activa = ruta === 'editar' ? 'documentos' : ruta;
    $('[data-vista]').removeClass('activo').filter('[data-vista="' + activa + '"]').addClass('activo');
    window.scrollTo(0, 0);

    if (ruta === 'inicio') cargarResumen();
    if (vista === 'documentos') {
      cargarDocumentos();
      if (ruta === 'buscar') {
        mostrarFiltrosAvanzados(true);
        $('#filtro-q').trigger('focus');
      }
    }
    if (ruta === 'nuevo') prepararFormulario(null);
    if (ruta === 'editar') cargarParaEditar(parseInt(partes[1], 10));
  }

  $(window).on('hashchange', navegar);

  // Inicio

  function saludar() {
    var h = new Date().getHours();
    $('#saludo').text(h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches');
  }

  function cargarResumen() {
    saludar();
    api({ url: API + 'resumen.php' }).done(function (r) {
      var d = r.data;
      $('#indicadores [data-clave="total"]').text(d.total);
      ESTATUS.forEach(function (e) {
        $('#indicadores [data-clave="' + e + '"]').text(d.por_estatus[e]);
      });

      var $lista = $('#recientes').empty();
      if (!d.recientes.length) {
        $lista.append('<li class="vacio-texto">Aún no hay documentos registrados.</li>');
        return;
      }
      d.recientes.forEach(function (doc) {
        $lista.append(
          '<li><a href="#" data-ver="' + doc.id_documento + '">' +
            '<strong>' + escapar(doc.asunto) + '</strong>' + etiquetaEstatus(doc.estatus) +
            '<small>' + escapar(doc.no_oficio) + ' · ' + formatoFecha(doc.fecha) + ' · ' + escapar(doc.remitente) + '</small>' +
          '</a></li>'
        );
      });
    }).fail(function (e) {
      $('#recientes').html('<li class="vacio-texto">' + escapar(e.mensaje) + '</li>');
    });
  }

  // Los indicadores abren el listado filtrado
  $('#indicadores').on('click', '.indicador', function () {
    limpiarFiltros();
    $('#form-filtros [name="estatus"]').val($(this).data('estatus'));
  });

  // Listado, búsqueda y filtros

  var $filtros = $('#form-filtros');
  var temporizadorBusqueda;
  var peticionListado;

  function leerFiltros() {
    var filtros = {};
    $.each($filtros.serializeArray(), function (_, campo) {
      var valor = $.trim(campo.value);
      if (valor) filtros[campo.name] = valor;
    });
    return filtros;
  }

  function cargarDocumentos() {
    var filtros = leerFiltros();

    if (filtros.desde && filtros.hasta && filtros.desde > filtros.hasta) {
      avisar('La fecha «Desde» no puede ser posterior a «Hasta».', 'error');
      return;
    }

    if (peticionListado) peticionListado.abort();
    $('.resultados').addClass('cargando');

    peticionListado = $.ajax({ url: API + 'documentos.php', data: $.extend({ pagina: estado.pagina, por_pagina: 10 }, filtros), dataType: 'json', cache: false });
    peticionListado.done(function (r) {
      estado.pagina = r.pagina;
      pintarListado(r);
      pintarChips(filtros);
    }).fail(function (xhr, estadoTexto) {
      if (estadoTexto === 'abort') return;
      avisar((xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo cargar el listado.', 'error');
      $('#paginacion-info').text('Error al cargar.');
    }).always(function () {
      $('.resultados').removeClass('cargando');
    });
  }

  function pintarListado(r) {
    var $tabla = $('#tabla-cuerpo').empty();
    var $movil = $('#lista-movil').empty();
    estado.documentos = {};

    r.data.forEach(function (doc) {
      estado.documentos[doc.id_documento] = doc;
      recordarSugerencias(doc);

      var botonVer = '<button class="btn-icono" type="button" data-ver="' + doc.id_documento + '" title="Ver documento" aria-label="Ver documento"><span class="icono">visibility</span></button>';
      var botonDescargar = '<a class="btn-icono" href="' + urlPdf(doc.id_documento, true) + '" title="Descargar PDF" aria-label="Descargar PDF"><span class="icono">download</span></a>';
      var botonMenu = '<button class="btn-icono" type="button" data-menu="' + doc.id_documento + '" title="Más acciones" aria-label="Más acciones" aria-haspopup="menu"><span class="icono">more_vert</span></button>';

      $tabla.append(
        '<tr>' +
          '<td>' + escapar(doc.no_oficio) + '</td>' +
          '<td>' + formatoFecha(doc.fecha) + '</td>' +
          '<td>' + escapar(doc.asunto) + '</td>' +
          '<td>' + escapar(doc.remitente) + '</td>' +
          '<td>' + escapar(doc.destinatario) + '</td>' +
          '<td>' + etiquetaEstatus(doc.estatus) + '</td>' +
          '<td class="tabla__acciones">' + botonVer + botonDescargar + botonMenu + '</td>' +
        '</tr>'
      );

      $movil.append(
        '<li><div class="doc-movil">' +
          '<div class="doc-movil__principal" data-ver="' + doc.id_documento + '">' +
            '<div class="doc-movil__oficio">' + escapar(doc.no_oficio) + ' · ' + formatoFecha(doc.fecha) + '</div>' +
            '<p class="doc-movil__asunto">' + escapar(doc.asunto) + '</p>' +
            '<div class="doc-movil__ruta">' + escapar(doc.remitente) + '<span class="icono">arrow_forward</span>' + escapar(doc.destinatario) + '</div>' +
          '</div>' +
          '<div class="doc-movil__acciones">' + botonMenu + '</div>' +
          '<div>' + etiquetaEstatus(doc.estatus) + '</div>' +
        '</div></li>'
      );
    });

    $('#sin-resultados').prop('hidden', r.data.length > 0);
    $('.tabla-envoltura').toggleClass('vacia', r.data.length === 0);

    var inicio = r.total ? (r.pagina - 1) * r.por_pagina + 1 : 0;
    var fin = Math.min(r.pagina * r.por_pagina, r.total);
    $('#paginacion-info').text(r.total
      ? 'Mostrando ' + inicio + ' a ' + fin + ' de ' + r.total + ' registro' + (r.total === 1 ? '' : 's')
      : 'Sin registros');
    pintarPaginacion(r.pagina, r.paginas);
  }

  function pintarPaginacion(actual, total) {
    var $c = $('#paginacion-botones').empty();
    function boton(etiqueta, pagina, opciones) {
      opciones = opciones || {};
      $('<button type="button">')
        .html(etiqueta)
        .attr({ 'data-pagina': pagina, 'aria-label': opciones.titulo || 'Página ' + pagina })
        .prop('disabled', !!opciones.deshabilitado)
        .toggleClass('activo', !!opciones.activo)
        .appendTo($c);
    }
    boton('<span class="icono">first_page</span>', 1, { deshabilitado: actual === 1, titulo: 'Primera página' });
    boton('<span class="icono">chevron_left</span>', actual - 1, { deshabilitado: actual === 1, titulo: 'Anterior' });
    var desde = Math.max(1, Math.min(actual - 2, total - 4));
    for (var p = desde; p <= Math.min(total, desde + 4); p++) {
      boton(p, p, { activo: p === actual });
    }
    boton('<span class="icono">chevron_right</span>', actual + 1, { deshabilitado: actual === total, titulo: 'Siguiente' });
    boton('<span class="icono">last_page</span>', total, { deshabilitado: actual === total, titulo: 'Última página' });
  }

  $('#paginacion-botones').on('click', 'button', function () {
    estado.pagina = parseInt($(this).data('pagina'), 10);
    cargarDocumentos();
    $('.resultados')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  // Filtros activos
  function pintarChips(filtros) {
    var nombres = { q: 'Texto', estatus: 'Estatus', desde: 'Desde', hasta: 'Hasta', no_oficio: 'Oficio', asunto: 'Asunto', remitente: 'Remitente', destinatario: 'Destinatario' };
    var $c = $('#chips-filtros').empty();
    $.each(filtros, function (campo, valor) {
      var texto = (campo === 'desde' || campo === 'hasta') ? formatoFecha(valor) : valor;
      $c.append(
        '<span class="chip">' + nombres[campo] + ': ' + escapar(texto) +
        '<button type="button" data-quitar="' + campo + '" aria-label="Quitar filtro"><span class="icono">close</span></button></span>'
      );
    });
  }

  $('#chips-filtros').on('click', '[data-quitar]', function () {
    $filtros.find('[name="' + $(this).data('quitar') + '"]').val('');
    buscar();
  });

  function buscar() {
    estado.pagina = 1;
    cargarDocumentos();
  }

  function limpiarFiltros() {
    $filtros[0].reset();
    estado.pagina = 1;
  }

  function mostrarFiltrosAvanzados(mostrar) {
    $('#filtros-avanzados').prop('hidden', !mostrar);
    $('#btn-mas-filtros').attr('aria-expanded', String(mostrar));
  }

  $filtros.on('submit', function (e) {
    e.preventDefault();
    clearTimeout(temporizadorBusqueda);
    buscar();
  });
  $filtros.on('change', 'select, input[type="date"]', buscar);
  $filtros.on('input', 'input[type="search"], input[type="text"]', function () {
    clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = setTimeout(buscar, 350);
  });
  $('#btn-limpiar').on('click', function (e) {
    e.preventDefault();
    limpiarFiltros();
    buscar();
  });
  $('#btn-mas-filtros').on('click', function () {
    mostrarFiltrosAvanzados($('#filtros-avanzados').prop('hidden'));
  });

  // Menú de acciones por documento

  var $menu = $('#menu-acciones');

  function abrirMenu(boton, doc) {
    var html = '<div class="menu__titulo">' + escapar(doc.no_oficio) + '</div>' +
      '<button type="button" data-ver="' + doc.id_documento + '"><span class="icono">visibility</span>Ver documento</button>' +
      '<a href="' + urlPdf(doc.id_documento, true) + '"><span class="icono">download</span>Descargar PDF</a>' +
      '<a href="#editar/' + doc.id_documento + '"><span class="icono">edit</span>Editar</a>' +
      '<hr><div class="menu__titulo">Cambiar estatus</div>';
    ESTATUS.forEach(function (e) {
      html += '<button type="button" data-estatus="' + e + '" data-id="' + doc.id_documento + '"' + (e === doc.estatus ? ' class="actual"' : '') + '>' +
        '<span class="punto punto--' + CLASE_ESTATUS[e] + '"></span>' + e + '</button>';
    });
    html += '<hr><button type="button" class="menu__peligro" data-eliminar="' + doc.id_documento + '"><span class="icono">delete</span>Eliminar</button>';

    $menu.html(html).removeAttr('hidden');

    // Posición del menú
    var r = boton.getBoundingClientRect();
    var ancho = $menu.outerWidth(), alto = $menu.outerHeight();
    var izquierda = Math.max(8, Math.min(r.right - ancho, window.innerWidth - ancho - 8));
    var arriba = r.bottom + 4;
    if (arriba + alto > window.innerHeight - 8) arriba = Math.max(8, r.top - alto - 4);
    $menu.css({ left: izquierda, top: arriba });
    $menu.find('button, a').first().trigger('focus');
  }

  function cerrarMenu() { $menu.attr('hidden', true); }

  $(document).on('click', '[data-menu]', function () {
    var doc = estado.documentos[$(this).data('menu')];
    if ($menu.is(':visible') && $menu.data('origen') === this) { cerrarMenu(); return; }
    $menu.data('origen', this);
    abrirMenu(this, doc);
  });
  $menu.on('click', 'button, a', function () { setTimeout(cerrarMenu, 0); });
  $menu.on('click', '[data-estatus]', function () {
    cambiarEstatus($(this).data('id'), $(this).data('estatus'));
  });
  $(document).on('click', function (e) {
    if (!$(e.target).closest('#menu-acciones, [data-menu]').length) cerrarMenu();
  });
  $(window).on('resize scroll', cerrarMenu);

  // Estatus y eliminación

  function cambiarEstatus(id, nuevo) {
    return api({ url: API + 'documentos.php', method: 'POST', data: { accion: 'estatus', id: id, estatus: nuevo } })
      .done(function (r) {
        avisar(r.mensaje);
        refrescarVistaActual();
        if (estado.visorId === id) pintarDetalleVisor(r.data);
      })
      .fail(function (e) { avisar(e.mensaje, 'error'); });
  }

  var idPorEliminar = null;

  function pedirEliminar(id) {
    var doc = estado.documentos[id] || (estado.visorDoc && estado.visorDoc.id_documento === id ? estado.visorDoc : null);
    idPorEliminar = id;
    $('#confirmar-texto').text(doc
      ? 'Se eliminará el oficio ' + doc.no_oficio + ' («' + doc.asunto + '») y su PDF. Esta acción no se puede deshacer.'
      : 'Esta acción no se puede deshacer.');
    abrirModal('#confirmar');
    $('#confirmar-aceptar').trigger('focus');
  }

  $(document).on('click', '[data-eliminar]', function () { pedirEliminar($(this).data('eliminar')); });
  $('#visor-eliminar').on('click', function () { pedirEliminar(estado.visorId); });

  $('#confirmar-aceptar').on('click', function () {
    var $btn = $(this).prop('disabled', true);
    api({ url: API + 'documentos.php', method: 'POST', data: { accion: 'eliminar', id: idPorEliminar } })
      .done(function (r) {
        avisar(r.mensaje);
        cerrarModal('#confirmar');
        if (estado.visorId === idPorEliminar) cerrarModal('#visor');
        refrescarVistaActual();
      })
      .fail(function (e) { avisar(e.mensaje, 'error'); })
      .always(function () { $btn.prop('disabled', false); });
  });

  function refrescarVistaActual() {
    var ruta = location.hash.replace('#', '').split('/')[0] || 'inicio';
    if (ruta === 'inicio') cargarResumen();
    if (ruta === 'documentos' || ruta === 'buscar') cargarDocumentos();
  }

  // Modales

  var focoPrevio = null;

  function abrirModal(selector) {
    focoPrevio = document.activeElement;
    $(selector).removeAttr('hidden');
    $('body').css('overflow', 'hidden');
  }

  function cerrarModal(selector) {
    $(selector).attr('hidden', true);
    if (selector === '#visor') {
      estado.visorId = null;
      estado.renderVisor++;
      $('#visor-pdf').empty();
    }
    if (!$('.modal:not([hidden])').length) $('body').css('overflow', '');
    if (focoPrevio && document.contains(focoPrevio)) focoPrevio.focus();
  }

  $('.modal').on('click', '[data-cerrar]', function () {
    cerrarModal('#' + $(this).closest('.modal').attr('id'));
  });
  $(document).on('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!$menu.is('[hidden]')) { cerrarMenu(); return; }
    var $abierto = $('.modal:not([hidden])').last();
    if ($abierto.length) cerrarModal('#' + $abierto.attr('id'));
  });

  // Visor del PDF

  $(document).on('click', '[data-ver]', function (e) {
    e.preventDefault();
    abrirVisor(parseInt($(this).data('ver'), 10));
  });

  function abrirVisor(id) {
    estado.visorId = id;
    var turno = ++estado.renderVisor;
    $('#visor-oficio, #visor-titulo').text('');
    $('#visor-ficha').empty();
    $('#visor-estatus').empty();
    $('#visor-pdf').html('<div class="visor__mensaje"><span class="icono">hourglass_top</span>Cargando documento…</div>');
    $('#visor-descargar').attr('href', urlPdf(id, true));
    $('#visor-editar').attr('href', '#editar/' + id);
    abrirModal('#visor');

    api({ url: API + 'documentos.php', data: { id: id } }).done(function (r) {
      if (turno !== estado.renderVisor) return;
      pintarDetalleVisor(r.data);
      if (r.data.tamano_bytes) {
        dibujarPdf(urlPdf(id), turno);
      } else {
        $('#visor-pdf').html('<div class="visor__mensaje"><span class="icono">draft</span>Este documento no tiene PDF asociado.</div>');
      }
    }).fail(function (e) {
      $('#visor-pdf').html('<div class="visor__mensaje"><span class="icono">error</span>' + escapar(e.mensaje) + '</div>');
    });
  }

  function pintarDetalleVisor(doc) {
    estado.visorDoc = doc;
    $('#visor-oficio').text('OFICIO ' + doc.no_oficio);
    $('#visor-titulo').text(doc.asunto);
    $('#visor-ficha').html(
      '<dt>Fecha</dt><dd>' + formatoFecha(doc.fecha) + '</dd>' +
      '<dt>Remitente</dt><dd>' + escapar(doc.remitente) + '</dd>' +
      '<dt>Destinatario</dt><dd>' + escapar(doc.destinatario) + '</dd>' +
      '<dt>Estatus</dt><dd>' + etiquetaEstatus(doc.estatus) + '</dd>' +
      '<dt>Archivo</dt><dd>' + escapar(doc.archivo_pdf || '—') + (doc.tamano_bytes ? ' · ' + formatoTamano(doc.tamano_bytes) : '') + '</dd>'
    );
    var html = '';
    ESTATUS.forEach(function (e) {
      html += '<label class="segmentado__opcion segmentado__opcion--' + CLASE_ESTATUS[e] + '">' +
        '<input type="radio" name="visor-estatus" value="' + e + '"' + (e === doc.estatus ? ' checked' : '') + '>' +
        '<span><i></i>' + e + '</span></label>';
    });
    $('#visor-estatus').html(html);
  }

  $('#visor-estatus').on('change', 'input', function () {
    cambiarEstatus(estado.visorId, this.value);
  });
  $('#visor-editar').on('click', function () { cerrarModal('#visor'); });

  function dibujarPdf(url, turno) {
    var $contenedor = $('#visor-pdf');
    if (!window.pdfjsLib) {
      $contenedor.html('<iframe src="' + url + '" title="Documento PDF" style="width:100%;height:100%;border:0;min-height:70vh"></iframe>');
      return;
    }
    pdfjsLib.getDocument(url).promise.then(function (pdf) {
      if (turno !== estado.renderVisor) return;
      $contenedor.empty();
      var anchoDisponible = Math.min($contenedor.width() || 600, 900);
      var escalaPantalla = window.devicePixelRatio || 1;

      // Dibuja las páginas en orden
      var cadena = Promise.resolve();
      for (var n = 1; n <= pdf.numPages; n++) {
        (function (numero) {
          cadena = cadena.then(function () {
            if (turno !== estado.renderVisor) return;
            return pdf.getPage(numero).then(function (pagina) {
              var base = pagina.getViewport({ scale: 1 });
              var vista = pagina.getViewport({ scale: (anchoDisponible / base.width) * escalaPantalla });
              var canvas = document.createElement('canvas');
              canvas.width = vista.width;
              canvas.height = vista.height;
              canvas.style.width = anchoDisponible + 'px';
              canvas.setAttribute('aria-label', 'Página ' + numero + ' de ' + pdf.numPages);
              $contenedor.append(canvas);
              return pagina.render({ canvasContext: canvas.getContext('2d'), viewport: vista }).promise;
            });
          });
        })(n);
      }
    }).catch(function () {
      if (turno !== estado.renderVisor) return;
      $contenedor.html('<div class="visor__mensaje"><span class="icono">error</span>No se pudo mostrar el PDF.<a class="btn btn--secundario" href="' + url + '" target="_blank" rel="noopener">Abrir en otra pestaña</a></div>');
    });
  }

  // Formulario de registro / edición

  var $form = $('#form-documento');
  var sugerencias = { remitente: {}, destinatario: {} };

  function recordarSugerencias(doc) {
    ['remitente', 'destinatario'].forEach(function (campo) {
      if (sugerencias[campo][doc[campo]]) return;
      sugerencias[campo][doc[campo]] = true;
      $('#lista-' + campo + 's').append($('<option>').val(doc[campo]));
    });
  }

  function limpiarErrores() {
    $form.find('.campo--invalido').removeClass('campo--invalido');
    $form.find('.campo__error').text('');
  }

  function marcarError(campo, mensaje) {
    var $campo = campo === 'archivo' ? $('#campo-archivo') : $form.find('[name="' + campo + '"]').closest('.campo');
    $campo.addClass('campo--invalido').find('.campo__error').text(mensaje);
  }

  function prepararFormulario(doc) {
    estado.edicion = doc;
    estado.fotos = [];
    estado.pdf = null;
    $form[0].reset();
    limpiarErrores();

    $('#formulario-titulo').text(doc ? 'Editar documento' : 'Registrar documento');
    $('#vista-formulario').data('titulo', doc ? 'Editar documento' : 'Nuevo documento');
    $('#btn-guardar span:last').text(doc ? 'Guardar cambios' : 'Guardar documento');
    $('#archivo-obligatorio').toggle(!doc || !doc.tamano_bytes);

    if (doc) {
      $.each(['id', 'no_oficio', 'fecha', 'asunto', 'remitente', 'destinatario'], function (_, campo) {
        $form.find('[name="' + campo + '"]').val(campo === 'id' ? doc.id_documento : doc[campo]);
      });
      $form.find('[name="estatus"][value="' + doc.estatus + '"]').prop('checked', true);
    } else {
      $form.find('[name="id"]').val('');
      $form.find('[name="fecha"]').val(hoyISO());
    }
    $form.find('[name="fecha"]').attr('max', hoyISO());
    pintarAdjunto();
  }

  function cargarParaEditar(id) {
    prepararFormulario(null);
    $form.addClass('cargando');
    api({ url: API + 'documentos.php', data: { id: id } })
      .done(function (r) { prepararFormulario(r.data); })
      .fail(function (e) { avisar(e.mensaje, 'error'); location.hash = '#documentos'; })
      .always(function () { $form.removeClass('cargando'); });
  }

  // Muestra el archivo adjunto
  function pintarAdjunto() {
    var $a = $('#adjunto');
    if (estado.fotos.length) {
      var html = '<div class="adjunto__fila"><span class="icono">picture_as_pdf</span>' +
        '<div class="adjunto__info"><strong>Se generará un PDF de ' + estado.fotos.length + ' página' + (estado.fotos.length === 1 ? '' : 's') + '</strong>' +
        '<small>Puedes tomar más fotos para agregar páginas.</small></div>' +
        '<button class="btn-icono" type="button" data-quitar-todo title="Quitar todo" aria-label="Quitar todo"><span class="icono">delete</span></button></div>' +
        '<div class="miniaturas">';
      estado.fotos.forEach(function (foto, i) {
        html += '<div class="miniatura"><img src="' + foto.vista + '" alt="Página ' + (i + 1) + '"><span>' + (i + 1) + '</span>' +
          '<button type="button" data-quitar-foto="' + i + '" aria-label="Quitar página ' + (i + 1) + '"><span class="icono">close</span></button></div>';
      });
      $a.html(html + '</div>').removeAttr('hidden');
    } else if (estado.pdf) {
      $a.html('<div class="adjunto__fila"><span class="icono">picture_as_pdf</span>' +
        '<div class="adjunto__info"><strong>' + escapar(estado.pdf.name) + '</strong><small>' + formatoTamano(estado.pdf.size) + ' · listo para guardar</small></div>' +
        '<button class="btn-icono" type="button" data-quitar-todo title="Quitar archivo" aria-label="Quitar archivo"><span class="icono">delete</span></button></div>'
      ).removeAttr('hidden');
    } else if (estado.edicion && estado.edicion.tamano_bytes) {
      $a.html('<div class="adjunto__fila"><span class="icono">picture_as_pdf</span>' +
        '<div class="adjunto__info"><strong>' + escapar(estado.edicion.archivo_pdf) + '</strong><small>PDF actual · ' + formatoTamano(estado.edicion.tamano_bytes) + ' · sube otro para reemplazarlo</small></div>' +
        '<button class="btn-icono" type="button" data-ver="' + estado.edicion.id_documento + '" title="Ver PDF actual" aria-label="Ver PDF actual"><span class="icono">visibility</span></button></div>'
      ).removeAttr('hidden');
    } else {
      $a.attr('hidden', true).empty();
    }
  }

  function agregarImagenes(archivos) {
    var promesas = $.map(archivos, function (archivo) {
      if (!/^image\//.test(archivo.type)) return null;
      return prepararImagen(archivo).then(function (foto) { estado.fotos.push(foto); });
    });
    estado.pdf = null;
    Promise.all(promesas).then(pintarAdjunto, function () {
      avisar('No se pudo leer una de las imágenes.', 'error');
      pintarAdjunto();
    });
  }

  $('#input-camara').on('change', function () {
    agregarImagenes(this.files);
    this.value = '';
    limpiarErrorArchivo();
  });

  $('#input-archivo').on('change', function () {
    var archivo = this.files[0];
    this.value = '';
    limpiarErrorArchivo();
    if (!archivo) return;

    if (archivo.type === 'application/pdf' || /\.pdf$/i.test(archivo.name)) {
      if (archivo.size > PDF_MAXIMO) {
        marcarError('archivo', 'El PDF pesa ' + formatoTamano(archivo.size) + '; el máximo es 4 MB.');
        return;
      }
      estado.pdf = archivo;
      estado.fotos = [];
      pintarAdjunto();
    } else if (/^image\//.test(archivo.type)) {
      agregarImagenes([archivo]);
    } else {
      marcarError('archivo', 'Solo se aceptan archivos PDF o imágenes.');
    }
  });

  function limpiarErrorArchivo() {
    $('#campo-archivo').removeClass('campo--invalido').find('.campo__error').text('');
  }

  $('#adjunto').on('click', '[data-quitar-foto]', function () {
    estado.fotos.splice($(this).data('quitar-foto'), 1);
    pintarAdjunto();
  }).on('click', '[data-quitar-todo]', function () {
    estado.fotos = [];
    estado.pdf = null;
    pintarAdjunto();
  });

  $form.on('input change', 'input', function () {
    $(this).closest('.campo').removeClass('campo--invalido').find('.campo__error').text('');
  });

  // Validación del formulario
  function validarFormulario() {
    limpiarErrores();
    var valido = true;
    $form.find('[required]').each(function () {
      if (!$.trim(this.value)) {
        marcarError(this.name, 'Este campo es obligatorio.');
        valido = false;
      }
    });
    var fecha = $form.find('[name="fecha"]').val();
    if (fecha && fecha > hoyISO()) {
      marcarError('fecha', 'La fecha de recepción no puede ser futura.');
      valido = false;
    }
    var tieneArchivo = estado.fotos.length || estado.pdf || (estado.edicion && estado.edicion.tamano_bytes);
    if (!tieneArchivo) {
      marcarError('archivo', 'Toma una foto o selecciona el PDF del documento.');
      valido = false;
    }
    return valido;
  }

  $form.on('submit', function (e) {
    e.preventDefault();
    if (!validarFormulario()) {
      $form.find('.campo--invalido').first().find('input').trigger('focus');
      if ($('#campo-archivo').hasClass('campo--invalido') && $form.find('.campo--invalido').length === 1) {
        $('#campo-archivo')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      return;
    }

    var $btn = $('#btn-guardar').prop('disabled', true);
    var $texto = $btn.find('span:last');
    var textoOriginal = $texto.text();
    var nombre = 'oficio_' + $form.find('[name="no_oficio"]').val().replace(/[^A-Za-z0-9_-]+/g, '-') + '.pdf';

    obtenerPdfAdjunto($texto)
      .then(function (pdf) {
        var datos = new FormData($form[0]);
        datos.append('accion', 'guardar');
        if (pdf) datos.append('archivo', pdf, nombre);
        $texto.text('Guardando…');
        return api({ url: API + 'documentos.php', method: 'POST', data: datos, processData: false, contentType: false });
      })
      .then(function (r) {
        avisar(r.mensaje);
        limpiarFiltros();
        location.hash = '#documentos';
      }, function (e) {
        avisar(e.mensaje, 'error');
        $.each(e.errores || {}, marcarError);
        if (e.campo) marcarError(e.campo, e.mensaje);
      })
      .always(function () {
        $btn.prop('disabled', false);
        $texto.text(textoOriginal);
      });
  });

  // Obtiene el PDF a subir
  function obtenerPdfAdjunto($texto) {
    if (estado.pdf) return $.Deferred().resolve(estado.pdf).promise();
    if (!estado.fotos.length) return $.Deferred().resolve(null).promise();

    $texto.text('Generando PDF…');
    var d = $.Deferred();
    // Si pesa mucho se baja la calidad
    generarPdf(estado.fotos, 1800, 0.78)
      .then(function (pdf) { return pdf.size <= PDF_MAXIMO ? pdf : generarPdf(estado.fotos, 1300, 0.6); })
      .then(function (pdf) { return pdf.size <= PDF_MAXIMO ? pdf : generarPdf(estado.fotos, 1000, 0.5); })
      .then(function (pdf) {
        if (pdf.size > PDF_MAXIMO) {
          d.reject({ mensaje: 'Demasiadas fotos: el PDF supera 4 MB. Quita algunas páginas.', errores: { archivo: 'El PDF supera 4 MB.' } });
        } else {
          d.resolve(pdf);
        }
      }, function () {
        d.reject({ mensaje: 'No se pudo generar el PDF a partir de las fotos.' });
      });
    return d.promise();
  }

  // Digitalización: fotos → PDF (jsPDF)

  // Carga la foto y su miniatura
  function prepararImagen(archivo) {
    return new Promise(function (resolver, rechazar) {
      var url = URL.createObjectURL(archivo);
      var img = new Image();
      img.onload = function () {
        resolver({ imagen: img, vista: dibujarEscalada(img, 240, 0.7) });
      };
      img.onerror = function () { URL.revokeObjectURL(url); rechazar(); };
      img.src = url;
    });
  }

  function dibujarEscalada(img, ladoMaximo, calidad) {
    var escala = Math.min(1, ladoMaximo / Math.max(img.naturalWidth, img.naturalHeight));
    var canvas = document.createElement('canvas');
    canvas.width = Math.round(img.naturalWidth * escala);
    canvas.height = Math.round(img.naturalHeight * escala);
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', calidad);
  }

  // Une las fotos en un PDF
  function generarPdf(fotos, ladoMaximo, calidad) {
    return new Promise(function (resolver) {
      var jsPDF = window.jspdf.jsPDF;
      var doc = null;
      fotos.forEach(function (foto) {
        var img = foto.imagen;
        var horizontal = img.naturalWidth > img.naturalHeight;
        var orientacion = horizontal ? 'landscape' : 'portrait';
        if (!doc) {
          doc = new jsPDF({ orientation: orientacion, unit: 'pt', format: 'letter', compress: true });
        } else {
          doc.addPage('letter', orientacion);
        }
        var anchoPag = doc.internal.pageSize.getWidth();
        var altoPag = doc.internal.pageSize.getHeight();
        var margen = 18;
        var escala = Math.min((anchoPag - margen * 2) / img.naturalWidth, (altoPag - margen * 2) / img.naturalHeight);
        var w = img.naturalWidth * escala, h = img.naturalHeight * escala;
        doc.addImage(dibujarEscalada(img, ladoMaximo, calidad), 'JPEG', (anchoPag - w) / 2, (altoPag - h) / 2, w, h, undefined, 'FAST');
      });
      doc.setProperties({ title: 'Documento digitalizado', creator: 'Gestión de Documentos UNACH' });
      resolver(doc.output('blob'));
    });
  }

  // Arranque
  navegar();
});
