<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informe de Nacimientos</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: Arial, sans-serif; margin: 24px; color: #1d2a2c; background: #fff; }
        h1 { font-size: 24px; margin: 0 0 4px; color: #234e35; }
        .meta { color: #5d676d; margin-bottom: 20px; font-size: 12px; line-height: 1.5; }
        .acciones { display: flex; gap: 10px; margin-bottom: 20px; }
        button { padding: 10px 20px; border: 0; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; }
        #imprimir { background: #2f5233; color: #fff; }
        #cerrar { background: #e4dcc6; color: #201d18; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; page-break-inside: avoid; }
        th, td { border: 1px solid #d4d7d9; padding: 8px; text-align: left; font-size: 12px; vertical-align: top; }
        th { background: #eef3f5; color: #1b3b2d; }
        .footer { margin-top: 16px; font-size: 11px; color: #5d676d; border-top: 1px solid #dfe5e7; padding-top: 10px; }
        .error { color: #8b1e1e; }
        @media print { body { margin: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
    <div class="acciones">
        <button id="imprimir" type="button">Exportar PDF</button>
        <button id="cerrar" type="button">Cerrar</button>
    </div>
    <h1 id="titulo">Informe de Nacimientos</h1>
    <div class="meta" id="meta">Generando reporte...</div>
    <main id="contenido"></main>
    <div class="footer">Sistema SOVCA</div>

    <script>
        const escapeHtml = (value) => String(value ?? '—')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

        const formatDate = (value) => {
            if (!value) return '—';
            const parts = String(value).slice(0, 10).split('-');
            return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
        };

        const number = (value, decimals = 1) => value === null || value === undefined
            ? '—' : Number(value).toFixed(decimals);

        function filtros() {
            const encoded = new URLSearchParams(window.location.search).get('%$%') || '';
            if (!encoded) return {};
            try { return Object.fromEntries(new URLSearchParams(atob(encoded))); } catch (error) { return {}; }
        }

        function diasEntre(reciente, anterior) {
            const desde = new Date(`${anterior}T00:00:00`);
            const hasta = new Date(`${reciente}T00:00:00`);
            return Number.isNaN(desde.getTime()) || Number.isNaN(hasta.getTime())
                ? null : Math.round((hasta - desde) / 86400000);
        }

        function fila(celdas) {
            return `<tr>${celdas.map((celda) => `<td>${escapeHtml(celda)}</td>`).join('')}</tr>`;
        }

        function tabla(encabezados, filas, columnas) {
            return `<table><thead><tr>${encabezados.map((encabezado) => `<th>${escapeHtml(encabezado)}</th>`).join('')}</tr></thead><tbody>${filas || `<tr><td colspan="${columnas}">Sin registros para los filtros seleccionados.</td></tr>`}</tbody></table>`;
        }

        function renderizar(registros) {
            const filtro = filtros();
            let codigoMadre = String(filtro.cod_bovs || '').trim();
            if (codigoMadre && String(filtro.codigo_erete || '') === '2') {
                const coincidencia = registros.find((registro) => String(registro.arete_madre || '').trim() === codigoMadre);
                codigoMadre = coincidencia ? coincidencia.codigo_madre : '';
            }

            const conIep = registros.map((registro, indice, lista) => {
                const anterior = lista.slice(0, indice).reverse().find((filaAnterior) => filaAnterior.codigo_madre === registro.codigo_madre);
                return { ...registro, iep: anterior ? diasEntre(registro.fecha, anterior.fecha) : null };
            });
            let filas = codigoMadre ? conIep.filter((registro) => registro.codigo_madre === codigoMadre) : conIep;
            const desde = filtro.fecha_inicio ? filtro.fecha_inicio.split('-').reverse().join('-') : '';
            const hasta = filtro.fecha_hasta ? filtro.fecha_hasta.split('-').reverse().join('-') : '';
            filas = filas.filter((registro) => (!desde || registro.fecha >= desde) && (!hasta || registro.fecha <= hasta));

            const total = filas.length;
            const hembras = filas.filter((registro) => registro.sexo_valor === 2).length;
            const machos = filas.filter((registro) => registro.sexo_valor === 1).length;
            const vivos = filas.filter((registro) => registro.vivo).length;
            const pesos = filas.map((registro) => registro.peso).filter((peso) => peso !== null && !Number.isNaN(peso));
            const ieps = filas.map((registro) => registro.iep).filter((iep) => iep !== null);
            const porcentaje = (cantidad) => total ? `${Math.round((cantidad / total) * 1000) / 10}%` : '—';
            const promedio = (valores) => valores.length ? valores.reduce((totalValor, valor) => totalValor + valor, 0) / valores.length : null;

            const rango = [filtro.fecha_inicio && `Desde ${filtro.fecha_inicio}`, filtro.fecha_hasta && `Hasta ${filtro.fecha_hasta}`].filter(Boolean).join(' ');
            document.getElementById('titulo').textContent = codigoMadre ? 'Ficha de Nacimientos' : 'Informe de Nacimientos';
            document.getElementById('meta').textContent = `${rango || 'Todos los registros'} | Generado: ${formatDate(new Date().toISOString())}`;

            if (codigoMadre) {
                const madre = registros.find((registro) => registro.codigo_madre === codigoMadre) || { codigo_madre: codigoMadre, arete_madre: '—', nombre_madre: '—' };
                const resumenMadre = tabla(['Codigo SOVCA', 'Nro. arete', 'Nombre', 'Total nacimientos', '% vivos', '% hembras', '% machos'], fila([madre.codigo_madre, madre.arete_madre, madre.nombre_madre, total, porcentaje(vivos), porcentaje(hembras), porcentaje(machos)]), 7);
                const pesosMadre = tabla(['Peso promedio al nacer', 'Peso maximo al nacer', 'Peso minimo al nacer', 'IEP promedio (dias)'], fila([number(promedio(pesos)), number(pesos.length ? Math.max(...pesos) : null), number(pesos.length ? Math.min(...pesos) : null), number(promedio(ieps), 0)]), 4);
                const detalle = filas.map((registro) => fila([formatDate(registro.fecha), registro.nro_parto, registro.tipo_servicio || '—', registro.nacio, registro.sexo, number(registro.peso), registro.id_cria, registro.aptitud, registro.iep ?? '—'])).join('');
                document.getElementById('contenido').innerHTML = resumenMadre + pesosMadre + tabla(['Fecha', 'Parto Nro.', 'Tipo de servicio', 'Nacio', 'Sexo', 'Peso al nacer', 'ID', 'Aptitud del animal', 'IEP (dias)'], detalle, 9);
                return;
            }

            const resumen = tabla(['Total nacimientos', 'Total hembras', 'Total machos', '% vivos', '% machos', '% hembras', 'Peso promedio', 'P. max al nacer', 'P. min al nacer', 'IEP promedio (dias)'], fila([total, hembras, machos, porcentaje(vivos), porcentaje(machos), porcentaje(hembras), number(promedio(pesos)), number(pesos.length ? Math.max(...pesos) : null), number(pesos.length ? Math.min(...pesos) : null), number(promedio(ieps), 0)]), 10);
            const detalle = filas.map((registro) => fila([registro.codigo_madre, registro.arete_madre, formatDate(registro.fecha), registro.nro_parto, registro.tipo_servicio || '—', registro.nacio, registro.sexo, number(registro.peso), registro.id_cria, registro.iep ?? '—'])).join('');
            document.getElementById('contenido').innerHTML = resumen + tabla(['Codigo SOVCA', 'Nro. arete madre', 'Fecha', 'Parto Nro.', 'Tipo de servicio', 'Nacio', 'Sexo', 'Peso al nacer', 'ID', 'IEP (dias)'], detalle, 10);
        }

        fetch('../../../data_json/data_reporte_nacimientos.php', { credentials: 'same-origin' })
            .then((response) => response.ok ? response.json() : Promise.reject(new Error('No se pudieron cargar los nacimientos.')))
            .then(renderizar)
            .catch((error) => { document.getElementById('meta').innerHTML = `<span class="error">${escapeHtml(error.message)}</span>`; });

        document.getElementById('imprimir').addEventListener('click', () => window.print());
        document.getElementById('cerrar').addEventListener('click', () => window.close());
    </script>
</body>
</html>
