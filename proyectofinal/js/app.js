$(function () {
    var zafras = [];

    function formato(n, unidad) {
        if (n === null || n === undefined) return '--';
        var decimales = (unidad === 't' || unidad === 'ha') ? 0 : 2;
        return n.toLocaleString('es-MX', { minimumFractionDigits: decimales, maximumFractionDigits: decimales }) + ' ' + unidad;
    }

    function porcentaje(n) {
        if (n === null || n === undefined) return '';
        var clase = n >= 0 ? 'sube' : 'baja';
        return '<span class="' + clase + '">' + (n >= 0 ? '+' : '') + n.toFixed(1) + '%</span>';
    }

    function escapar(texto) {
        return $('<div>').text(texto).html();
    }

    function revisarIa() {
        $.getJSON('api/estado.php')
            .done(function (r) {
                if (!r.ollama) {
                    $('#estadoIa').text('Ollama apagado').addClass('mal');
                } else if (!r.instalado) {
                    $('#estadoIa').text('Falta el modelo ' + r.modelo).addClass('mal');
                } else {
                    $('#estadoIa').text('IA lista: ' + r.modelo).addClass('ok');
                }
                $('#modeloIa').text('(' + r.modelo + ')');
            })
            .fail(function () {
                $('#estadoIa').text('Sin servidor PHP').addClass('mal');
            });
    }

    function cargarCatalogos() {
        $.getJSON('api/catalogos.php')
            .done(function (r) {
                $.each(r.ingenios, function (i, nombre) {
                    $('#ingenio').append($('<option>').val(nombre).text(nombre));
                });
                zafras = r.zafras;
                if (zafras.length) {
                    $('#zafra option[value="todas"]').text('Todas las zafras (' + zafras[0] + ' a ' + zafras[zafras.length - 1] + ')');
                }
                $.each(zafras.slice().reverse(), function (i, z) {
                    $('#zafra').append($('<option>').val(z).text(z));
                });
                $.each(r.indicadores, function (i, ind) {
                    $('#indicador').append($('<option>').val(ind.clave).text(ind.nombre + ' (' + ind.unidad + ')'));
                });
                $('#infoArchivos').text(r.archivos.length + ' archivos CSV, ' + r.total_registros + ' registros cargados');
                if (!r.archivos.length) {
                    $('#errorForm').text('No hay archivos CSV en la carpeta data/.');
                }
            })
            .fail(function () {
                $('#errorForm').text('No se pudieron cargar los datos. Revisa que el servidor PHP esté encendido.');
            });
    }

    function validar() {
        var pregunta = $.trim($('#pregunta').val());
        $('.invalido').removeClass('invalido');
        if (!$('#indicador').val()) {
            $('#indicador').addClass('invalido');
            return 'Selecciona un indicador.';
        }
        if (pregunta.length < 5) {
            $('#pregunta').addClass('invalido');
            return 'Escribe una pregunta de al menos 5 caracteres.';
        }
        return '';
    }

    function mostrarCargando() {
        $('#respuestaIa').removeClass('vacia error').html('<div class="cargando"><div class="spinner"></div>Calculando indicadores y consultando a la IA local... puede tardar unos segundos.</div>');
    }

    function pintarKpis(r) {
        var u = r.indicador.unidad;
        $('#kpiIngenios').text(r.kpis.ingenios);
        var titulo = r.filtros.ingenio !== 'todos' ? 'Promedio del ingenio' : (r.filtros.zafra === 'todas' ? 'Promedio histórico' : 'Promedio de la zafra');
        $('#kpiPromedioTitulo').text(titulo);
        $('#kpiPromedio').text(formato(r.kpis.promedio, u));
        $('#kpiMejor').text(formato(r.kpis.mejor.valor, u));
        $('#kpiMejorDetalle').text(r.kpis.mejor.ingenio + ' · ' + r.kpis.mejor.zafra);
        $('#kpiPeriodo').text(r.kpis.periodo);
    }

    function pintarRanking(r) {
        var u = r.indicador.unidad;
        var $tbody = $('#tablaRanking tbody').empty();
        $.each(r.ranking, function (i, fila) {
            var $tr = $('<tr>')
                .append($('<td>').text(fila.posicion))
                .append($('<td>').text(fila.ingenio))
                .append($('<td class="num">').text(formato(fila.valor, u)))
                .append($('<td class="num">').html(porcentaje(fila.vs_promedio)));
            if (fila.ingenio === r.filtros.ingenio) $tr.addClass('resaltado');
            $tbody.append($tr);
        });
        var p = r.posicion_ingenio;
        if (p) {
            $('#posicion').html('<b>' + escapar(p.ingenio) + '</b> ocupa el lugar <b>' + p.posicion + '</b> de ' + r.total_ranking + ' ingenios (' + porcentaje(p.vs_promedio) + ' vs promedio).');
        } else {
            $('#posicion').text('Promedio de ' + r.total_ranking + ' ingenios: ' + formato(r.kpis.promedio_general, u));
        }
    }

    function pintarGrafica(r) {
        var u = r.indicador.unidad;
        var propio = r.filtros.ingenio !== 'todos';
        var clave = propio ? 'ingenio' : 'promedio_nacional';
        var maximo = 0;
        $.each(r.serie, function (i, p) {
            maximo = Math.max(maximo, p[clave] || 0, p.promedio_nacional || 0);
        });
        var $g = $('#grafica').empty();
        $.each(r.serie, function (i, p) {
            var valor = p[clave];
            var ancho = valor ? (valor / maximo * 100) : 0;
            var $pista = $('<div class="barra-pista">').append($('<div class="barra">').toggleClass('propia', propio).css('width', ancho + '%'));
            if (propio && p.promedio_nacional) {
                $pista.append($('<div class="marca-prom">').css('left', (p.promedio_nacional / maximo * 100) + '%'));
            }
            $g.append($('<div class="barra-fila">')
                .append($('<span>').text(p.zafra))
                .append($pista)
                .append($('<span class="num">').text(valor ? formato(valor, u) : 's/d')));
        });
        var leyenda = propio
            ? '<i style="background:#15803d"></i>' + escapar(r.filtros.ingenio) + '<i style="background:#f59e0b"></i>Promedio de ingenios'
            : '<i style="background:#60a5fa"></i>Promedio de ingenios por zafra';
        $g.append('<div class="leyenda">' + leyenda + '</div>');

        var t = r.tendencia;
        $('#tendencia').html(t ? 'Variación ' + t.desde + ' a ' + t.hasta + ': ' + porcentaje(t.variacion) : '');
    }

    function pintarRespuesta(r) {
        $('#contexto').text(r.contexto);
        $('#notas').text(r.notas.join(' ')).prop('hidden', !r.notas.length);
        if (r.respuesta) {
            var html = escapar(r.respuesta)
                .replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
                .replace(/(Recomendación:)/g, '<b>$1</b>');
            $('#respuestaIa').removeClass('vacia error').html(html + '<p class="leyenda">Generado por ' + escapar(r.modelo) + ' en ' + r.segundos + ' s</p>');
        } else {
            $('#respuestaIa').removeClass('vacia').addClass('error').text((r.error_ia || 'Sin respuesta de la IA.') + ' Los indicadores calculados sí se muestran abajo.');
        }
    }

    $('#pregunta').on('input', function () {
        $('#contador').text($(this).val().length);
    });

    $('.chip').on('click', function () {
        var $c = $(this);
        $('#pregunta').val($c.data('pregunta')).trigger('input');
        if ($c.data('indicador') && $('#indicador option[value="' + $c.data('indicador') + '"]').length) {
            $('#indicador').val($c.data('indicador'));
        }
        if ($c.data('ingenio')) $('#ingenio').val($c.data('ingenio'));
        if ($c.data('zafra') === 'ultima' && zafras.length) {
            $('#zafra').val(zafras[zafras.length - 1]);
        } else if ($c.data('zafra')) {
            $('#zafra').val($c.data('zafra'));
        }
        if ($c.text() === 'Tendencia de un ingenio' && $('#ingenio').val() === 'todos') {
            $('#errorForm').text('Ahora elige un ingenio en la lista.');
            $('#ingenio').focus();
        }
    });

    $('#verContexto').on('click', function (e) {
        e.preventDefault();
        var $c = $('#contexto');
        if (!$c.text()) return;
        $c.prop('hidden', !$c.prop('hidden'));
        $(this).text($c.prop('hidden') ? 'Ver datos enviados a la IA' : 'Ocultar datos enviados a la IA');
    });

    $('#formConsulta').on('submit', function (e) {
        e.preventDefault();
        var error = validar();
        $('#errorForm').text(error);
        if (error) return;

        var datos = {
            ingenio: $('#ingenio').val(),
            zafra: $('#zafra').val(),
            indicador: $('#indicador').val(),
            pregunta: $.trim($('#pregunta').val())
        };

        $('#btnConsultar').prop('disabled', true).text('Consultando...');
        mostrarCargando();

        $.ajax({
            url: 'api/consultar.php',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(datos),
            timeout: 300000
        })
            .done(function (r) {
                pintarKpis(r);
                pintarRanking(r);
                pintarGrafica(r);
                pintarRespuesta(r);
            })
            .fail(function (xhr, estado) {
                var msj = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error al consultar (' + estado + ').';
                $('#respuestaIa').removeClass('vacia').addClass('error').text(msj);
            })
            .always(function () {
                $('#btnConsultar').prop('disabled', false).text('Consultar con IA');
            });
    });

    revisarIa();
    cargarCatalogos();
});
